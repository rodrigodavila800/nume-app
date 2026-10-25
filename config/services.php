<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'price_id' => env('STRIPE_PRICE_ID'),
        'platform_fee_percent' => env('STRIPE_PLATFORM_FEE_PERCENT', 10),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
        // Retorno do OAuth do YouTube (mesmo cliente OAuth). Vazio: usa a rota portal.social.oauth.callback.
        'youtube_redirect' => env('GOOGLE_YOUTUBE_REDIRECT_URI'),
    ],

    // App da Meta (Facebook Login for Business): Facebook Pages + Instagram profissional.
    'meta' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        // Opcional: configuração do Facebook Login for Business (substitui a lista de escopos).
        'config_id' => env('META_LOGIN_CONFIG_ID'),
        // Vazio: usa a rota portal.social.oauth.callback (meta).
        'redirect' => env('META_REDIRECT_URI'),
    ],

    // App do TikTok for Developers (Login Kit + Content Posting API).
    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        // Vazio: usa a rota portal.social.oauth.callback (tiktok).
        'redirect' => env('TIKTOK_REDIRECT_URI'),
    ],

    // Caso de uso Threads do app da Meta (tem ID e segredo próprios no painel).
    'threads' => [
        'client_id' => env('THREADS_APP_ID'),
        'client_secret' => env('THREADS_APP_SECRET'),
        // Vazio: usa a rota portal.social.oauth.callback (threads).
        'redirect' => env('THREADS_REDIRECT_URI'),
    ],

    'redsys' => [
        'merchant_code' => env('REDSYS_MERCHANT_CODE', '999008881'),
        'terminal' => env('REDSYS_TERMINAL', '001'),
        'secret_key' => env('REDSYS_SECRET_KEY', 'sq7HjrUOBfKmC5Jw6ZGg14hqEcTZip5t'),
        'environment' => env('REDSYS_ENVIRONMENT', 'test'),
        'enabled' => env('REDSYS_ENABLED', true),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'fallback_models' => ['gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.5-flash'],
    ],

];
