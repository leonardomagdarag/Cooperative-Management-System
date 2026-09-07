<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'BR000001', 'name' => 'Main', 'is_head_office' => true],
            ['code' => 'BR000002', 'name' => 'Branch', 'is_head_office' => false],
        ];

        foreach ($branches as $data) {
            Branch::query()->withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                $data
            );
        }
    }
}