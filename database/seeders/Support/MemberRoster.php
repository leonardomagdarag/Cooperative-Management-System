<?php

namespace Database\Seeders\Support;

use Carbon\Carbon;

/**
 * Canonical roster of the seeded membership.
 *
 * Every seeder that needs to know *who* a member is reads from here, so the
 * generated sample data cannot contradict itself:
 *
 *  - the join date drives the `minimum_membership_years` eligibility gate that
 *    ServiceController::getLoanEligibilityError() enforces;
 *  - the share capital declared here is exactly what AccountSeeder subscribes on
 *    the member's behalf, so LoanSeeder sees a real balance when it checks the
 *    product's `minimum_share_capital`;
 *  - the monthly income drives both the savings history and how large a loan the
 *    member can realistically service;
 *  - the loan plan is declarative. LoanSeeder materialises the lifecycle and
 *    TransactionSeeder replays the matching cash flows, so payment amounts come
 *    from Loan::paymentSchedule() instead of being invented twice.
 *
 * The cooperative trades in Calauan, Laguna, so every address, employer and
 * livelihood below is drawn from that farming municipality.
 */
final class MemberRoster
{
    /**
     * Barangays of Calauan, Laguna (the cooperative's home municipality).
     *
     * @var list<string>
     */
    public const CALAUAN_BARANGAYS = [
        'Balayhangin',
        'Bangyas',
        'Dayap',
        'Hanggan',
        'Imok',
        'Kanluran',
        'Lamot 1',
        'Lamot 2',
        'Limao',
        'Mabacan',
        'Masiit',
        'Paliparan',
        'Perez',
        'Prinza',
        'San Isidro',
        'Santo Tomas',
        'Silangan',
    ];

    /**
     * PSGC codes for the barangays above, keyed by barangay name.
     *
     * These are the real codes loaded into the `barangays` table by
     * PSGCSeeder, so a seeded member's barangay_id resolves against the same
     * reference data the app uses for its address dropdowns.
     *
     * @var array<string, string>
     */
    public const CALAUAN_BARANGAY_CODES = [
        'Balayhangin' => '0403406001',
        'Bangyas' => '0403406002',
        'Dayap' => '0403406003',
        'Hanggan' => '0403406004',
        'Imok' => '0403406005',
        'Lamot 1' => '0403406006',
        'Lamot 2' => '0403406007',
        'Limao' => '0403406008',
        'Mabacan' => '0403406009',
        'Masiit' => '0403406010',
        'Paliparan' => '0403406011',
        'Perez' => '0403406012',
        'Kanluran' => '0403406013',
        'Silangan' => '0403406014',
        'Prinza' => '0403406015',
        'San Isidro' => '0403406016',
        'Santo Tomas' => '0403406017',
    ];

    /** The cooperative's municipality: Calauan, Laguna, Region IV-A (CALABARZON). */
    public const MUNICIPALITY = [
        'country' => 'Philippines',
        'region' => 'CALABARZON',
        'province' => 'Laguna',
        'city_municipality' => 'Calauan',
        'zip_code' => '4277',
    ];

