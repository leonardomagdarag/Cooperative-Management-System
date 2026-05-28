<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

test('users can log in with normalized email when a legacy record stores extra whitespace or casing', function () {
    Role::findOrCreate('manager', 'web');

    DB::table('users')->insert([
        'user_number' => 'USR999999',
        'email' => '  Manager.Legacy@Example.com  ',
        'password' => Hash::make('Password123'),
        'role' => 'manager',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::query()
        ->where('user_number', 'USR999999')
        ->firstOrFail();

    $user->assignRole('manager');

    $response = $this->post('/login', [
        'email' => 'manager.legacy@example.com',
        'password' => 'Password123',
    ]);

    $this->assertAuthenticatedAs($user->fresh());
    $response->assertRedirect(route('dashboard', absolute: false));

    expect($user->fresh()->email)->toBe('manager.legacy@example.com');
});
