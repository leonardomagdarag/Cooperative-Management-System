<?php

use App\Mail\OtpCodeMail;
use App\Models\Account;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    SystemSetting::set('security_withdrawal_otp_threshold', 2000);
});

test('end-to-end withdrawal OTP approval flow gates high-value withdrawals and debits balance only on valid OTP', function () {
    Mail::fake();

    $member = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 10000.00,
    ]);

    $manager = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $manager->assignRole('manager');

    // 1. Member requests a high-value withdrawal (₱5,000 > ₱2,000 threshold)
    $this->actingAs($member);
    $response = $this->postJson('/api/services/withdrawal', [
        'amount' => 5000,
        'transaction_mode' => 'counter',
        'reason' => 'Emergency expenses',
    ])->assertCreated();

    $transactionId = $response->json('data.id');
    $transaction = Transaction::findOrFail($transactionId);

    expect($transaction->status)->toBe('pending')
        ->and((float) $account->fresh()->balance)->toBe(10000.00);

    // 2. Accountant attempts approval without OTP -> rejected with 422
    $this->actingAs($manager);
    $this->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'A verification code is required to approve this withdrawal.');

    expect((float) $account->fresh()->balance)->toBe(10000.00)
        ->and($transaction->fresh()->status)->toBe('pending');

    // 3. Accountant requests OTP for this withdrawal
    $this->postJson("/api/services/transactions/{$transaction->id}/send-otp")
        ->assertOk()
        ->assertJsonPath('message', 'A verification code was sent to your email.');

    // Capture the generated OTP from the sent mailable
    $capturedOtp = null;
    Mail::assertSent(OtpCodeMail::class, function (OtpCodeMail $mail) use (&$capturedOtp) {
        $capturedOtp = $mail->otpCode;

        return true;
    });

    expect($capturedOtp)->not->toBeNull()
        ->and(strlen($capturedOtp))->toBe(6);

    // 4. Accountant attempts approval with invalid OTP -> rejected with 422
    $invalidOtp = $capturedOtp === '123456' ? '654321' : '123456';
    $this->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
        'otp_code' => $invalidOtp,
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Invalid or expired verification code.');

    expect((float) $account->fresh()->balance)->toBe(10000.00)
        ->and($transaction->fresh()->status)->toBe('pending');

    // 5. Accountant approves with valid OTP -> succeeds, balance decremented
    $this->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
        'otp_code' => $capturedOtp,
    ])->assertOk()
        ->assertJsonPath('message', 'Transaction approved.');

    expect($transaction->fresh()->status)->toBe('completed')
        ->and((float) $account->fresh()->balance)->toBe(5000.00);
});

test('low-value withdrawal below OTP threshold can be approved directly without OTP', function () {
    $member = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000.00,
    ]);

    $manager = User::factory()->create([
        'profile_completion_required' => false,
    ]);
    $manager->assignRole('manager');

    // Member requests a low-value withdrawal (₱1,500 < ₱2,000 threshold)
    $this->actingAs($member);
    $response = $this->postJson('/api/services/withdrawal', [
        'amount' => 1500,
        'transaction_mode' => 'counter',
        'reason' => 'Allowance',
    ])->assertCreated();

    $transaction = Transaction::findOrFail($response->json('data.id'));

    // Accountant approves directly without providing OTP
    $this->actingAs($manager);
    $this->postJson("/api/services/approve/{$transaction->id}", [
        'action' => 'approve',
    ])->assertOk()
        ->assertJsonPath('message', 'Transaction approved.');

    expect($transaction->fresh()->status)->toBe('completed')
        ->and((float) $account->fresh()->balance)->toBe(3500.00);
});
