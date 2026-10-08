<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\OtpChallenge;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SecurityOtpService;
use App\Support\PersonPasswordPolicy;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->admin->assignRole('chairman');
});

test('user seeder does not create duplicate fixed login accounts', function () {
    $this->seed(UserSeeder::class);

    expect(User::query()->whereIn('email', [
        'developer@example.com',
        'admin@example.com',
        'manager@example.com',
        'accountant@example.com',
        'loan@example.com',
    ])->count())->toBe(5);
});

test('person password policy rejects predictable name plus digits', function () {
    $rules = PersonPasswordPolicy::passwordRules(false, PersonPasswordPolicy::normalizedNameTokens(['John', 'Doe']));

    $fails = validator(['password' => 'John12345'], ['password' => $rules])->fails();

    expect($fails)->toBeTrue();
});

test('person password policy requires letters and numbers', function () {
    $rules = PersonPasswordPolicy::passwordRules(false, []);

    expect(validator(['password' => 'abcdef'], ['password' => $rules])->fails())->toBeTrue()
        ->and(validator(['password' => 'password123'], ['password' => $rules])->passes())->toBeTrue()
        ->and(validator(['password' => 'short1'], ['password' => $rules])->fails())->toBeTrue();
});

test('edit password policy allows blank passwords', function () {
    $rules = PersonPasswordPolicy::passwordRules(true, []);

    expect(validator(['password' => ''], ['password' => $rules])->passes())->toBeTrue();
});

