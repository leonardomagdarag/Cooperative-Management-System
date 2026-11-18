<?php

use App\Models\User;
use Database\Seeders\ChairmanSeeder;
use Database\Seeders\QaChairmanSeeder;
use Database\Seeders\SpatieRolePermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('seeded accounts use the requested role passwords and the shared member password', function () {
    $this->seed([SpatieRolePermissionSeeder::class, UserSeeder::class, ChairmanSeeder::class]);

    $passwords = [
        'developer' => '135#Developer',
        'admin' => '135#Admin',
        'manager' => '135#Manager',
        'accountant' => '135#Accountant',
        'chairman' => '135#Chairman',
        'member' => '135#Members',
    ];

    foreach (User::all() as $user) {
        expect(Hash::check($passwords[$user->primary_role], $user->password))->toBeTrue()
            ->and(Hash::check('password', $user->password))->toBeFalse();
    }

    foreach (['admin', 'member', 'chairman'] as $role) {
        User::role($role)->firstOrFail()->update(['password' => 'PersonallyChanged456!']);
    }

    $this->seed([UserSeeder::class, ChairmanSeeder::class]);

    foreach (['admin', 'member', 'chairman'] as $role) {
        expect(Hash::check('PersonallyChanged456!', User::role($role)->firstOrFail()->password))->toBeTrue();
    }
});

test('the QA chairman fixture uses the same default password', function () {
    $this->seed([SpatieRolePermissionSeeder::class, QaChairmanSeeder::class]);

    expect(Hash::check('135#Chairman', User::role('chairman')->firstOrFail()->password))->toBeTrue();
});

test('seeded members use first.lastname@gmail.com logins and forward OTP delivery to leo.baluyot039+', function () {
    $this->seed([SpatieRolePermissionSeeder::class, UserSeeder::class]);

    $arjay = User::where('user_number', 'USR000007')->firstOrFail();
    expect($arjay->email)->toBe('arjay.ramos@gmail.com')
        ->and($arjay->deliveryEmail())->toBe('leo.baluyot039+arjay.ramos@gmail.com');

    $leo = User::where('email', 'leo.baluyot.magdarag039@gmail.com')->firstOrFail();
    expect($leo->email)->toBe('leo.baluyot.magdarag039@gmail.com')
        ->and($leo->deliveryEmail())->toBe('leo.baluyot.magdarag039@gmail.com');
});