    /**
     * Resolve a Calauan barangay name to its PSGC code.
     */
    public static function barangayCode(string $barangay): ?string
    {
        return self::CALAUAN_BARANGAY_CODES[$barangay] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            [
                'user_number' => 'USR000007',
                'first_name' => 'Arjay',
                'last_name' => 'Ramos',
                'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 91, 'day' => 14],
                'birth_date' => '1978-05-21',
                'gender' => 'male',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => 'High School',
                'barangay' => 'Mabacan',
                'street' => 'Purok 3, Sitio Maligaya',
                'phone' => '09178451203',
                'employment' => [
                    'type' => 'self-employed',
                    'company' => null,
                    'position' => 'Rice Trader',
                    'monthly_income' => 38000,
                ],
                'share_capital' => ['opening' => 2500, 'annual_top_up' => 2000],
                'savings' => ['target_balance' => 78000, 'monthly_spend' => 2600],
                'special_loan_eligible' => false,
                'loan' => [
                    'product' => 'business',
                    'amount' => 30000,
                    'term_months' => 12,
                    'applied_ago_months' => 16,
                    'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 470,
                    'review_notes' => 'Endorsed. Rice trading account verified, no existing past-due account.',
                    'approved_ago_months' => 15,
                    'disbursed_ago_months' => 15,
                    'missed_payments' => 4,
                ],
            ],
            [
                'user_number' => 'USR000008',
                'first_name' => 'Cris David',
                'last_name' => 'Jarabejo',
                'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 99, 'day' => 9],
                'birth_date' => '1968-11-02',
                'gender' => 'male',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => 'Vocational',
                'barangay' => 'San Isidro',
                'street' => 'Purok 1, Sitio Bato',
                'phone' => '09273982014',
                'employment' => [
                    'type' => 'self-employed',
                    'company' => null,
                    'position' => 'Sari-Sari Store Owner',
                    'monthly_income' => 32000,
                ],
                'share_capital' => ['opening' => 2000, 'annual_top_up' => 2000],
                'savings' => ['target_balance' => 110000, 'monthly_spend' => 2800],
                'special_loan_eligible' => false,
                'loan' => [
                    'product' => 'business',
                    'amount' => 25000,
                    'term_months' => 12,
                    'applied_ago_months' => 31,
                    'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 930,
                    'review_notes' => 'Endorsed. Long-standing member, store in good standing.',
                    'approved_ago_months' => 30,
                    'disbursed_ago_months' => 30,
                    'missed_payments' => 0,
                    'closed' => true,
                ],
            ],
            [
                'user_number' => 'USR000009',
                'first_name' => 'Glendo',
                'last_name' => 'Camarines',
                'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 88, 'day' => 21],
                'birth_date' => '1981-02-17',
                'gender' => 'male',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => 'High School',
                'barangay' => 'Balayhangin',
                'street' => 'Purok 5, Sitio Ilaya',
                'phone' => '09184672035',
                'employment' => [
                    'type' => 'self-employed',
                    'company' => null,
                    'position' => 'Poultry & Vegetable Trader',
                    'monthly_income' => 41000,
                ],
                'share_capital' => ['opening' => 2500, 'annual_top_up' => 1000],
                'savings' => ['target_balance' => 68000, 'monthly_spend' => 3100],
                'special_loan_eligible' => true,
                'loan' => [
                    'product' => 'special',
                    'amount' => 15000,
                    'term_months' => 12,
                    'applied_ago_days' => 3,
                    'review_status' => null,
                    'purpose' => 'Capital for poultry feed and layering replacement ahead of the rainy season.',
                ],
            ],
            [
                'user_number' => 'USR000010',
                'first_name' => 'Jerrelyn',
                'last_name' => 'Amper',
                'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 68, 'day' => 16],
                'birth_date' => '1986-07-30',
                'gender' => 'female',
                'civil_status' => 'married',
                'religion' => 'Christian',
                'education' => "Bachelor's Degree",
                'barangay' => 'Dayap',
                'street' => 'Purok 2, Sitio Bagong Silang',
                'phone' => '09959341207',
                'employment' => [
                    'type' => 'ofw',
                    'company' => 'Warehouse Supervisor (Saudi Arabia)',
                    'position' => 'Warehouse Supervisor',
                    'monthly_income' => 78000,
                ],
                'share_capital' => ['opening' => 3500, 'annual_top_up' => 3500],
                'savings' => ['target_balance' => 98000, 'monthly_spend' => 4200],
                'special_loan_eligible' => true,
                'loan' => [
                    'product' => 'special',
                    'amount' => 20000,
                    'term_months' => 12,
                    'applied_ago_months' => 8,
                    'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 235,
                    'review_notes' => 'Endorsed. Emergency cash needs, remittance account verified.',
                    'approved_ago_months' => 7,
                    'disbursed_ago_months' => 7,
                    'missed_payments' => 0,
                ],
            ],
            [
                'user_number' => 'USR000011',
                'first_name' => 'Jean Leslie',
                'last_name' => 'Velasco',
                'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 73, 'day' => 22],
                'birth_date' => '1990-04-08',
                'gender' => 'female',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => "Bachelor's Degree",
                'barangay' => 'Imok',
                'street' => 'Purok 4, Sitio Tubigan',
                'phone' => '09178321046',
                'employment' => [
                    'type' => 'government',
                    'company' => 'Department of Education - Calauan',
                    'position' => 'Teacher III',
                    'monthly_income' => 46000,
                ],
                'share_capital' => ['opening' => 3000, 'annual_top_up' => 3000],
                'savings' => ['target_balance' => 88000, 'monthly_spend' => 5200],
                'special_loan_eligible' => false,
                'loan' => [
                    'product' => 'business',
                    'amount' => 40000,
                    'term_months' => 12,
                    'applied_ago_days' => 20,
                    'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 12,
                    'review_notes' => 'Endorsed for final approval. Interview completed, salary account verified.',
                    'approved_ago_days' => 3,
                    'purpose' => 'School fees and capital for a small poultry project run by the household.',
                    'interview' => [
                        'scheduled_ago_days' => 15,
                        'completed_ago_days' => 8,
                        'officer_user_number' => 'USR000004',
                        'notes' => 'Applicant is a permanent government employee with a confirmed salary credit. '
                            .'Business proposal reviewed, projected cash flow covers the amortisation.',
                    ],
                ],
            ],
            [
                'user_number' => 'USR000012',
                'first_name' => 'Jeral Jean',
                'last_name' => 'Cabillon',
                'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 82, 'day' => 30],
                'birth_date' => '1983-09-12',
                'gender' => 'female',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => 'High School',
                'barangay' => 'Kanluran',
                'street' => 'Purok 6, Sitio Malusak',
                'phone' => '09269452031',
                'employment' => [
                    'type' => 'self-employed',
                    'company' => null,
                    'position' => 'Vegetable Farmer',
                    'monthly_income' => 29000,
                ],
                'share_capital' => ['opening' => 1500, 'annual_top_up' => 1500],
                'savings' => ['target_balance' => 42000, 'monthly_spend' => 1900],
                'special_loan_eligible' => false,
                'loan' => [
                    'product' => 'group',
                    'amount' => 5000,
                    'term_months' => 6,
                    'applied_ago_months' => 27,
                    'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 800,
                    'review_notes' => 'Endorsed. Co-makers interviewed and signed their guarantees.',
                    'approved_ago_months' => 26,
                    'disbursed_ago_months' => 26,
                    'missed_payments' => 0,
                    'closed' => true,
                    'co_maker_user_numbers' => ['USR000007', 'USR000009', 'USR000013', 'USR000014'],
                ],
            ],
            [
                'user_number' => 'USR000013',
                'first_name' => 'Leo Angelo',
                'last_name' => 'Centeno',
                'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 40, 'day' => 6],
                'birth_date' => '1993-12-01',
                'gender' => 'male',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => 'High School',
                'barangay' => 'Silangan',
                'street' => 'Purok 7, Sitio Bukal',
                'phone' => '09184562017',
                'employment' => [
                    'type' => 'private',
                    'company' => 'Dela Cruz Construction',
                    'position' => 'Construction Worker',
                    'monthly_income' => 27000,
                ],
                'share_capital' => ['opening' => 1250, 'annual_top_up' => 1250],
                'savings' => ['target_balance' => 24000, 'monthly_spend' => 1500],
                'special_loan_eligible' => false,
                'loan' => [
                    'product' => 'group',
                    'amount' => 4500,
                    'term_months' => 6,
                    'applied_ago_months' => 5,
                    'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 140,
                    'review_notes' => 'Endorsed. Group of five verified, all members in good standing.',
                    'approved_ago_months' => 4,
                    'disbursed_ago_months' => 4,
                    'missed_payments' => 1,
                    'co_maker_user_numbers' => ['USR000009', 'USR000012', 'USR000015', 'USR000016'],
                ],
            ],
            [
                'user_number' => 'USR000014',
                'first_name' => 'Alyson',
                'last_name' => 'Penano',
                'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 54, 'day' => 14],
                'birth_date' => '1988-06-25',
                'gender' => 'male',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => 'Vocational',
                'barangay' => 'Santo Tomas',
                'street' => 'Purok 8, Sitio Kawayan',
                'phone' => '09279340258',
                'employment' => [
                    'type' => 'private',
                    'company' => 'Lazada Calauan Hub',
                    'position' => 'Coconut Trader',
                    'monthly_income' => 35000,
                ],
                'share_capital' => ['opening' => 2000, 'annual_top_up' => 2000],
                'savings' => ['target_balance' => 52000, 'monthly_spend' => 2400],
                'special_loan_eligible' => false,
                'loan' => [
                    'product' => 'business',
                    'amount' => 30000,
                    'term_months' => 12,
                    'applied_ago_days' => 45,
                    'review_status' => 'review_rejected',
                    'reviewed_ago_days' => 20,
                    'review_notes' => 'Returned to applicant. Requested amount exceeds the documented buying '
                        .'capacity of the trading business. Advised to re-apply at ₱20,000 with supplier invoices.',
                    'purpose' => 'Capital to buy copra in bulk ahead of the price increase.',
                    'interview' => [
                        'scheduled_ago_days' => 32,
                        'completed_ago_days' => 20,
                        'officer_user_number' => 'USR000004',
                        'notes' => 'Trading volume and cash on hand do not support a ₱30,000 amortisation.',
                    ],
                ],
            ],
            [
                'user_number' => 'USR000015',
                'first_name' => 'Ravie',
                'last_name' => 'Premor',
                'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 3, 'day' => 18],
                'birth_date' => '1996-03-19',
                'gender' => 'male',
                'civil_status' => 'single',
                'religion' => 'Catholic',
                'education' => 'High School',
                'barangay' => 'Prinza',
                'street' => 'Purok 2, Sitio Balite',
                'phone' => '09162380914',
                'employment' => [
                    'type' => 'private',
                    'company' => null,
                    'position' => 'Farmhand',
                    'monthly_income' => 18000,
                ],
                'share_capital' => ['opening' => 1000, 'annual_top_up' => 0],
                'savings' => ['target_balance' => 4200, 'monthly_spend' => 700],
                'special_loan_eligible' => true,
                'loan' => [
                    // A member of under a year cannot reach the group or business
                    // products, so the portfolio shows a special-loan request.
                    'product' => 'special',
                    'amount' => 8000,
                    'term_months' => 6,
                    'applied_ago_days' => 10,
                    'review_status' => 'under_review',
                    'reviewed_ago_days' => 5,
                    'review_notes' => 'Under review. Verifying farm employment and guarantor details.',
                    'purpose' => 'Emergency medical assistance for a relative.',
                ],
            ],
            [
                'user_number' => 'USR000016',
                'first_name' => 'Leo',
                'mid_name' => 'Baluyot',
                'last_name' => 'Magdarag',
                'email' => 'leo.baluyot.magdarag039@gmail.com',
                'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 78, 'day' => 7],
                'birth_date' => '1995-06-15',
                'gender' => 'male',
                'civil_status' => 'married',
                'religion' => 'Catholic',
                'education' => 'Vocational',
                'barangay' => 'Masiit',
                'street' => 'Purok 9, Sitio Luntian',
                'phone' => '09761729700',
                'employment' => [
                    'type' => 'self-employed',
                    'company' => null,
                    'position' => 'Rice & Poultry Trader',
                    'monthly_income' => 52000,
                ],
                'share_capital' => ['opening' => 3000, 'annual_top_up' => 2500],
                'savings' => ['target_balance' => 84000, 'monthly_spend' => 3600],
                'special_loan_eligible' => false,
                'loan' => [
                    'product' => 'business',
                    'amount' => 45000,
                    'term_months' => 12,
                    'applied_ago_months' => 7,
                    'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 205,
                    'review_notes' => 'Endorsed. Expanding poultry and rice trading, monthly remittance verified.',
                    'approved_ago_months' => 6,
                    'disbursed_ago_months' => 6,
                    'missed_payments' => 0,
                ],
            ],
            [
                'user_number' => 'USR000017', 'first_name' => 'Maria Teresa', 'last_name' => 'Santos',
                'email' => 'maria.teresa.santos@example.com', 'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 64, 'day' => 12], 'birth_date' => '1982-08-19',
                'gender' => 'female', 'civil_status' => 'married', 'religion' => 'Catholic', 'education' => 'High School',
                'barangay' => 'Hanggan', 'street' => 'Purok 2', 'phone' => '09170000017',
                'employment' => ['type' => 'self-employed', 'company' => null, 'position' => 'Vegetable Vendor', 'monthly_income' => 26000],
                'share_capital' => ['opening' => 2000, 'annual_top_up' => 1500],
                'savings' => ['target_balance' => 36000, 'monthly_spend' => 1800],
                'special_loan_eligible' => false,
                'loan' => ['product' => 'business', 'amount' => 18000, 'term_months' => 12,
                    'applied_ago_months' => 10, 'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 290, 'approved_ago_months' => 9,
                    'disbursed_ago_months' => 9, 'missed_payments' => 0,
                    'purpose' => 'Additional stock and a market stall refrigerator.'],
            ],
            [
                'user_number' => 'USR000018', 'first_name' => 'Jose Miguel', 'last_name' => 'Dela Cruz',
                'email' => 'jose.miguel.delacruz@example.com', 'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 37, 'day' => 5], 'birth_date' => '1991-04-11',
                'gender' => 'male', 'civil_status' => 'married', 'religion' => 'Catholic', 'education' => 'Vocational',
                'barangay' => 'Lamot 1', 'street' => 'Purok 4', 'phone' => '09170000018',
                'employment' => ['type' => 'private', 'company' => 'Calauan Transport Services', 'position' => 'Delivery Driver', 'monthly_income' => 29000],
                'share_capital' => ['opening' => 2000, 'annual_top_up' => 2000],
                'savings' => ['target_balance' => 27000, 'monthly_spend' => 1400],
                'special_loan_eligible' => false,
            ],
            [
                'user_number' => 'USR000019', 'first_name' => 'Ana Liza', 'last_name' => 'Reyes',
                'email' => 'ana.liza.reyes@example.com', 'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 49, 'day' => 17], 'birth_date' => '1979-12-03',
                'gender' => 'female', 'civil_status' => 'widowed', 'religion' => 'Catholic', 'education' => "Bachelor's Degree",
                'barangay' => 'Bangyas', 'street' => 'Purok 1', 'phone' => '09170000019',
                'employment' => ['type' => 'government', 'company' => 'Calauan Municipal Health Office', 'position' => 'Administrative Aide', 'monthly_income' => 31000],
                'share_capital' => ['opening' => 2500, 'annual_top_up' => 2000],
                'savings' => ['target_balance' => 44000, 'monthly_spend' => 1900],
                'special_loan_eligible' => true,
                'loan' => ['product' => 'special', 'amount' => 12000, 'term_months' => 6,
                    'applied_ago_days' => 12, 'review_status' => 'under_review',
                    'reviewed_ago_days' => 6, 'review_notes' => 'Checking supporting medical expenses.',
                    'purpose' => 'Medical expenses for a dependent.'],
            ],
            [
                'user_number' => 'USR000020', 'first_name' => 'Rogelio', 'last_name' => 'Mendoza',
                'email' => 'rogelio.mendoza@example.com', 'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 76, 'day' => 8], 'birth_date' => '1975-03-27',
                'gender' => 'male', 'civil_status' => 'married', 'religion' => 'Catholic', 'education' => 'High School',
                'barangay' => 'Paliparan', 'street' => 'Purok 5', 'phone' => '09170000020',
                'employment' => ['type' => 'self-employed', 'company' => null, 'position' => 'Rice Farmer', 'monthly_income' => 34000],
                'share_capital' => ['opening' => 3000, 'annual_top_up' => 1500],
                'savings' => ['target_balance' => 52000, 'monthly_spend' => 2200],
                'special_loan_eligible' => false,
                'loan' => ['product' => 'business', 'amount' => 20000, 'term_months' => 12,
                    'applied_ago_months' => 26, 'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 770, 'approved_ago_months' => 25,
                    'disbursed_ago_months' => 25, 'missed_payments' => 0, 'closed' => true,
                    'purpose' => 'Rice seed and irrigation equipment.'],
            ],
            [
                'user_number' => 'USR000021', 'first_name' => 'Sheila Mae', 'last_name' => 'Garcia',
                'email' => 'sheila.mae.garcia@example.com', 'branch_code' => 'BR000001',
                'joined' => ['months_ago' => 15, 'day' => 23], 'birth_date' => '1997-09-15',
                'gender' => 'female', 'civil_status' => 'single', 'religion' => 'Christian', 'education' => "Bachelor's Degree",
                'barangay' => 'Limao', 'street' => 'Purok 3', 'phone' => '09170000021',
                'employment' => ['type' => 'private', 'company' => 'Calauan Pharmacy', 'position' => 'Pharmacy Assistant', 'monthly_income' => 24000],
                'share_capital' => ['opening' => 1500, 'annual_top_up' => 1000],
                'savings' => ['target_balance' => 10500, 'monthly_spend' => 900],
                'special_loan_eligible' => false,
            ],
            [
                'user_number' => 'USR000022', 'first_name' => 'Danilo', 'last_name' => 'Flores',
                'email' => 'danilo.flores@example.com', 'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 58, 'day' => 11], 'birth_date' => '1984-06-02',
                'gender' => 'male', 'civil_status' => 'married', 'religion' => 'Catholic', 'education' => 'Vocational',
                'barangay' => 'Perez', 'street' => 'Purok 2', 'phone' => '09170000022',
                'employment' => ['type' => 'self-employed', 'company' => null, 'position' => 'Motorcycle Repair Shop Owner', 'monthly_income' => 33000],
                'share_capital' => ['opening' => 2500, 'annual_top_up' => 1500],
                'savings' => ['target_balance' => 39000, 'monthly_spend' => 1700],
                'special_loan_eligible' => false,
                'loan' => ['product' => 'business', 'amount' => 25000, 'term_months' => 12,
                    'applied_ago_days' => 35, 'review_status' => 'endorsed_for_approval',
                    'reviewed_ago_days' => 21, 'review_notes' => 'Repair shop income and supplier quotation verified.',
                    'approved_ago_days' => 5, 'purpose' => 'Tools and spare parts for the repair shop.'],
            ],
            [
                'user_number' => 'USR000023', 'first_name' => 'Lourdes', 'last_name' => 'Bautista',
                'email' => 'lourdes.bautista@example.com', 'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 83, 'day' => 19], 'birth_date' => '1969-10-28',
                'gender' => 'female', 'civil_status' => 'married', 'religion' => 'Catholic', 'education' => 'High School',
                'barangay' => 'Lamot 2', 'street' => 'Purok 6', 'phone' => '09170000023',
                'employment' => ['type' => 'self-employed', 'company' => null, 'position' => 'Sari-Sari Store Owner', 'monthly_income' => 28000],
                'share_capital' => ['opening' => 2000, 'annual_top_up' => 1000],
                'savings' => ['target_balance' => 47000, 'monthly_spend' => 1600],
                'special_loan_eligible' => false,
            ],
            [
                'user_number' => 'USR000024', 'first_name' => 'Kevin Paul', 'last_name' => 'Navarro',
                'email' => 'kevin.paul.navarro@example.com', 'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 28, 'day' => 4], 'birth_date' => '1994-01-09',
                'gender' => 'male', 'civil_status' => 'single', 'religion' => 'Christian', 'education' => 'Vocational',
                'barangay' => 'Masiit', 'street' => 'Purok 4', 'phone' => '09170000024',
                'employment' => ['type' => 'private', 'company' => 'Laguna Agro Supply', 'position' => 'Warehouse Clerk', 'monthly_income' => 23000],
                'share_capital' => ['opening' => 1500, 'annual_top_up' => 1500],
                'savings' => ['target_balance' => 16000, 'monthly_spend' => 800],
                'special_loan_eligible' => false,
            ],
            [
                'user_number' => 'USR000025', 'first_name' => 'Rosalinda', 'last_name' => 'Aquino',
                'email' => 'rosalinda.aquino@example.com', 'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 44, 'day' => 16], 'birth_date' => '1987-02-14',
                'gender' => 'female', 'civil_status' => 'married', 'religion' => 'Catholic', 'education' => 'High School',
                'barangay' => 'Kanluran', 'street' => 'Purok 7', 'phone' => '09170000025',
                'employment' => ['type' => 'self-employed', 'company' => null, 'position' => 'Dressmaker', 'monthly_income' => 25000],
                'share_capital' => ['opening' => 2000, 'annual_top_up' => 1500],
                'savings' => ['target_balance' => 25000, 'monthly_spend' => 1200],
                'special_loan_eligible' => false,
                'loan' => ['product' => 'business', 'amount' => 15000, 'term_months' => 12,
                    'applied_ago_days' => 8, 'review_status' => null,
                    'purpose' => 'Sewing machine and fabric for uniform orders.'],
            ],
            [
                'user_number' => 'USR000026', 'first_name' => 'Mark Anthony', 'last_name' => 'Villanueva',
                'email' => 'mark.anthony.villanueva@example.com', 'branch_code' => 'BR000002',
                'joined' => ['months_ago' => 7, 'day' => 10], 'birth_date' => '1999-07-22',
                'gender' => 'male', 'civil_status' => 'single', 'religion' => 'Catholic', 'education' => 'High School',
                'barangay' => 'Silangan', 'street' => 'Purok 1', 'phone' => '09170000026',
                'employment' => ['type' => 'private', 'company' => 'Calauan Rice Mill', 'position' => 'Mill Operator', 'monthly_income' => 21000],
                'share_capital' => ['opening' => 1000, 'annual_top_up' => 0],
                'savings' => ['target_balance' => 6200, 'monthly_spend' => 700],
                'special_loan_eligible' => false,
            ],
        ];
    }

    public static function forUserNumber(string $userNumber): ?array
    {
        foreach (self::all() as $member) {
            if ($member['user_number'] === $userNumber) {
                return $member;
            }
        }

        return null;
    }

    /**
     * Resolve the declared join date into a concrete past date.
     *
     * @param  array<string, mixed>  $member
     */
    public static function joinedAt(array $member, ?Carbon $now = null): Carbon
    {
        $now ??= Carbon::now();
        $joined = $member['joined'] ?? ['months_ago' => 12, 'day' => 1];

        return $now->copy()
            ->subMonths((int) ($joined['months_ago'] ?? 12))
            ->startOfMonth()
            ->day((int) ($joined['day'] ?? 1))
            ->setTime(9, 0);
    }
}
