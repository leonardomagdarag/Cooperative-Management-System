<?php

return [
    'sections' => [
        // admin and manager (acc for chair and vchair) almost have access to all data, just have different settings capabilities
        // admin can modify data, but they can't delete it. Any major change/s will need an OTP from the manager/s
        'admin' => ['dashboard', 'notifications', 'officers', 'members', 'services', 'tickets' /* to access problems from users */, 'ledger', 'logs', 'announcements', 'reports', 'settings', 'loan-products', 'role-permissions', 'profile'],
        'manager' => ['dashboard', 'notifications', 'officers', 'members', 'services', 'tickets', 'ledger' /* shows transactions (loans, payments, etc) */, 'announcements', 'reports', 'settings', 'profile'],
        'member' => ['dashboard', 'notifications', 'services', 'loans', 'history', 'announcements', 'settings', 'profile'], // members have a different services content. They can apply for loan here or withdraw or deposit using online payment
        'loan' => ['dashboard', 'notifications', 'services', 'loans', 'members', 'messages', 'announcements', 'settings', 'profile'], // officer services allows the officer to record a services like show loan applications (approve them or not), make deposits, withdrawal or pay loans of members if they do not use online payment
        'accountant' => ['dashboard', 'notifications', 'services', 'loans', 'members', 'ledger', 'announcements', 'reports', 'settings', 'profile'],
        'secretary' => ['dashboard', 'notifications', 'members', 'ledger', 'announcements', 'reports', 'settings', 'profile'],
    ],
    'requirement' => [
        'age' => 18,
    ],
    'session' => [
        'timeout' => 30, // in minutes
    ],
    'security' => [
        'otp_expiration' => 5, // in minutes
        'otp_length' => 6,
    ],
];
