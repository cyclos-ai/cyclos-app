<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Carrier Polling Interval (seconds)
    |--------------------------------------------------------------------------
    */
    'default_poll_interval' => env('CARRIER_POLLING_INTERVAL', 300),

    /*
    |--------------------------------------------------------------------------
    | Maersk
    | Handles: MAEU, SUDU (Hamburg Süd), SAFI (Safmarine), CCNI
    | Docs: https://developer.maersk.com
    |--------------------------------------------------------------------------
    */
    'maersk' => [
        'api_key'      => env('MAERSK_API_KEY'),
        'api_url'      => env('MAERSK_API_URL', 'https://api.maersk.com'),
        'consumer_key' => env('MAERSK_CONSUMER_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | MSC - Mediterranean Shipping Company
    | Handles: MSCU, MEDU
    |--------------------------------------------------------------------------
    */
    'msc' => [
        'api_key' => env('MSC_API_KEY'),
        'api_url' => env('MSC_API_URL', 'https://www.msc.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CMA CGM Group
    | Handles: CMDU, ANNU (ANL), APLU (APL)
    | Docs: https://developer.cma-cgm.com
    |--------------------------------------------------------------------------
    */
    'cma_cgm' => [
        'api_key' => env('CMA_CGM_API_KEY'),
        'api_url' => env('CMA_CGM_API_URL', 'https://api.cma-cgm.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | COSCO Shipping
    | Handles: COSU, CCLU, OOLU (OOCL)
    |--------------------------------------------------------------------------
    */
    'cosco' => [
        'api_key' => env('COSCO_API_KEY'),
        'api_url' => env('COSCO_API_URL', 'https://elines.coscoshipping.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evergreen Marine
    | Handles: EGLV, EGHU
    |--------------------------------------------------------------------------
    */
    'evergreen' => [
        'api_key' => env('EVERGREEN_API_KEY'),
        'api_url' => env('EVERGREEN_API_URL', 'https://www.evergreen-line.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hapag-Lloyd
    | Handles: HLCU, HLXU
    | Docs: https://www.hapag-lloyd.com/en/online-business/api.html
    | Auth: OAuth2 client credentials
    |--------------------------------------------------------------------------
    */
    'hapag_lloyd' => [
        'api_key'       => env('HAPAG_LLOYD_API_KEY'),
        'client_id'     => env('HAPAG_LLOYD_CLIENT_ID'),
        'client_secret' => env('HAPAG_LLOYD_CLIENT_SECRET'),
        'api_url'       => env('HAPAG_LLOYD_API_URL', 'https://api.hlag.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ONE - Ocean Network Express
    | Handles: ONEY, ONEU, KKLU ("K" Line), NYKU (NYK), MOLU (MOL)
    |--------------------------------------------------------------------------
    */
    'one' => [
        'api_key' => env('ONE_API_KEY'),
        'api_url' => env('ONE_API_URL', 'https://ecomm.one-line.com'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Yang Ming Marine
    | Handles: YMLU, YMJA
    |--------------------------------------------------------------------------
    */
    'yang_ming' => [
        'api_key' => env('YANG_MING_API_KEY'),
        'api_url' => env('YANG_MING_API_URL', 'https://www.yangming.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ZIM Integrated Shipping
    | Handles: ZIMU, ZLCU
    |--------------------------------------------------------------------------
    */
    'zim' => [
        'api_key' => env('ZIM_API_KEY'),
        'api_url' => env('ZIM_API_URL', 'https://www.zim.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HMM - Hyundai Merchant Marine
    | Handles: HDMU
    |--------------------------------------------------------------------------
    */
    'hmm' => [
        'api_key' => env('HMM_API_KEY'),
        'api_url' => env('HMM_API_URL', 'https://www.hmm21.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wan Hai Lines
    | Handles: WHLC, WHLU
    |--------------------------------------------------------------------------
    */
    'wan_hai' => [
        'api_key' => env('WAN_HAI_API_KEY'),
        'api_url' => env('WAN_HAI_API_URL', 'https://www.wanhai.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PIL - Pacific International Lines
    | Handles: PILU
    |--------------------------------------------------------------------------
    */
    'pil' => [
        'api_key' => env('PIL_API_KEY'),
        'api_url' => env('PIL_API_URL', 'https://www.pilship.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SM Line Corporation
    | Handles: KMTU
    |--------------------------------------------------------------------------
    */
    'sm_line' => [
        'api_key' => env('SM_LINE_API_KEY'),
        'api_url' => env('SM_LINE_API_URL', 'https://www.smlines.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | X-Press Feeders
    | Handles: XPRU
    |--------------------------------------------------------------------------
    */
    'x_press_feeders' => [
        'api_key' => env('XPRESS_FEEDERS_API_KEY'),
        'api_url' => env('XPRESS_FEEDERS_API_URL', 'https://www.x-pressfeeders.com/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits (per carrier)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'MSCU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'MAEU' => ['per_second' => 10, 'per_minute' => 200, 'per_day' => 5000],
        'CMDU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'COSU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'HLCU' => ['per_second' => 3,  'per_minute' => 60,  'per_day' => 2000],
        'ONEY' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'EGLV' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'HDMU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'YMLU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'ZIMU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 2000],
        'WHLC' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'PILU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'SUDU' => ['per_second' => 10, 'per_minute' => 200, 'per_day' => 5000],
        'KMTU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
        'XPRU' => ['per_second' => 5,  'per_minute' => 100, 'per_day' => 3000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhook_secrets' => [
        'maersk' => env('MAERSK_WEBHOOK_SECRET'),
    ],

];
