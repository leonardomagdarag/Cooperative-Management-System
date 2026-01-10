<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'content' => fake()->paragraphs(3, true),
            'created_by' => \App\Models\User::whereIn('role', ['admin', 'manager', 'secretary'])->inRandomOrder()->first()?->id,
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
