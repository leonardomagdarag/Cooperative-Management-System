<?php

use App\Jobs\SendEmailJob;
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

test('admin creating member does not leak plaintext password in welcome email job', function () {
    Bus::fake();
    Config::set('mail.default', 'smtp');

    $admin = User::where('email', 'admin@example.com')->firstOrFail();

    $payload = [
        'first_name' => 'Roberto',
        'last_name' => 'Silang',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'civil_status' => 'single',
        'email' => 'roberto.silang@example.com',
        'phone_number' => '09181234567',
        'password' => 'SuperSecret123!',
        'password_confirmation' => 'SuperSecret123!',
        'monthly_income' => 25000,
    ];

    $response = $this->actingAs($admin)
        ->withSession(['verified_officer_email' => $payload['email']])
        ->postJson('/api/members', $payload);

    $response->assertStatus(201);

    Bus::assertDispatched(SendEmailJob::class, function (SendEmailJob $job) use ($payload) {
        if ($job->toEmail !== 'roberto.silang@example.com') {
            return false;
        }

        if (! ($job->mailable instanceof MemberWelcomeMail)) {
            return false;
        }

        $rendered = $job->mailable->render();

        // Plaintext password MUST NOT appear anywhere in the rendered mail
        return ! str_contains($rendered, $payload['password'])
            && ! property_exists($job->mailable, 'plainPassword');
    });

    // Check user in database: password must only be stored hashed
    $user = User::where('email', 'roberto.silang@example.com')->firstOrFail();
    expect($user->password)->not->toBe($payload['password'])
        ->and(password_verify($payload['password'], $user->password))->toBeTrue();
});
