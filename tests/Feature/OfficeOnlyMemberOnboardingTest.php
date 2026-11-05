<?php

use App\Jobs\SendEmailJob;
use App\Mail\MemberPasswordSetupMail;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->admin = User::where('email', 'leo.baluyot039+manager@gmail.com')->firstOrFail();
    $this->member = User::where('email', 'leo.baluyot039+arjay.ramos@gmail.com')->firstOrFail();
    $this->branch = Branch::where('code', 'BR000001')->firstOrFail();
    expect($this->admin->can('members.create'))->toBeTrue();
});

function officeMemberPayload(string $email): array
{
    return [
        'first_name' => 'Office',
        'last_name' => 'Applicant',
        'birth_date' => '1995-05-10',
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => $email,
        'phone_number' => '09191234567',
        'branch_id' => Branch::where('code', 'BR000001')->firstOrFail()->id,
        'role' => 'member',
        'agreement' => true,
    ];
}

test('public registration page and submission are unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->postJson('/register', ['email' => 'uninvited@example.com'])->assertNotFound();
    expect(User::where('email', 'uninvited@example.com')->exists())->toBeFalse();
});

test('staff creates a member without setting a password and the member redeems the invitation', function () {
    Queue::fake();
    $email = 'office.applicant@example.com';

    $this->actingAs($this->admin)
        ->withSession(['verified_officer_email' => $email])
        ->postJson('/api/members', officeMemberPayload($email))
        ->assertCreated()
        ->assertJsonPath('must_set_password', true);

    $created = User::where('email', $email)->firstOrFail();
    expect($created->must_set_password)->toBeTrue()
        ->and(Hash::check('Password123', $created->password))->toBeFalse()
        ->and((float) $created->accounts()->where('account_type', 'savings')->firstOrFail()->balance)->toBe(0.0);

    $setupUrl = null;
    Queue::assertPushed(SendEmailJob::class, function ($job) use (&$setupUrl) {
        if (! $job->mailable instanceof MemberPasswordSetupMail) {
            return false;
        }
        $setupUrl = $job->mailable->setupUrl;

        return str_contains($job->mailable->render(), 'Set Your Password');
    });
    parse_str((string) parse_url($setupUrl, PHP_URL_QUERY), $query);
    expect($query['email'] ?? null)->toBe($email)
        ->and($query['setup'] ?? null)->toBe('1');

    $this->postJson('/reset-password', [
        'email' => $email,
        'token' => $query['token'],
        'password' => 'NewPassword123',
        'password_confirmation' => 'NewPassword123',
    ])->assertOk();

    $created->refresh();
    expect($created->must_set_password)->toBeFalse()
        ->and($created->email_verified_at)->not->toBeNull()
        ->and(Hash::check('NewPassword123', $created->password))->toBeTrue();

    $this->postJson('/reset-password', [
        'email' => $email,
        'token' => $query['token'],
        'password' => 'AnotherPassword123',
        'password_confirmation' => 'AnotherPassword123',
    ])->assertUnprocessable();
});

test('officers cannot supply a member password and pending access blocks sign in', function () {
    $email = 'office.secure@example.com';
    $payload = officeMemberPayload($email) + ['password' => 'OfficerPassword123', 'password_confirmation' => 'OfficerPassword123'];

    $this->actingAs($this->admin)->withSession(['verified_officer_email' => $email])
        ->postJson('/api/members', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->member->forceFill(['must_set_password' => true, 'password' => Hash::make('KnownPassword123')])->save();
    auth()->logout();

    $this->postJson('/api/login', ['email' => $this->member->email, 'password' => 'KnownPassword123'])
        ->assertForbidden();
});

test('authorized staff can resend a one-time invitation only to a pending member in their branch', function () {
    Queue::fake();
    $this->member->forceFill(['must_set_password' => true])->save();
    $url = null;

    $this->actingAs($this->admin)
        ->postJson('/api/members/'.$this->member->id.'/resend-invitation')
        ->assertOk();
    Queue::assertPushed(SendEmailJob::class, function ($job) use (&$url) {
        if (! $job->mailable instanceof MemberPasswordSetupMail) {
            return false;
        }
        $url = $job->mailable->setupUrl;

        return true;
    });
    expect($url)->toContain('/reset-password?');

    $this->actingAs($this->member)
        ->postJson('/api/members/'.$this->member->id.'/resend-invitation')
        ->assertForbidden();

    $this->member->forceFill(['must_set_password' => false])->save();
    $this->actingAs($this->admin)
        ->postJson('/api/members/'.$this->member->id.'/resend-invitation')
        ->assertStatus(409);
});

test('changing a pending members email requires verification and invalidates the old invitation', function () {
    Queue::fake();
    $this->member->forceFill(['must_set_password' => true])->save();
    $oldEmail = $this->member->email;
    $oldToken = Password::broker()->createToken($this->member);
    $newEmail = 'corrected.member@example.com';
    $payload = [
        'first_name' => $this->member->personalData->first_name,
        'last_name' => $this->member->personalData->last_name,
        'email' => $newEmail,
    ];

    $this->actingAs($this->admin)->putJson('/api/members/'.$this->member->id, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    expect($this->member->fresh()->email)->toBe($oldEmail);

    $this->actingAs($this->admin)->withSession(['verified_officer_email' => $newEmail])
        ->putJson('/api/members/'.$this->member->id, $payload)->assertOk();
    $this->member->refresh();
    expect($this->member->email)->toBe($newEmail)
        ->and($this->member->personalData->contact->email_verified_at)->not->toBeNull();
    $this->postJson('/reset-password', [
        'email' => $oldEmail,
        'token' => $oldToken,
        'password' => 'AnotherPassword123',
        'password_confirmation' => 'AnotherPassword123',
    ])->assertUnprocessable();
    Queue::assertPushed(SendEmailJob::class, fn ($job) => $job->mailable instanceof MemberPasswordSetupMail && $job->toEmail === $newEmail
    );
});
