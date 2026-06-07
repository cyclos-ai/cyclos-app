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

    'stripe' => [
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
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

    /*
    |--------------------------------------------------------------------------
    | Google Maps JavaScript API
    |--------------------------------------------------------------------------
    | Browser-side key for the global vessel tracking map. Injected into the
    | SPA at runtime (window.__GMAPS_KEY__) so it never requires a rebuild.
    | Get a key at: https://console.cloud.google.com/google/maps-apis
    */
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QuickBooks Online (Intuit) Accounting Integration
    |--------------------------------------------------------------------------
    | OAuth2 connection to QuickBooks Online for pushing invoices.
    | Create an app and get credentials at: https://developer.intuit.com
    |
    | environment: 'production' (https://quickbooks.api.intuit.com) or
    |              'sandbox'    (https://sandbox-quickbooks.api.intuit.com)
    */
    'quickbooks' => [
        'client_id'     => env('QUICKBOOKS_CLIENT_ID'),
        'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
        'environment'   => env('QUICKBOOKS_ENVIRONMENT', 'production'),
        'redirect_uri'  => env('QUICKBOOKS_REDIRECT_URI', 'https://demo.cyclos.ai/quickbooks/callback'),
        'scopes'        => 'com.intuit.quickbooks.accounting com.intuit.quickbooks.payment openid',
        'minor_version' => env('QUICKBOOKS_MINOR_VERSION', '73'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Steamship Line Direct APIs (DCSA Track & Trace fallback)
    |--------------------------------------------------------------------------
    | Used when JSONCargo has no data for a container. Each carrier exposes a
    | DCSA Track & Trace 2.x endpoint at GET {base_url}/events?equipmentReference=
    |
    | auth: 'oauth2'  — client-credentials grant (Maersk)
    | auth: 'apikey'  — static key in Authorization/X-Api-Key headers
    */
    'carriers' => [
        'maersk' => [
            'scac'          => 'MAEU',
            'name'          => 'Maersk',
            'base_url'      => env('MAERSK_DCSA_URL'),
            'auth'          => 'oauth2',
            'client_id'     => env('MAERSK_CLIENT_ID'),
            'client_secret' => env('MAERSK_CLIENT_SECRET'),
            'token_url'     => env('MAERSK_TOKEN_URL'),
        ],
        'cma_cgm' => [
            'scac'     => 'CMDU',
            'name'     => 'CMA CGM',
            'base_url' => env('CMA_CGM_DCSA_URL'),
            'auth'     => 'apikey',
            'api_key'  => env('CMA_CGM_API_KEY'),
        ],
        'hapag' => [
            'scac'     => 'HLCU',
            'name'     => 'Hapag-Lloyd',
            'base_url' => env('HAPAG_DCSA_URL'),
            'auth'     => 'apikey',
            'api_key'  => env('HAPAG_API_KEY'),
        ],
        'one' => [
            'scac'     => 'ONEY',
            'name'     => 'Ocean Network Express',
            'base_url' => env('ONE_DCSA_URL'),
            'auth'     => 'apikey',
            'api_key'  => env('ONE_API_KEY'),
        ],
        'zim' => [
            'scac'     => 'ZIMU',
            'name'     => 'ZIM',
            'base_url' => env('ZIM_DCSA_URL'),
            'auth'     => 'apikey',
            'api_key'  => env('ZIM_API_KEY'),
        ],
    ],

];
