<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayMongo Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com'),

    /*
    |--------------------------------------------------------------------------
    | Default Mode ('sandbox' or 'live')
    |--------------------------------------------------------------------------
    | Sandbox / Test mode is safe by default.
    */
    'mode' => env('PAYMONGO_MODE', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Test / Sandbox Credentials (fallback from environment)
    |--------------------------------------------------------------------------
    */
    'test_public_key' => env('PAYMONGO_TEST_PUBLIC_KEY'),
    'test_secret_key' => env('PAYMONGO_TEST_SECRET_KEY'),
    'test_webhook_secret' => env('PAYMONGO_TEST_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Live Credentials (fallback from environment)
    |--------------------------------------------------------------------------
    */
    'live_public_key' => env('PAYMONGO_LIVE_PUBLIC_KEY'),
    'live_secret_key' => env('PAYMONGO_LIVE_SECRET_KEY'),
    'live_webhook_secret' => env('PAYMONGO_LIVE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Default Supported Payment Methods
    |--------------------------------------------------------------------------
    */
    'supported_methods' => [
        'gcash' => [
            'name' => 'GCash',
            'type' => 'gcash',
            'description' => 'Pay via GCash e-wallet',
            'icon' => 'fa-wallet',
        ],
        'paymaya' => [
            'name' => 'Maya',
            'type' => 'paymaya',
            'description' => 'Pay via Maya e-wallet',
            'icon' => 'fa-wallet',
        ],
        'grab_pay' => [
            'name' => 'GrabPay',
            'type' => 'grab_pay',
            'description' => 'Pay via GrabPay e-wallet',
            'icon' => 'fa-wallet',
        ],
        'shopeepay' => [
            'name' => 'ShopeePay',
            'type' => 'shopeepay',
            'description' => 'Pay via ShopeePay e-wallet',
            'icon' => 'fa-wallet',
        ],
        'card' => [
            'name' => 'Credit / Debit Card',
            'type' => 'card',
            'description' => 'Visa or Mastercard with 3D Secure',
            'icon' => 'fa-credit-card',
        ],
        'qrph' => [
            'name' => 'QR Ph',
            'type' => 'qrph',
            'description' => 'National QR code supported by PH banks & e-wallets',
            'icon' => 'fa-qrcode',
        ],
        'dob' => [
            'name' => 'Direct Online Banking',
            'type' => 'dob',
            'description' => 'Direct online banking transfer (BPI, UnionBank)',
            'icon' => 'fa-university',
        ],
        'billease' => [
            'name' => 'BillEase',
            'type' => 'billease',
            'description' => 'Buy Now, Pay Later in installments',
            'icon' => 'fa-calendar-alt',
        ],
    ],
];
