<?php

namespace App\Support;

use App\Models\SiteContent;

class HelpContent
{
    public const SECTION = 'help';

    public static function defaults(): array
    {
        return [
            'content' => [
                'header_description' => 'Explore system guides, search frequently asked questions, or communicate with cooperative support staff.',
                'callout_title' => 'Still need assistance?',
                'callout_description' => 'Our support team and officers are ready to help fix your issue or answer questions.',
            ],
            'categories' => [
                [
                    'id' => 'membership',
                    'label' => 'Membership & Onboarding',
                    'icon' => 'fa-id-card',
                    'description' => 'Office enrollment, profile completion requirements, and account access.',
                    'faqs' => [
                        [
                            'q' => 'How do I apply for cooperative membership?',
                            'a' => 'Visit the main office or a branch office to apply for membership. An authorized officer records your details and verifies your email address. Once your account is created, you will receive an email link to set your own password.',
                            'tags' => ['apply', 'office', 'new member'],
                        ],
                        [
                            'q' => 'What profile details must I complete before transacting?',
                            'a' => 'To access financial services like loans, deposits, and withdrawals, you must complete your full profile in Settings. This includes Personal Data (civil status, birth date, birthplace), Complete Address, Contact Info, Beneficiaries, and Employment / Source of Income details.',
                            'tags' => ['profile completion', 'personal data', 'kyc'],
                        ],
                        [
                            'q' => 'How long does membership approval take?',
                            'a' => 'An officer will explain the review process at the office. After your account is created, check your email for the password setup link. Contact the office if the link expires.',
                            'tags' => ['approval timeline', 'activation email'],
                        ],
                    ],
                ],
                [
                    'id' => 'savings',
                    'label' => 'Savings & Deposits',
                    'icon' => 'fa-piggy-bank',
                    'description' => 'Managing your cooperative savings, deposit accounts, and ledger records.',
                    'faqs' => [
                        [
                            'q' => 'What savings accounts are supported in Settlers?',
                            'a' => 'Settlers Credit Cooperative offers Regular Savings and Time Deposit accounts. Members earn competitive annual dividends based on their average daily balance and share capital.',
                            'tags' => ['savings', 'deposit', 'dividend'],
                        ],
                        [
                            'q' => 'How can I deposit funds into my account?',
                            'a' => 'Deposits can be made over-the-counter at any Settlers branch office or via bank transfer. You can record your deposit under Services > Deposit, where an officer will review and credit the amount to your ledger.',
                            'tags' => ['deposit methods', 'bank transfer', 'services'],
                        ],
                        [
                            'q' => 'Where can I see my real-time savings balance and transaction history?',
                            'a' => 'You can view your available balance directly on your Member Dashboard overview. For a comprehensive chronological ledger of deposits, withdrawals, and payments, click on the History tab.',
                            'tags' => ['balance', 'history', 'ledger'],
                        ],
                    ],
                ],
                [
                    'id' => 'withdrawals',
                    'label' => 'Withdrawals & Security',
                    'icon' => 'fa-money-bill-transfer',
                    'description' => 'Requesting withdrawals, OTP verification, and release protocols.',
                    'faqs' => [
                        [
                            'q' => 'How do I submit a withdrawal request?',
                            'a' => 'Navigate to Services > Withdrawal from your dashboard. Select the source savings account, input the amount you wish to withdraw, and enter the verification PIN sent to your registered email.',
                            'tags' => ['withdraw', 'request', 'services'],
                        ],
                        [
                            'q' => 'Why is a 6-digit email OTP required for withdrawal?',
                            'a' => 'To safeguard your cooperative funds against unauthorized access, an OTP (One-Time Password) challenge is required before your request is queued for officer processing.',
                            'tags' => ['otp', 'security', 'verification'],
                        ],
                        [
                            'q' => 'How long does withdrawal processing and payout take?',
                            'a' => 'Withdrawal requests are reviewed and approved by the Accountant or Manager during business hours. Once authorized, funds can be claimed over-the-counter or disbursed to your designated bank account.',
                            'tags' => ['approval', 'payout', 'processing time'],
                        ],
                    ],
                ],
                [
                    'id' => 'loans',
                    'label' => 'Loans & Borrowing',
                    'icon' => 'fa-hand-holding-dollar',
                    'description' => 'Loan products, eligibility, documentary requirements, and approval pipeline.',
                    'faqs' => [
                        [
                            'q' => 'What are the eligibility requirements to apply for a loan?',
                            'a' => 'You must be an active member in good standing with a 100% completed profile. Some specialized loan tiers also require Special Loan Eligibility, which is evaluated and enabled by cooperative management.',
                            'tags' => ['eligibility', 'loan application', 'requirements'],
                        ],
                        [
                            'q' => 'What loan products are available?',
                            'a' => 'We offer Regular Loans, Emergency Loans, Microfinance Loans, and Appliance / Equipment Loans. Each product specifies interest rates, payment frequencies, and term durations (e.g., 6 to 36 months).',
                            'tags' => ['loan products', 'terms', 'interest'],
                        ],
                        [
                            'q' => 'What are the review stages of a loan application?',
                            'a' => 'Your application progresses through five transparent stages: (1) Pending submission, (2) Under Review by the Loan Officer, (3) Endorsed for Approval, (4) Approved by the Manager/Board, and (5) Disbursed by the Cashier/Accountant.',
                            'tags' => ['review pipeline', 'approval stages', 'loan status'],
                        ],
                        [
                            'q' => 'What supporting documents are required for my loan application?',
                            'a' => 'Commonly required documents include Proof of Income (recent payslip or ITR), two Government-issued IDs, Proof of Billing, and a Co-maker / Guarantor statement where applicable.',
                            'tags' => ['documents', 'proof of income', 'co-maker'],
                        ],
                    ],
                ],
                [
                    'id' => 'repayments',
                    'label' => 'Loan Repayments',
                    'icon' => 'fa-calendar-check',
                    'description' => 'Amortization schedules, advance payments, and notification alerts.',
                    'faqs' => [
                        [
                            'q' => 'How can I make loan repayments?',
                            'a' => 'Go to Services > Loan Payment, select your active loan, and enter the payment amount. You can also settle amortizations directly at our branch offices.',
                            'tags' => ['payment', 'amortization', 'services'],
                        ],
                        [
                            'q' => 'Can I pay my loan in advance or make partial payments?',
                            'a' => 'Yes! Partial and advance payments are accepted and credited towards your outstanding principal and interest balance, helping reduce your total interest cost.',
                            'tags' => ['advance payment', 'partial payment'],
                        ],
                        [
                            'q' => 'How does the system remind me of upcoming loan dues?',
                            'a' => 'The automated notification system dispatches reminders to your dashboard notifications and email 7 days, 3 days, and 1 day before your scheduled due date.',
                            'tags' => ['due date', 'reminders', 'notifications'],
                        ],
                    ],
                ],
                [
                    'id' => 'security',
                    'label' => 'Security & Account',
                    'icon' => 'fa-shield-halved',
                    'description' => 'Password management, trusted devices, and account privacy.',
                    'faqs' => [
                        [
                            'q' => 'How do I change my password securely?',
                            'a' => 'Navigate to Settings > My Account. In the Change Password card, click "Send Code" to receive an OTP PIN via email, enter the 6-digit code, and enter your new password.',
                            'tags' => ['password', 'otp', 'settings'],
                        ],
                        [
                            'q' => 'What does "Revoke All Trusted Devices" do?',
                            'a' => 'If you suspect unauthorized access or logged in from a shared computer, clicking "Revoke All Devices" under Settings > My Account terminates all active login sessions immediately across all browsers.',
                            'tags' => ['trusted devices', 'sessions', 'security'],
                        ],
                        [
                            'q' => 'How do I update my registered email address?',
                            'a' => 'Under Settings > My Account, click "Edit Profile" and update your email. You will be required to verify the new email address via OTP confirmation before the change is saved.',
                            'tags' => ['email update', 'verification'],
                        ],
                    ],
                ],
                [
                    'id' => 'support',
                    'label' => 'Support & Messaging',
                    'icon' => 'fa-life-ring',
                    'description' => 'Using the ticket module, communicating with officers, and resolving issues.',
                    'faqs' => [
                        [
                            'q' => 'How do I submit a support ticket to cooperative staff?',
                            'a' => 'Go to Settings > Help & Support and switch to the "Active Tickets & Messages" tab. Click "Submit New Ticket", choose the appropriate category, set the priority, and describe your inquiry or issue.',
                            'tags' => ['ticket', 'submit', 'support'],
                        ],
                        [
                            'q' => 'Can I attach screenshots or images to my support inquiry?',
                            'a' => 'Yes! When submitting a ticket or replying to an officer, you can upload up to 5 image files (PNG, JPG, WEBP, GIF, up to 5MB each). Screenshots help our support team diagnose and fix issues faster.',
                            'tags' => ['attachments', 'screenshots', 'images'],
                        ],
                        [
                            'q' => 'How do I converse with the officer assigned to my ticket?',
                            'a' => 'Open any active ticket in your "Active Tickets & Messages" tab to view the live conversation thread. You can write replies and review officer messages directly in the modal.',
                            'tags' => ['messages', 'chat', 'replies'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function current(): array
    {
        $defaults = self::defaults();

        $categories = SiteContent::query()
            ->where('section', self::SECTION)
            ->where('key', 'knowledge_base')
            ->where('is_active', true)
            ->value('value');

        $decoded = $categories ? json_decode($categories, true) : null;

        return [
            'content' => [
                'header_description' => self::storedOr(self::SECTION, 'header_description', $defaults['content']['header_description']),
                'callout_title' => self::storedOr(self::SECTION, 'callout_title', $defaults['content']['callout_title']),
                'callout_description' => self::storedOr(self::SECTION, 'callout_description', $defaults['content']['callout_description']),
            ],
            'categories' => is_array($decoded) && $decoded !== [] ? $decoded : $defaults['categories'],
        ];
    }

    public static function save(array $content, array $categories): void
    {
        foreach ([
            'header_description' => (string) ($content['header_description'] ?? ''),
            'callout_title' => (string) ($content['callout_title'] ?? ''),
            'callout_description' => (string) ($content['callout_description'] ?? ''),
        ] as $key => $value) {
            SiteContent::query()->updateOrCreate(
                ['section' => self::SECTION, 'key' => $key],
                ['value' => trim($value), 'type' => 'text', 'is_active' => true]
            );
        }

        SiteContent::query()->updateOrCreate(
            ['section' => self::SECTION, 'key' => 'knowledge_base'],
            [
                'value' => json_encode($categories, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'type' => 'json',
                'is_active' => true,
            ]
        );
    }

    public static function sanitizeTree(array $categories): array
    {
        return collect($categories)
            ->map(function (array $category): array {
                return [
                    'id' => self::cleanText($category['id'] ?? ''),
                    'label' => self::cleanText($category['label'] ?? ''),
                    'icon' => self::cleanText($category['icon'] ?? ''),
                    'description' => self::cleanText($category['description'] ?? ''),
                    'faqs' => collect($category['faqs'] ?? [])->map(function (array $faq): array {
                        return [
                            'q' => self::cleanText($faq['q'] ?? ''),
                            'a' => self::cleanText($faq['a'] ?? ''),
                            'tags' => collect($faq['tags'] ?? [])
                                ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
                                ->map(fn ($tag) => self::cleanText($tag))
                                ->values()
                                ->all(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    private static function storedOr(string $section, string $key, string $default): string
    {
        $value = SiteContent::query()
            ->where('section', $section)
            ->where('key', $key)
            ->where('is_active', true)
            ->value('value');

        return is_string($value) && trim($value) !== '' ? $value : $default;
    }

    public static function cleanText(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }
}
