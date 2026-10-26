<?php

return [
    'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    'mode' => env('XENDIT_MODE', 'test'),
    'test_secret_key' => env('XENDIT_TEST_SECRET_KEY'),
    'test_webhook_token' => env('XENDIT_TEST_WEBHOOK_TOKEN'),
    'live_secret_key' => env('XENDIT_LIVE_SECRET_KEY'),
    'live_webhook_token' => env('XENDIT_LIVE_WEBHOOK_TOKEN'),
];
