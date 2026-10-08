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


    // Consulta de prévias de links externos (Open Graph, oEmbed).
    'link_preview' => [
        'enabled' => env('LINK_PREVIEW_ENABLED', true),
        // Vazio = conexão direta (padrão). Um proxy reduz a proteção contra SSRF, pois ele resolve o DNS.
        'proxy' => env('LINK_PREVIEW_PROXY', ''),
    ],

    // Procedimento seguro do primeiro Master pela web (somente sem usuários cadastrados).
    'setup' => [
        'token' => env('SETUP_TOKEN'),
    ],

];
