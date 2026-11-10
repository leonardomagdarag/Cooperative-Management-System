<?php

use App\Jobs\VerifyXenditPaymentJob;
use App\Models\Account;
use App\Models\Loan;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\XenditService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    config(['xendit.test_secret_key' => 'test-key', 'xendit.base_url' => 'https://api.xendit.co']);
    Http::preventStrayRequests();

    $this->member = User::factory()->create();
    $this->account = Account::factory()->create(['user_id' => $this->member->id, 'balance' => 1000]);
    $this->loan = Loan::factory()->active()->create(['user_id' => $this->member->id]);
    $this->payment = Transaction::create([
        'transaction_type' => 'loan_payment',
        'transaction_mode' => 'online',
        'payment_gateway' => 'xendit',
        'xendit_session_id' => 'session-reconciliation',
        'xendit_mode' => 'test',
        'amount' => 100,
        'fee_amount' => 0,
        'loan_id' => $this->loan->id,
        'status' => 'pending',
        'recorded_by' => $this->member->id,
        'transaction_date' => now(),
    ]);
});

test('reconciliation queues only pending unverified Xendit checkouts even when new checkouts are disabled', function () {
    Queue::fake();
    SystemSetting::set('xendit_enabled', false, 'boolean');
    $deposit = $this->payment->replicate();
    $deposit->fill([
        'transaction_number' => null, 'loan_id' => null, 'account_id' => $this->account->id,
        'transaction_type' => 'deposit', 'xendit_session_id' => 'session-deposit',
    ])->save();
    foreach ([
        ['status' => 'completed'], ['status' => 'rejected'],
        ['xendit_payment_id' => 'already-verified'], ['payment_gateway' => 'paymongo'],
        ['xendit_session_id' => null], ['xendit_session_id' => ''],
    ] as $changes) {
        $other = $this->payment->replicate();
        $other->fill(['transaction_number' => null, ...$changes])->save();
    }

    $this->artisan('payments:reconcile-xendit')->assertSuccessful();

    Queue::assertPushed(VerifyXenditPaymentJob::class, 2);
    Queue::assertPushedOn('payments', VerifyXenditPaymentJob::class, fn ($job) => $job->transactionId === $this->payment->id);
    Queue::assertPushedOn('payments', VerifyXenditPaymentJob::class, fn ($job) => $job->transactionId === $deposit->id);
    Http::assertNothingSent();
});

test('repeated scheduler dispatches do not duplicate outstanding verification jobs', function () {
    config(['queue.default' => 'database']);
    $this->artisan('payments:reconcile-xendit')->assertSuccessful();
    $this->artisan('payments:reconcile-xendit')->assertSuccessful();

    expect(DB::table('jobs')->where('queue', 'payments')->count())->toBe(1);
});

test('queued verification handles provider lifecycle without posting money', function (string $providerStatus, string $expectedStatus, bool $verified) {
    Http::fake([
        'api.xendit.co/sessions/session-reconciliation' => Http::response([
            'status' => $providerStatus, 'payment_id' => 'payment-reconciliation',
            'amount' => 100, 'currency' => 'PHP', 'reference_id' => $this->payment->transaction_number,
        ]),
        'api.xendit.co/v3/payments/payment-reconciliation' => Http::response([
            'status' => 'SUCCEEDED', 'payment_id' => 'payment-reconciliation',
            'request_amount' => 100, 'currency' => 'PHP', 'reference_id' => $this->payment->transaction_number,
        ]),
    ]);

    (new VerifyXenditPaymentJob($this->payment->id))->handle(app(XenditService::class));
    expect($this->payment->fresh()->status)->toBe($expectedStatus)
        ->and(filled($this->payment->fresh()->xendit_payment_id))->toBe($verified)
        ->and($this->payment->fresh()->account_id)->toBeNull()
        ->and((float) $this->loan->fresh()->totalPaid())->toBe(0.0)
        ->and($this->account->fresh()->balance)->toBe('1000.00');

    if ($verified) {
        // A retry after a callback/manual verification does not call Xendit again.
        (new VerifyXenditPaymentJob($this->payment->id))->handle(app(XenditService::class));
        Http::assertSentCount(2);
        expect(Activity::where('event', 'payment.xendit.verified')->where('subject_id', $this->payment->id)->count())->toBe(1);
    }
})->with([
    'paid' => ['COMPLETED', 'pending', true],
    'not paid yet' => ['ACTIVE', 'pending', false],
    'expired' => ['EXPIRED', 'rejected', false],
    'canceled' => ['CANCELED', 'rejected', false],
]);