test('member create rejects a predictable password through the api', function () {
    actingAs($this->admin);

    $this->postJson('/api/members', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'birth_date' => now()->subYears(30)->toDateString(),
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'john.predictable@example.com',
        'phone_number' => '09171234567',
        'password' => 'John12345',
        'password_confirmation' => 'John12345',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('member edit allows a blank password', function () {
    $member = User::factory()->create([
        'email' => 'jane.edit@example.com',
    ]);

    actingAs($this->admin);

    $this->putJson('/api/members/'.$member->id, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane.edit@example.com',
    ])->assertOk();
});

test('member password changes require an OTP bound to the member', function () {
    $member = User::factory()->create(['email' => 'member.password@example.com']);
    $payload = [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => $member->email,
        'password' => 'Securepass123',
    ];

    actingAs($this->admin);

    $this->postJson('/api/members/'.$member->id.'/password/send-otp')->assertOk();
    expect(OtpChallenge::query()
        ->where('user_id', $this->admin->id)
        ->where('purpose', 'member_password_change')
        ->latest('id')
        ->first()?->context)->toBe(['member_id' => $member->id]);

    $this->putJson('/api/members/'.$member->id, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('otp_code');

    $securityOtp = app(SecurityOtpService::class);
    $issued = $securityOtp->issueChallenge(
        $this->admin,
        $securityOtp->memberPasswordChangePurpose(),
        ['member_id' => $member->id],
    );

    $this->putJson('/api/members/'.$member->id, array_merge($payload, ['otp_code' => $issued['otp_code']]))
        ->assertOk();

    expect(Hash::check('Securepass123', $member->fresh()->password))->toBeTrue();
});

test('member profile returns education and special loan eligibility fields', function () {
    $member = User::factory()->create([
        'email' => 'member.show@example.com',
        'special_loan_eligible' => true,
    ]);

    actingAs($this->admin);

    $this->getJson('/api/members/'.$member->id)
        ->assertOk()
        ->assertJsonPath('special_loan_eligible', true)
        ->assertJsonPath('personal_data.education', $member->personalData->education);
});

test('member profile returns recent activity logs', function () {
    $member = User::factory()->create([
        'email' => 'member.logs@example.com',
    ]);

    actingAs($this->admin);

    $this->postJson('/api/members/'.$member->id.'/toggle-special-loan')->assertOk();

    $this->getJson('/api/members/'.$member->id)
        ->assertOk()
        ->assertJsonPath('recent_logs', fn ($logs) => is_array($logs)
            && count($logs) > 0
            && collect($logs)->contains('event', 'member.special-loan-eligibility'));
});

test('member special loan eligibility toggle flips the flag and audits the change', function () {
    $member = User::factory()->create([
        'email' => 'member.eligible@example.com',
        'special_loan_eligible' => false,
    ]);

    actingAs($this->admin);

    $this->postJson('/api/members/'.$member->id.'/toggle-special-loan')
        ->assertOk()
        ->assertJsonPath('special_loan_eligible', true);

    expect($member->fresh()->special_loan_eligible)->toBeTrue();

    $this->postJson('/api/members/'.$member->id.'/toggle-special-loan')
        ->assertOk()
        ->assertJsonPath('special_loan_eligible', false);

    expect($member->fresh()->special_loan_eligible)->toBeFalse();
});

test('member special loan eligibility toggle requires the members.update permission', function () {
    $member = User::factory()->create([
        'email' => 'member.no-perm@example.com',
        'special_loan_eligible' => false,
    ]);

    $noPermission = User::factory()->create([
        'email' => 'list-viewer@example.com',
    ]);
    $noPermission->givePermissionTo(Permission::findByName('members.view'));

    actingAs($noPermission);

    $this->postJson('/api/members/'.$member->id.'/toggle-special-loan')->assertForbidden();

    expect($member->fresh()->special_loan_eligible)->toBeFalse();
});

test('profile email changes require an OTP bound to the new email address', function () {
    $member = User::factory()->create([
        'email' => 'member.current@example.com',
    ]);
    $payload = [
        'title' => $member->personalData->title ?: 'Member',
        'first_name' => $member->personalData->first_name,
        'last_name' => $member->personalData->last_name,
        'email' => 'member.next@example.com',
    ];

    actingAs($member);

    $this->postJson('/api/profile/email/send-otp', ['email' => $payload['email']])->assertOk();
    expect(OtpChallenge::query()
        ->where('user_id', $member->id)
        ->where('purpose', 'email_change')
        ->latest('id')
        ->first()?->context)->toBe(['email' => 'member.next@example.com']);

    $this->putJson('/api/profile', $payload)->assertUnprocessable()
        ->assertJsonValidationErrors('email_otp_code');

    $securityOtp = app(SecurityOtpService::class);
    $issued = $securityOtp->issueChallenge(
        $member,
        $securityOtp->emailChangePurpose(),
        ['email' => 'member.next@example.com'],
    );

    $this->putJson('/api/profile', array_merge($payload, ['email_otp_code' => $issued['otp_code']]))
        ->assertOk();

    expect($member->fresh()->email)->toBe('member.next@example.com')
        ->and($member->fresh()->email_verified_at)->toBeNull();
});

test('loan total payable applies interest per month across the term', function () {
    $loan = Loan::factory()->make([
        'loan_amount' => 3000,
        'interest_rate' => 3,
        'term_months' => 3,
    ]);

    expect($loan->totalInterest())->toBe(270.0)
        ->and($loan->totalPayable())->toBe(3270.0)
        ->and($loan->monthlyPayment())->toBe(1090.0);
});

test('loan payment schedule reconciles exactly to the model payable amount', function () {
    $loan = Loan::factory()->make([
        'loan_amount' => 3000,
        'interest_rate' => 3,
        'term_months' => 3,
        'approved_at' => now()->startOfDay(),
    ]);

    $schedule = $loan->paymentSchedule();

    expect($schedule)->toHaveCount(3)
        ->and(array_sum(array_column($schedule, 'payment')))->toEqual(3270.0)
        ->and($schedule[2]['total_payment'])->toEqual($loan->totalPayable())
        ->and($schedule[0]['due_date'])->toBe(now()->startOfDay()->addMonth()->format('M d, Y'))
        ->and($schedule[2]['balance'])->toEqual(0.0);
});

test('counter loan payment covers the full payable amount and marks the loan paid off', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($member);

    $this->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'account_id' => $account->id,
        'amount' => $loan->totalPayable(),
        'transaction_mode' => 'counter',
    ])->assertCreated();

    expect($loan->fresh()->loan_status)->toBe('paid_off')
        ->and((float) $loan->fresh()->remainingBalance())->toBe(0.0);
});

