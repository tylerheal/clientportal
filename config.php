<?php
return [
    'app_name' => 'Service Portal',
    'default_admin' => [
        'email' => 'admin@example.com',
        'password' => 'Admin123!',
        'name' => 'Administrator'
    ],
    'mail' => [
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Service Portal'
    ],
    'payment' => [
        'stripe_publishable_key' => '',
        'stripe_secret_key' => '',
        'paypal_client_id' => '',
        'paypal_client_secret' => '',
        'paypal_mode' => 'sandbox',
        'enable_paypal' => false,
        'enable_stripe' => false,
        'enable_google_pay' => false,
    ]
];
