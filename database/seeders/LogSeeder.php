<?php

namespace Database\Seeders;

use App\Models\Log;
use App\Models\User;
use Illuminate\Database\Seeder;

class LogSeeder extends Seeder
{
    public function run(): void
    {
        // Get all users
        $users = User::all();

        // Create 2-5 logs per user
        foreach ($users as $user) {
            Log::factory(rand(2, 5))->create([
                'user_id' => $user->id,
            ]);
        }

        // Create some system logs without user_id
        Log::factory(10)->create([
            'user_id' => null,
            'activity' => 'System Event',
        ]);
    }
}