test('queued verification skips a transaction already handled or deleted', function (string $state) {
    if ($state === 'deleted') {
        $this->payment->delete();
    } else {
        $this->payment->update($state === 'verified'
            ? ['xendit_payment_id' => 'verified-by-webhook'] : ['status' => $state]);
    }

    (new VerifyXenditPaymentJob($this->payment->id))->handle(app(XenditService::class));
    Http::assertNothingSent();
})->with(['completed', 'rejected', 'verified', 'deleted']);

test('provider failures remain retryable without changing the payment', function () {
    Http::fake(['api.xendit.co/sessions/*' => Http::response(['message' => 'IP is not allowlisted'], 403)]);

    expect(fn () => (new VerifyXenditPaymentJob($this->payment->id))->handle(app(XenditService::class)))
        ->toThrow(RuntimeException::class, 'IP is not allowlisted');
    expect($this->payment->fresh()->status)->toBe('pending')
        ->and($this->payment->fresh()->xendit_payment_id)->toBeNull();
});

test('the payments worker backs off a failed verification and keeps its unique job', function () {
    config(['queue.default' => 'database']);
    Http::fake(['api.xendit.co/sessions/*' => Http::response(['message' => 'Temporarily unavailable'], 503)]);
    $this->artisan('payments:reconcile-xendit')->assertSuccessful();

    $this->artisan('queue:work', [
        'connection' => 'database', '--queue' => 'payments', '--once' => true, '--sleep' => 0,
    ])->assertSuccessful();

    $queued = DB::table('jobs')->where('queue', 'payments')->sole();
    expect($queued->attempts)->toBe(1)
        ->and($queued->available_at)->toBeGreaterThanOrEqual(now()->addSeconds(55)->timestamp)
        ->and($this->payment->fresh()->status)->toBe('pending');
    $this->artisan('payments:reconcile-xendit')->assertSuccessful();
    expect(DB::table('jobs')->where('queue', 'payments')->count())->toBe(1);
});

test('racing verification does not duplicate audit entries or reopen an officer rejection', function (bool $rejected) {
    Http::fake([
        'api.xendit.co/sessions/*' => Http::response([
            'status' => 'COMPLETED', 'payment_id' => 'payment-reconciliation',
            'amount' => 100, 'currency' => 'PHP', 'reference_id' => $this->payment->transaction_number,
        ]),
        'api.xendit.co/v3/payments/*' => Http::response([
            'status' => 'SUCCEEDED', 'payment_id' => 'payment-reconciliation',
            'request_amount' => 100, 'currency' => 'PHP', 'reference_id' => $this->payment->transaction_number,
        ]),
    ]);
    // Both requests loaded an unverified model before one request finished.
    $stale = $this->payment->fresh();
    app(XenditService::class)->verifyAndCompletePayment($this->payment);
    if ($rejected) {
        $this->payment->update(['status' => 'rejected', 'rejection_reason' => 'Officer review']);
    }
    app(XenditService::class)->verifyAndCompletePayment($stale);

    expect($this->payment->fresh()->status)->toBe($rejected ? 'rejected' : 'pending')
        ->and(Activity::where('event', 'payment.xendit.verified')->where('subject_id', $this->payment->id)->count())->toBe(1);
})->with([false, true]);
