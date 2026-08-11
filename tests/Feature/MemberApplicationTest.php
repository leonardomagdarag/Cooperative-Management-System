<?php

use App\Mail\MemberApplicationStatusMail;
use App\Models\Account;
use App\Models\Contact;
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

    $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
    $this->secretary = User::where('email', 'secretary@example.com')->firstOrFail();
    $this->member = User::where('email', 'arjay.ramos@example.com')->firstOrFail();
});

function memberApplicationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Maria',
        'middle_name' => '',
        'last_name' => 'Reyes',
        'gender' => 'Female',
        'birthdate' => '1992-03-14',
        'age' => 30,
        'phone' => '09218887766',
        'email' => 'maria.reyes@example.com',
        'region' => 'CALABARZON',
        'province' => 'Laguna',
        'city' => 'Calamba',
        'barangay' => 'Real',
        'street' => '45 Riverside St.',
        'zip_code' => '4027',
        'occupation' => 'Nurse',
        'monthly_income' => '30001-50000',
        'membership_type' => 'Regular',
        'password' => 'Password123',
        'password_confirmation' => 'Password123',
        'terms' => '1',
        'cf-turnstile-response' => 'test-token',
    ], $overrides);
}

function createPendingApplicant(string $email): User
{
    test()->post('/register', memberApplicationPayload(['email' => $email]));

    return User::where('email', $email)->firstOrFail();
}

test('a pending applicant cannot log in until approved', function () {
    $applicant = createPendingApplicant('blocked.login@example.com');

    $response = $this->postJson('/login', [
        'email' => $applicant->email,
        'password' => 'Password123',
    ]);

    $response->assertStatus(403)->assertJsonPath('message', 'Your account is not active.');
});

test('secretary can approve a pending application', function () {
    Mail::fake();

    $applicant = createPendingApplicant('secretary.approves@example.com');
    $application = MemberApplication::where('user_id', $applicant->id)->firstOrFail();

    $response = $this->actingAs($this->secretary)->postJson("/api/members/{$applicant->id}/approve");

    $response->assertOk()->assertJsonPath('message', 'Application approved and activation email sent.');

    expect($applicant->fresh()->account_status)->toBe('active')
        ->and(Account::where('user_id', $applicant->id)->where('account_type', 'savings')->where('balance', 500)->exists())->toBeTrue()
        ->and(Account::where('user_id', $applicant->id)->where('account_type', 'share-capital')->where('balance', 500)->exists())->toBeTrue();

    $application->refresh();
    expect($application->status)->toBe('approved');

    $notification = $applicant->notifications()->where('type', 'member_approved')->first();
    expect($notification)->not->toBeNull();

    Mail::assertSent(MemberApplicationStatusMail::class, fn ($mail) => $mail->status === 'approved' && $mail->activationUrl !== null);
});

test('approving again reports the member is already active', function () {
    $applicant = createPendingApplicant('double.approve@example.com');

    $this->actingAs($this->secretary)->postJson("/api/members/{$applicant->id}/approve")->assertOk();

    $this->actingAs($this->secretary)->postJson("/api/members/{$applicant->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Member is already active.');
});

test('a regular member cannot approve applications', function () {
    $applicant = createPendingApplicant('no.permission.approve@example.com');

    $this->actingAs($this->member)->postJson("/api/members/{$applicant->id}/approve")
        ->assertStatus(403);
});

test('secretary can decline a pending application and release the email', function () {
    Mail::fake();

    $applicant = createPendingApplicant('decline.me@example.com');
    $originalEmail = $applicant->email;
    $application = MemberApplication::where('user_id', $applicant->id)->firstOrFail();

    $response = $this->actingAs($this->secretary)->postJson("/api/members/{$applicant->id}/decline", [
        'reason' => 'Incomplete submitted documents.',
    ]);

    $response->assertOk()->assertJsonPath('message', 'Application declined and applicant notified.');

    $application->refresh();
    expect($application->status)->toBe('declined')
        ->and($application->decline_reason)->toBe('Incomplete submitted documents.');

    $archived = $applicant->fresh();
    expect($archived->trashed())->toBeTrue()
        ->and($archived->email)->toBe('archived_'.$applicant->id.'_'.$originalEmail)
        ->and($archived->user_number)->toStartWith('ARCH-');

    $contact = $archived->personalData?->contact;
    expect($contact->email)->toBe('archived_'.$applicant->id.'_'.$originalEmail);

    expect(User::where('email', $originalEmail)->count())->toBe(0);
    expect(Contact::where('email', $originalEmail)->count())->toBe(0);

    Mail::assertSent(MemberApplicationStatusMail::class, fn ($mail) => $mail->status === 'declined' && $mail->applicantName === 'Maria Reyes');
});

test('the same email can re-register after a declined application', function () {
    $applicant = createPendingApplicant('rereregister@example.com');

    $this->actingAs($this->secretary)->postJson("/api/members/{$applicant->id}/decline", ['reason' => 'Duplicated record.'])->assertOk();

    $response = $this->post('/register', memberApplicationPayload([
        'email' => 'rereregister@example.com',
    ]));

    $response->assertRedirect(route('login_page', absolute: false));

    $reApplicant = User::withTrashed()->where('email', 'rereregister@example.com')->first();
    expect($reApplicant)->not->toBeNull()
        ->and($reApplicant->trashed())->toBeFalse()
        ->and(MemberApplication::where('user_id', $reApplicant->id)->where('status', 'pending')->exists())->toBeTrue();
});

test('decline requires a valid reviewer', function () {
    $applicant = createPendingApplicant('decline.forbidden@example.com');

    $this->actingAs($this->member)->postJson("/api/members/{$applicant->id}/decline", ['reason' => 'Nope'])
        ->assertStatus(403);
});