test('online loan payment stays pending and does not change balances', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($member);

    $this->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'account_id' => $account->id,
        'amount' => 620,
        'transaction_mode' => 'online',
    ])->assertCreated();

    $payment = Transaction::query()
        ->where('loan_id', $loan->id)
        ->where('transaction_type', 'loan_payment')
        ->latest('id')
        ->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('pending')
        ->and($loan->fresh()->loan_status)->toBe('active')
        ->and((float) $account->fresh()->balance)->toBe(5000.0);
});

test('online deposits require proof of payment', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 0,
    ]);

    actingAs($member);

    $this->postJson('/api/services/deposit', [
        'amount' => 1000,
        'account_type' => 'savings',
        'transaction_mode' => 'online',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('proof');
});

test('online deposits with proof stay pending until verification', function () {
    Storage::fake('public');

    $member = User::factory()->create();
    $member->assignRole('member');

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 0,
    ]);

    actingAs($member);

    $this->postJson('/api/services/deposit', [
        'amount' => 1000,
        'account_type' => 'savings',
        'transaction_mode' => 'online',
        'proof' => UploadedFile::fake()->create('proof.jpg', 200, 'image/jpeg'),
    ])->assertCreated();

    $transaction = Transaction::query()
        ->where('transaction_type', 'deposit')
        ->latest('id')
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->status)->toBe('pending')
        ->and($transaction->proof_of_payment_path)->not->toBeNull();
});

test('reserved role names cannot be created', function () {
    actingAs($this->admin);

    $this->postJson('/api/roles', ['name' => 'terminated'])->assertUnprocessable();

    expect(Role::query()->where('name', 'terminated')->where('guard_name', 'web')->count())->toBe(1);
});

test('base roles are protected from deletion and renaming', function () {
    actingAs($this->admin);

    $this->deleteJson('/api/roles/manager')->assertUnprocessable();
    $this->putJson('/api/roles/manager/rename', ['name' => 'boss'])->assertUnprocessable();

    expect(Role::query()->where('name', 'manager')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('assigned custom roles cannot be renamed or deleted', function () {
    actingAs($this->admin);

    $this->postJson('/api/roles', ['name' => 'Branch Supervisor'])->assertCreated();

    $officer = User::factory()->create();
    $officer->syncRoles(['branch-supervisor']);

    $this->putJson('/api/roles/branch-supervisor/rename', ['name' => 'Operations Lead'])->assertUnprocessable();
    $this->deleteJson('/api/roles/branch-supervisor')->assertUnprocessable();

    expect(Role::query()->where('name', 'branch-supervisor')->where('guard_name', 'web')->exists())->toBeTrue();
});

test('admin can rename and delete an unused custom role', function () {
    actingAs($this->admin);

    $this->postJson('/api/roles', ['name' => 'Compliance Officer'])->assertCreated();

    $this->putJson('/api/roles/compliance-officer/rename', ['name' => 'Compliance Lead'])->assertOk();
    expect(Role::query()->where('name', 'compliance-officer')->where('guard_name', 'web')->exists())->toBeFalse()
        ->and(Role::query()->where('name', 'compliance-lead')->where('guard_name', 'web')->exists())->toBeTrue();

    $this->deleteJson('/api/roles/compliance-lead')->assertOk();
    expect(Role::query()->where('name', 'compliance-lead')->where('guard_name', 'web')->exists())->toBeFalse();
});

test('loan product duplicate code is rejected on create but allowed on update', function () {
    actingAs($this->admin);

    $payload = [
        'code' => 'veteran',
        'name' => 'Veteran Loan',
        'base_type' => 'special',
        'interest_rate' => 2,
        'max_amount' => 50000,
        'max_term' => 24,
        'minimum_membership_years' => 1,
        'required_co_makers' => 1,
        'sort_order' => 5,
    ];

    $this->postJson('/api/loan-products', $payload)->assertCreated();

    $this->postJson('/api/loan-products', $payload)->assertUnprocessable();

    $product = LoanProduct::where('code', 'veteran')->firstOrFail();

    $this->putJson('/api/loan-products/'.$product->id, array_merge($payload, ['name' => 'Veteran Loan Plus']))->assertOk();
});

test('non-admin cannot encode loans', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    actingAs($member);

    $this->postJson('/api/loans', [
        'user_id' => $member->id,
        'loan_product' => 'group',
        'loan_type' => 'group',
        'loan_status' => 'pending',
        'loan_amount' => 1000,
        'interest_rate' => 1.5,
        'term_months' => 6,
        'payment_frequency' => 'monthly',
        'applied_at' => now()->toDateString(),
    ])->assertForbidden();
});

test('seeders create accountant account with accountant role', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $accountant = User::where('email', 'accountant@example.com')->first();

    expect($accountant)->not->toBeNull()
        ->and($accountant->role)->toBe('accountant')
        ->and((bool) $accountant->is_officer)->toBeTrue()
        ->and($accountant->hasRole('accountant'))->toBeTrue()
        ->and($accountant->can('loans.view'))->toBeTrue()
        ->and($accountant->can('members.create'))->toBeTrue()
        ->and($accountant->cannot('admin'))->toBeTrue();
});

test('permission names referenced by policies exist in the catalog', function () {
    $permissions = Permission::pluck('name')->all();

    foreach (['members.create', 'members.update', 'members.view', 'settings.update', 'loans.view'] as $name) {
        expect(in_array($name, $permissions, true))->toBeTrue("Permission [{$name}] missing");
    }
});

test('loan close rejects loans that still have an outstanding balance', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($this->admin);

    $this->postJson('/api/loans/'.$loan->id.'/close')->assertStatus(422);

    expect($loan->fresh()->loan_status)->toBe('active');
});

