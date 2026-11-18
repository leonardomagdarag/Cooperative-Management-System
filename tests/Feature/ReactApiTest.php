<?php

use App\Mail\SecurityAlert;
use App\Models\Announcement;
use App\Models\LoginHandoff;
use App\Models\User;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Mail;

test('react api login establishes an authenticated session', function () {
    $user = User::factory()->create([
        'email' => 'react-api@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    $this->get('/sanctum/csrf-cookie')->assertNoContent();

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('user.email', $user->email);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

test('a sign-in on a different session waits for approval without replacing the active session', function () {
    $user = User::factory()->create([
        'email' => 'pending-handoff@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);
    $user->forceFill(['current_session_id' => 'already-active-session'])->save();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_approval', true);

    $handoff = LoginHandoff::query()->where('user_id', $user->id)->firstOrFail();

    expect($handoff->status)->toBe('pending')
        ->and($handoff->current_session_id)->toBe('already-active-session')
        ->and($handoff->pending_session_id)->not->toBe('already-active-session')
        ->and($user->fresh()->current_session_id)->toBe('already-active-session');
});

test('new member device login requires otp and blocks protected api access', function () {
    $user = User::factory()->create([
        'email' => 'new-device@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true)
        ->assertJsonPath('redirect', route('otp.challenge.show'));

    $this->getJson('/api/user')
        ->assertStatus(423)
        ->assertJsonPath('redirect', route('otp.challenge.show'));
});

test('login OTP bypass lets every user skip new-device verification when enabled', function () {
    Mail::fake();
    config(['auth.login_otp_bypass' => true]);
    $user = User::factory()->create([
        'email' => 'otp-bypass-member@example.com',
        'password' => 'password',
        'role' => 'member',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonMissingPath('requires_otp');

    Mail::assertSent(SecurityAlert::class, fn (SecurityAlert $mail) => $mail->alertType === 'new_login'
        && $mail->data['otp_status'] === 'bypassed'
        && $mail->data['is_new_device'] === true
        && str_contains($mail->render(), 'New-device OTP was skipped because the testing bypass is enabled.')
    );
});

test('new-device login OTP is required for officer accounts when bypass is disabled', function () {
    config(['auth.login_otp_bypass' => false]);
    $user = User::factory()->create([
        'email' => 'otp-officer@example.com',
        'password' => 'password',
        'role' => 'admin',
    ]);

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('requires_otp', true);
});

test('public announcement api returns json visibility records', function () {
    $creator = User::factory()->create();

    Announcement::factory()->create([
        'title' => 'Public API Announcement',
        'content' => 'This should be visible from the React news page.',
        'type' => Announcement::TYPE_ANNOUNCEMENT,
        'visibility' => [Announcement::VISIBILITY_PUBLIC],
        'created_by' => $creator->id,
    ]);

    $this->getJson('/api/announcements/public')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Public API Announcement')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'content', 'type', 'image_url', 'created_at', 'creator_name'],
            ],
            'pagination',
        ]);
});

test('public news and event feeds each paginate their own newest visible entries', function () {
    $creator = User::factory()->create();
    $public = Announcement::factory()->for($creator, 'creator')->state([
        'visibility' => [Announcement::VISIBILITY_EVERYONE],
        'created_at' => now()->subDay(),
    ]);

    $news = [];
    $events = [];
    foreach (range(1, 7) as $index) {
        $news[] = $public->create([
            'type' => $index % 2 ? Announcement::TYPE_NEWS : Announcement::TYPE_ANNOUNCEMENT,
        ])->id;
        $events[] = $public->create([
            'type' => Announcement::TYPE_EVENT,
            'event_date' => $index === 6 ? null : today(),
            'event_time' => '09:00',
            'event_location' => 'Main Office',
        ])->id;
    }

    $public->create(['type' => Announcement::TYPE_EVENT, 'visibility' => [Announcement::VISIBILITY_MEMBERS]]);
    $public->create(['type' => Announcement::TYPE_NEWS, 'visibility' => [Announcement::VISIBILITY_OFFICERS]]);

    $this->getJson('/api/announcements/public?section=news&per_page=6')
        ->assertOk()
        ->assertJsonPath('data.*.id', array_reverse(array_slice($news, 1)))
        ->assertJsonPath('pagination.total', 7);

    $this->getJson('/api/announcements/public?section=events&per_page=6')
        ->assertOk()
        ->assertJsonPath('data.*.id', array_reverse(array_slice($events, 1)))
        ->assertJsonPath('data.0.event_date', today()->toDateString())
        ->assertJsonPath('data.0.event_time', '09:00')
        ->assertJsonPath('data.0.event_location', 'Main Office')
        ->assertJsonPath('data.0.is_upcoming', true)
        ->assertJsonPath('data.1.event_date', null)
        ->assertJsonPath('pagination.total', 7);

    $this->getJson('/api/announcements/public?section=events&per_page=6&page=2')
        ->assertOk()
        ->assertJsonPath('data.*.id', [$events[0]]);
});

test('seeded admin can access protected react api endpoints', function () {
    $this->seed(SpatieRolePermissionSeeder::class);
    $this->seed(UserSeeder::class);

    $admin = User::where('email', 'leo.baluyot039+admin@gmail.com')->firstOrFail();

    $this->actingAs($admin)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', 'leo.baluyot039+admin@gmail.com')
        ->assertJsonPath('primary_role', 'admin');

    foreach ([
        '/api/dashboard',
        '/api/members',
        '/api/services',
        '/api/loan-products',
        '/api/ledger',
        '/api/announcements',
        '/api/logs',
        '/api/roles',
    ] as $endpoint) {
        $this->actingAs($admin)
            ->getJson($endpoint)
            ->assertOk();
    }
});
