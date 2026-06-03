<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FMCSA SAFER Web Services
    |--------------------------------------------------------------------------
    | Register for a free API key at:
    | https://mobile.fmcsa.dot.gov/qc/services/registration
    */
    'fmcsa' => [
        'web_key' => env('FMCSA_WEB_KEY'),
        'base_url' => env('FMCSA_BASE_URL', 'https://mobile.fmcsa.dot.gov/qc/services/carriers'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JSONCargo Container & Vessel Tracking API
    |--------------------------------------------------------------------------
    | Real-time shipment & vessel tracking from major shipping lines.
    | Get an API key at: https://jsoncargo.com/pricing
    |
    | Supported: Maersk, Hapag-Lloyd, HMM, ONE, Evergreen, MSC,
    |            CMA CGM, COSCO, ZIM, Yang Ming, PIL
    */
    'jsoncargo' => [
        'api_key'   => env('JSONCARGO_API_KEY'),
        'base_url'  => env('JSONCARGO_BASE_URL', 'https://api.jsoncargo.com/api/v1'),
        'cache_ttl' => env('JSONCARGO_CACHE_TTL', 900), // 15 minutes default
    ],

    /*
    |--------------------------------------------------------------------------
    | EDI Webhook
    |--------------------------------------------------------------------------
    | Shared secret key for authenticating inbound EDI 315 messages from
    | carriers and EDI value-added networks (VANs).
    | Set EDI_WEBHOOK_KEY in .env to a long random string.
    */
    'edi' => [
        'webhook_key' => env('EDI_WEBHOOK_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic Claude API (Document OCR / Extraction)
    |--------------------------------------------------------------------------
    | Used for AI-powered extraction of structured shipping data from PDFs
    | (Delivery Orders, Bills of Lading, Arrival Notices).
    | Get an API key at: https://console.anthropic.com
    */
    'anthropic' => [
        'api_key'  => env('ANTHROPIC_API_KEY'),
        'model'    => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
        'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Datalastic AIS Vessel Tracking
    |--------------------------------------------------------------------------
    | Real-time global AIS vessel positions for the vessel map.
    | Get an API key at: https://datalastic.com
    */
    'datalastic' => [
        'api_key'  => env('DATALASTIC_API_KEY'),
        'base_url' => env('DATALASTIC_BASE_URL', 'https://api.datalastic.com/api/v0'),
        'cache_ttl'=> (int) env('DATALASTIC_CACHE_TTL', 120),
    ],

];