test('loan close succeeds when the loan is fully paid', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    Transaction::factory()->create([
        'account_id' => $account->id,
        'loan_id' => $loan->id,
        'transaction_type' => 'loan_payment',
        'amount' => $loan->totalPayable(),
        'status' => 'completed',
        'transaction_mode' => 'counter',
        'transaction_date' => now(),
    ]);

    actingAs($this->admin);

    $this->postJson('/api/loans/'.$loan->id.'/close')->assertOk();

    expect($loan->fresh()->loan_status)->toBe('paid_off');
});

test('members cannot close loans', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'loan_status' => 'active',
    ]);

    actingAs($member);

    $this->postJson('/api/loans/'.$loan->id.'/close')->assertForbidden();

    expect($loan->fresh()->loan_status)->toBe('active');
});

test('loan update rejects setting paid_off status when loan has an outstanding balance', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($this->admin);

    $this->putJson('/api/loans/'.$loan->id, [
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'paid_off',
    ])->assertStatus(422);

    expect($loan->fresh()->loan_status)->toBe('active');
});

test('loan payment rejects paying a loan that belongs to a different member', function () {
    $member1 = User::factory()->create();
    $member1->assignRole('member');

    $member2 = User::factory()->create();
    $member2->assignRole('member');

    Account::factory()->create([
        'user_id' => $member1->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan2 = Loan::factory()->create([
        'user_id' => $member2->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'loan_status' => 'active',
    ]);

    actingAs($this->admin);

    $this->postJson('/api/services/loan-payment', [
        'member_id' => $member1->id,
        'loan_id' => $loan2->id,
        'amount' => 500,
        'transaction_mode' => 'counter',
    ])->assertStatus(422)
        ->assertJsonPath('message', 'The selected loan does not belong to this member.');
});

test('deposit with a duplicate client_request_id is not created twice', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 0,
    ]);

    actingAs($member);

    $payload = [
        'amount' => 500,
        'account_type' => 'savings',
        'transaction_mode' => 'counter',
        'client_request_id' => 'req_test_0001',
    ];

    $this->postJson('/api/services/deposit', $payload)->assertCreated();
    $this->postJson('/api/services/deposit', $payload)->assertOk();

    expect(Transaction::where('transaction_type', 'deposit')->where('client_request_id', 'req_test_0001')->count())->toBe(1);
});

test('withdrawal with a duplicate client_request_id is not created twice', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    actingAs($member);

    $payload = [
        'amount' => 1000,
        'transaction_mode' => 'counter',
        'client_request_id' => 'req_wd_0001',
    ];

    $this->postJson('/api/services/withdrawal', $payload)->assertCreated();
    $this->postJson('/api/services/withdrawal', $payload)->assertOk();

    expect(Transaction::where('transaction_type', 'withdrawal')->where('client_request_id', 'req_wd_0001')->count())->toBe(1);
});

