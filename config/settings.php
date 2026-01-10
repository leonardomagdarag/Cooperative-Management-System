<?php

return [
    'sections' => [
        // admin and manager (acc for chair and vchair) almost have access to all data, just have different settings capabilities
        // admin can modify data, but they can't delete it. Any major change/s will need an OTP from the manager/s
        'admin' => ['dashboard', 'officers', 'members', 'tickets' /* to access problems from users */, 'ledger', 'logs', 'announcements', 'reports', 'settings'],
        'manager' => ['dashboard', 'officers', 'members', 'tickets', 'ledger' /* shows transactions (loans, payments, etc) */, 'logs', 'announcements', 'reports', 'settings'],
        'member' => ['dashboard', 'services', 'history', 'announcements', 'settings'], // members have a different services content. They can apply for loan here or withdraw or deposit using online payment
        'loan' => ['dashboard', 'services', 'loans', 'members', 'messages', 'announcements',  'reports', 'settings'], // officer services allows the officer to record a services like show loan applications (approve them or not), make deposits, withdrawal or pay loans of members if they do not use online payment
        'secretary' => ['dashboard', 'members', 'ledger', 'tickets', 'messages', 'announcements',  'reports', 'settings'],
        'treasurer' => ['dashboard', 'services', 'members', 'ledger', 'announcements', 'reports', 'settings'],
    ],
];
