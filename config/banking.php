<?php

return [
    'equity' => [
        'auth_url' => env('EQUITY_AUTH_URL', 'https://api.equitybank.com/v1/auth'),
        'transactions_url' => env('EQUITY_TRANSACTIONS_URL', 'https://api.equitybank.com/v1/transactions'),
        'client_id' => env('EQUITY_CLIENT_ID'),
        'client_secret' => env('EQUITY_CLIENT_SECRET'),
    ],
    'kcb' => [
        'auth_url' => env('KCB_AUTH_URL', 'https://api.kcbgroup.com/v1/auth'),
        'transactions_url' => env('KCB_TRANSACTIONS_URL', 'https://api.kcbgroup.com/v1/accounts/transactions'),
        'api_key' => env('KCB_API_KEY'),
        'api_secret' => env('KCB_API_SECRET'),
    ],
];