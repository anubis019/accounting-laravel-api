<?php

return [
    'environment' => env('MPESA_ENV', 'sandbox'),
    'consumer_key' => env('MPESA_CONSUMER_KEY'),
    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
    'shortcode' => env('MPESA_SHORTCODE', '174379'),
    'passkey' => env('MPESA_PASSKEY'),
    'callback_url' => env('MPESA_CALLBACK_URL', url('/api/mpesa/callback')),
    'initiator_name' => env('MPESA_INITIATOR_NAME'),
    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),
];