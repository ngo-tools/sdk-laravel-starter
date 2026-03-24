<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NGO.Tools API URL
    |--------------------------------------------------------------------------
    |
    | The base URL of the NGO.Tools instance this app connects to.
    |
    */
    'api_url' => env('NGOTOOLS_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Developer Token
    |--------------------------------------------------------------------------
    |
    | Persistent token for authenticating tunnel and sync operations.
    | Automatically set during bootstrap.
    |
    */
    'dev_token' => env('NGOTOOLS_DEV_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret for verifying webhook signatures from NGO.Tools.
    | Automatically set during bootstrap.
    |
    */
    'webhook_secret' => env('NGOTOOLS_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Development Port
    |--------------------------------------------------------------------------
    |
    | Port for the local development server.
    |
    */
    'port' => env('NGOTOOLS_PORT', 8001),
];
