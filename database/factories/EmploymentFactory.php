<?php

namespace Database\Factories;

use App\Models\Employment;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmploymentFactory extends Factory
{
    protected $model = Employment::class;

    public function definition(): array
    {
        $employmentType = fake()->randomElement([
            'full-time', 'part-time', 'self-employed', 'freelancer', 
            'government', 'private', 'ofw', 'retired', 'unemployed', 
            'student', 'homemaker'
        ]);

        $companies = [
            'ABC Corporation', 'XYZ Trading Company', 'Department of Education',
            'Department of Health', 'City Government', 'Manila Water',
            'PLDT', 'Globe Telecom', 'SM Supermalls', 'Ayala Corporation',
            'Jollibee Foods Corporation', 'San Miguel Corporation'
        ];

        $positions = [
            'Manager', 'Supervisor', 'Team Leader', 'Officer', 'Staff',
            'Analyst', 'Specialist', 'Coordinator', 'Administrator',
            'Engineer', 'Teacher', 'Accountant', 'Sales Representative'
        ];

        $industries = [
            'Retail', 'Education', 'Healthcare', 'Technology', 'Finance',
            'Manufacturing', 'Construction', 'Telecommunications', 'Food Service'
        ];

        // Adjust income based on employment type
        $income = match($employmentType) {
            'unemployed', 'student', 'homemaker' => 0,
            'retired' => fake()->numberBetween(10000, 30000),
            'self-employed', 'freelancer' => fake()->numberBetween(15000, 50000),
            'government' => fake()->numberBetween(25000, 60000),
            'ofw' => fake()->numberBetween(50000, 120000),
            default => fake()->numberBetween(20000, 80000),
        };

        return [
            'employment_type' => $employmentType,
            'company' => in_array($employmentType, ['unemployed', 'student', 'homemaker', 'retired']) 
                ? null 
                : fake()->randomElement($companies),
            'position' => in_array($employmentType, ['unemployed', 'student', 'homemaker', 'retired']) 
                ? null 
                : fake()->randomElement($positions),
            'monthly_income' => $income,
            'other_income_source' => fake()->optional(0.3)->randomElement([
                'Freelance Projects', 'Online Business', 'Rental Income', 
                'Investments', 'Part-time Work'
            ]),
            'other_income_amount' => fake()->optional(0.3)->numberBetween(5000, 25000),
        ];
    }
}