test('loan payment with a duplicate client_request_id is not created twice', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $account = Account::factory()->create([
        'user_id' => $member->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $member->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($member);

    $payload = [
        'loan_id' => $loan->id,
        'account_id' => $account->id,
        'amount' => 500,
        'transaction_mode' => 'counter',
        'client_request_id' => 'req_lp_0001',
    ];

    $this->postJson('/api/services/loan-payment', $payload)->assertCreated();
    $this->postJson('/api/services/loan-payment', $payload)->assertOk();

    expect(Transaction::where('transaction_type', 'loan_payment')->where('client_request_id', 'req_lp_0001')->count())->toBe(1);
});

test('member create rejects an underage birth date', function () {
    actingAs($this->admin);

    $this->postJson('/api/members', [
        'first_name' => 'Nino',
        'last_name' => 'Tindero',
        'birth_date' => now()->subYears(17)->toDateString(),
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'nino.underage@example.com',
        'phone_number' => '09171234567',
        'password' => 'S3curepass!',
        'password_confirmation' => 'S3curepass!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('birth_date');
});

test('member create rejects a future birth date', function () {
    actingAs($this->admin);

    $this->postJson('/api/members', [
        'first_name' => 'Future',
        'last_name' => 'Child',
        'birth_date' => now()->addYear()->toDateString(),
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'future.child@example.com',
        'phone_number' => '09171234567',
        'password' => 'S3curepass!',
        'password_confirmation' => 'S3curepass!',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('birth_date');
});

test('member create rejects an email already used on a contact record', function () {
    actingAs($this->admin);

    Contact::create([
        'phone_no' => '09170000000',
        'email' => 'taken@example.com',
    ]);

    $this->withSession(['verified_officer_phone' => '09171234567'])
        ->postJson('/api/members', [
            'first_name' => 'Taken',
            'last_name' => 'Email',
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
            'civil_status' => 'single',
            'email' => 'taken@example.com',
            'phone_number' => '09171234567',
            'password' => 'S3curepass!',
            'password_confirmation' => 'S3curepass!',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('admin-created member starts unverified and receives a verification notification', function () {
    actingAs($this->admin);

    $this->withSession(['verified_officer_phone' => '09171234567'])
        ->postJson('/api/members', [
            'first_name' => 'Carl',
            'last_name' => 'Miguel',
            'birth_date' => now()->subYears(25)->toDateString(),
            'gender' => 'male',
            'civil_status' => 'single',
            'email' => 'carl.miguel@example.com',
            'phone_number' => '09171234567',
            'password' => 'S3curepass!',
            'password_confirmation' => 'S3curepass!',
        ])->assertCreated();

    $member = User::where('email', 'carl.miguel@example.com')->firstOrFail();

    expect($member->email_verified_at)->toBeNull();
    expect($member->notifications()->where('type', 'email_verification')->exists())->toBeTrue();
    expect($member->personalData->contact->phone_verified_at)->not->toBeNull();
});

test('member cannot pay another member loan without supplying their own id', function () {
    $owner = User::factory()->create(['email' => 'loan.owner@example.com']);
    $attacker = User::factory()->create(['email' => 'loan.attacker@example.com']);

    $ownerAccount = Account::create([
        'user_id' => $owner->id,
        'account_type' => 'savings',
        'balance' => 5000,
    ]);

    $loan = Loan::factory()->create([
        'user_id' => $owner->id,
        'loan_amount' => 1000,
        'interest_rate' => 12,
        'term_months' => 1,
        'payment_frequency' => 'monthly',
        'loan_status' => 'active',
    ]);

    actingAs($attacker);

    $this->postJson('/api/services/loan-payment', [
        'loan_id' => $loan->id,
        'account_id' => $ownerAccount->id,
        'amount' => 100,
        'transaction_mode' => 'counter',
        'client_request_id' => 'req_lp_owner_0001',
    ])->assertForbidden();

    expect(Transaction::where('transaction_type', 'loan_payment')->where('client_request_id', 'req_lp_owner_0001')->count())->toBe(0);
});
