<?php

namespace Database\Seeders;

use App\Models\LoanProduct;
use Illuminate\Database\Seeder;

class LoanProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'code' => 'group',
                'name' => 'Group Loan',
                'description' => 'Requires 5 members total (4 co-makers) and cooperative group accountability.',
                'base_type' => 'group',
                'icon' => 'users',
                'interest_rate' => 1.50,
                'max_amount' => 5000,
                'max_term' => 6,
                'requires_no_existing_loan' => true,
                'minimum_share_capital' => 0,
                'minimum_membership_years' => 1,
                'required_co_makers' => 4,
                'requires_interview' => false,
                'requires_special_eligibility' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'business',
                'name' => 'Business Loan',
                'description' => 'For business growth and livelihood support with officer interview requirement.',
                'base_type' => 'business',
                'icon' => 'briefcase',
                'interest_rate' => 3.00,
                'max_amount' => 50000,
                'max_term' => 12,
                'requires_no_existing_loan' => false,
                'minimum_share_capital' => 4000,
                'minimum_membership_years' => 1,
                'required_co_makers' => 0,
                'requires_interview' => true,
                'requires_special_eligibility' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'special',
                'name' => 'Special Loan',
                'description' => 'Emergency or special-case loan requiring manager-tagged eligibility.',
                'base_type' => 'special',
                'icon' => 'star',
                'interest_rate' => 2.00,
                'max_amount' => 30000,
                'max_term' => 12,
                'requires_no_existing_loan' => false,
                'minimum_share_capital' => 0,
                'minimum_membership_years' => 0,
                'required_co_makers' => 0,
                'requires_interview' => false,
                'requires_special_eligibility' => true,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($products as $product) {
            LoanProduct::updateOrCreate([
                'code' => $product['code'],
            ], $product);
        }
    }
}
