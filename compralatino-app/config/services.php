<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * API REST de la tienda externa YAuctions (Japón).
     * La consume el Job ProcessYAuctionPurchase en la cola 'compras-yauctions'.
     * En el entorno de demostración apunta a un host inválido a propósito
     * para ejercitar el bloque try/catch y la auditoría de fallos del Job.
     */
    'yauctions' => [
        'url'     => env('YACTIONS_URL', 'http://127.0.0.1:9'),
        'token'   => env('YACTIONS_TOKEN', 'demo-token'),
        'timeout' => 15,
    ],

];
