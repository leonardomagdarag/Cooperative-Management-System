<?php

use App\Models\Account;
use App\Models\Branch;
use App\Models\MemberApplication;
use App\Models\User;
use App\Services\TurnstileService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    mock(TurnstileService::class)->shouldReceive('verify')->andReturn(true);
});

function applicantPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Juan',
        'middle_name' => 'Santos',
        'last_name' => 'Dela Cruz',
        'gender' => 'Male',
        'birthdate' => '1995-05-20',
        'age' => 30,
        'phone' => '09171234567',
        'email' => 'juan.delacruz@example.com',
        'region' => 'NCR',
        'province' => 'Metro Manila',
        'city' => 'Manila',
        'barangay' => 'Malate',
        'street' => '123 Mabini St.',
        'zip_code' => '1004',
        'occupation' => 'Teacher',
        'monthly_income' => '10000-20000',
        'membership_type' => 'Regular',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => '1',
        'cf-turnstile-response' => 'test-token',
    ], $overrides);
}

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new applicants register as inactive members pending officer approval', function () {
    $response = $this->post('/register', applicantPayload(['email' => 'pending.applicant@example.com']));

    $response->assertRedirect(route('login_page', absolute: false));

    $user = User::where('email', 'pending.applicant@example.com')->firstOrFail();

    expect($user->account_status)->toBe('inactive')
        ->and($user->profile_completion_required)->toBeTrue()
        ->and($user->hasRole('member'))->toBeTrue()
        ->and($user->branch_id)->toBe(Branch::where('is_head_office', true)->value('id'))
        ->and($user->email_verified_at)->toBeNull()
        ->and(Account::where('user_id', $user->id)->count())->toBe(0);

    $application = MemberApplication::where('user_id', $user->id)->firstOrFail();
    expect($application->status)->toBe('pending');
    expect($application->original_email)->toBe($user->email);

    $this->assertGuest();
});

test('registration records the membership application for officer review', function () {
    $this->post('/register', applicantPayload(['email' => 'application.record@example.com']));

    $user = User::where('email', 'application.record@example.com')->firstOrFail();

    $application = MemberApplication::where('user_id', $user->id)->first();
    expect($application)->not->toBeNull()
        ->and($application->first_name)->toBe('Juan')
        ->and($application->membership_type)->toBe('Regular')
        ->and($application->status)->toBe('pending');
});

test('registering notifies admin, manager and accountant officers about the application', function () {
    $this->post('/register', applicantPayload(['email' => 'officer.notice@example.com']));

    $officers = User::role(['admin', 'manager', 'accountant'])->get();

    expect($officers->count())->toBeGreaterThanOrEqual(3);

    foreach ($officers as $officer) {
        $notification = $officer->notifications()
            ->where('type', 'member_application')
            ->first();

        expect($notification)->not->toBeNull()
            ->and($notification->message)->toContain('awaiting review');
    }
});

test('registering does not queue an email verification for an awaiting-approval applicant', function () {
    $this->post('/register', applicantPayload(['email' => 'no.verification@example.com']));

    $user = User::where('email', 'no.verification@example.com')->firstOrFail();

    expect($user->notifications()->where('type', 'email_verification')->exists())->toBeFalse();
});

test('registration rejects a duplicate email while respecting archived applications', function () {
    $existing = User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post('/register', applicantPayload([
        'email' => $existing->email,
        'first_name' => 'Other',
        'last_name' => 'Person',
    ]));

    $response->assertSessionHasErrors('email');
});

test('member registering with NCR region and empty province persists successfully', function () {
    $response = $this->post('/register', applicantPayload([
        'email' => 'ncr.applicant@example.com',
        'region' => 'National Capital Region (NCR)',
        'province' => '',
        'city' => 'Quezon City',
        'barangay' => 'Batasan Hills',
    ]));

    $response->assertRedirect(route('login_page', absolute: false));

    $user = User::where('email', 'ncr.applicant@example.com')->firstOrFail();
    $address = $user->personalData->address;

    expect($address->region)->toBe('National Capital Region (NCR)')
        ->and($address->city_municipality)->toBe('Quezon City')
        ->and($address->barangay)->toBe('Batasan Hills');
});
