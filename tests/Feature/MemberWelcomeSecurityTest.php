<?php

use App\Jobs\SendEmailJob;
use App\Mail\MemberPasswordSetupMail;
use App\Mail\MemberWelcomeMail;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);
});

test('MemberWelcomeMail rendered content does not contain plaintext password', function () {
    $mail = new MemberWelcomeMail('Juan Dela Cruz', 'juan@example.com', 'http://localhost/login');
    $rendered = $mail->render();

    expect($rendered)->toContain('Juan Dela Cruz')
        ->and($rendered)->toContain('juan@example.com')
        ->and($rendered)->toContain('http://localhost/login')
        ->and($rendered)->not->toContain('Password123')
        ->and($rendered)->not->toContain('Your Login Credentials:');

    // Reflection check: MemberWelcomeMail must not have a plainPassword property
    $reflection = new ReflectionClass(MemberWelcomeMail::class);
    expect($reflection->hasProperty('plainPassword'))->toBeFalse();
});

test('chairman creates member with a password setup invitation', function () {
    Bus::fake();
    Config::set('mail.default', 'smtp');

    $chairman = User::factory()->create(['role' => 'chairman']);
    $chairman->assignRole('chairman');

    $payload = [
        'first_name' => 'Roberto',
        'last_name' => 'Silang',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'roberto.silang@example.com',
        'phone_number' => '09181234567',
        'agreement' => true,
        'monthly_income' => 25000,
    ];

    $response = $this->actingAs($chairman)
        ->withSession(['verified_officer_email' => $payload['email']])
        ->postJson('/api/members', $payload);

    $response->assertStatus(201);

    Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) {
        if ($job->toEmail !== 'roberto.silang@example.com') {
            return false;
        }

        if (! ($job->mailable instanceof MemberPasswordSetupMail)) {
            return false;
        }

        $rendered = $job->mailable->render();

        return str_contains($rendered, 'Set Your Password')
            && str_contains($job->mailable->setupUrl, 'setup=1');
    });

    // The member must choose a password before signing in.
    $user = User::where('email', 'roberto.silang@example.com')->firstOrFail();
    expect($user->must_set_password)->toBeTrue();
});
