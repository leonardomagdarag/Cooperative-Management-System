<?php

use App\Mail\MemberApplicationStatusMail;
use App\Models\Account;
use App\Models\Contact;
use App\Models\MemberApplication;
use App\Models\User;
use App\Services\TurnstileService;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\mock;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    mock(TurnstileService::class)->shouldReceive('verify')->andReturn(true);

    $this->admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();
    $this->accountant = User::where('email', 'leo.baluyot039+accountant@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
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
    // Historical applications remain reviewable after public registration closes.
    $user = User::factory()->unverified()->create([
        'email' => $email,
        'password' => Hash::make('Password123'),
        'account_status' => 'inactive',
        'branch_id' => test()->accountant->branch_id,
    ]);
    $user->assignRole('member');
    $user->personalData->update(['first_name' => 'Maria', 'last_name' => 'Reyes']);
    $user->personalData->contact->update(['email' => $email]);
    MemberApplication::create([
        'user_id' => $user->id,
        'branch_id' => $user->branch_id,
        'original_email' => $email,
        'first_name' => 'Maria',
        'last_name' => 'Reyes',
        'phone' => '09218887766',
        'membership_type' => 'Regular',
        'status' => 'pending',
    ]);

    return $user;
}

test('a pending applicant cannot log in until approved', function () {
    $applicant = createPendingApplicant('blocked.login@example.com');

    $response = $this->postJson('/login', [
        'email' => $applicant->email,
        'password' => 'Password123',
    ]);

    $response->assertStatus(403)->assertJsonPath('message', 'Your account is not active.');
});

test('accountant can approve a pending application', function () {
    Mail::fake();

    $applicant = createPendingApplicant('accountant.approves@example.com');
    $application = MemberApplication::where('user_id', $applicant->id)->firstOrFail();

    $response = $this->actingAs($this->accountant)->postJson("/api/members/{$applicant->id}/approve");

    $response->assertOk()->assertJsonPath('message', 'Application approved and activation email sent.');

    expect($applicant->fresh()->account_status)->toBe('active')
        ->and(Account::where('user_id', $applicant->id)->where('account_type', 'savings')->where('balance', 0)->exists())->toBeTrue()
        ->and(Account::where('user_id', $applicant->id)->where('account_type', 'share-capital')->where('balance', 0)->exists())->toBeTrue();

    $application->refresh();
    expect($application->status)->toBe('approved');

    $notification = $applicant->notifications()->where('type', 'member_approved')->first();
    expect($notification)->not->toBeNull();

    Mail::assertSent(MemberApplicationStatusMail::class, fn ($mail) => $mail->status === 'approved' && $mail->activationUrl !== null);
});

test('approving again reports the member is already active', function () {
    $applicant = createPendingApplicant('double.approve@example.com');

    $this->actingAs($this->accountant)->postJson("/api/members/{$applicant->id}/approve")->assertOk();

    $this->actingAs($this->accountant)->postJson("/api/members/{$applicant->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Member is already active.');
});

test('a regular member cannot approve applications', function () {
    $applicant = createPendingApplicant('no.permission.approve@example.com');

    $this->actingAs($this->member)->postJson("/api/members/{$applicant->id}/approve")
        ->assertStatus(403);
});

test('accountant can decline a pending application and release the email', function () {
    Mail::fake();

    $applicant = createPendingApplicant('decline.me@example.com');
    $originalEmail = $applicant->email;
    $application = MemberApplication::where('user_id', $applicant->id)->firstOrFail();

    $response = $this->actingAs($this->accountant)->postJson("/api/members/{$applicant->id}/decline", [
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

test('declined applicant cannot self-register again', function () {
    $applicant = createPendingApplicant('rereregister@example.com');

    $this->actingAs($this->accountant)->postJson("/api/members/{$applicant->id}/decline", ['reason' => 'Duplicated record.'])->assertOk();

    $this->post('/register', memberApplicationPayload(['email' => 'rereregister@example.com']))->assertNotFound();
    expect(User::where('email', 'rereregister@example.com')->exists())->toBeFalse();
});

test('decline requires a valid reviewer', function () {
    $applicant = createPendingApplicant('decline.forbidden@example.com');

    $this->actingAs($this->member)->postJson("/api/members/{$applicant->id}/decline", ['reason' => 'Nope'])
        ->assertStatus(403);
});
