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
    | a conventional way to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'netgsm' => [
        'username' => env('NETGSM_USERNAME', '8503055373'),
        'password' => env('NETGSM_PASSWORD', 'F1AF196'),
        'msgheader' => env('NETGSM_MSGHEADER', 'BILBKLMINT'),
        'api_url' => env('NETGSM_API_URL', 'https://api.netgsm.com.tr/sms/rest/v2/send'),
    ],

    /*
    | Mesaj Paneli (mesajpaneli.com) — SmsVitriniService içinde resmi PHP SDK kullanılır.
    | API anahtarı: https://mesajpaneli.com/api
    */
    'smsvitrini' => [
        'api_hash' => env('SMSVITRINI_API_HASH') ?: env('SMSVITRINI_API_KEY'),
        'baslik' => env('SMSVITRINI_BASLIK'),
        'sdk_path' => env('SMSVITRINI_SDK_PATH', base_path('lib/MesajPaneli/MesajPaneliApi.php')),
        'verify_ssl' => env('SMSVITRINI_SSL_VERIFY', true) !== false
            && env('SMSVITRINI_SSL_VERIFY') !== 'false',
        'use_turkish_chars' => env('SMSVITRINI_USE_TURKISH_CHARS', true) !== false
            && env('SMSVITRINI_USE_TURKISH_CHARS') !== 'false',
        'test_phone' => env('SMSVITRINI_TEST_PHONE', '05312853058'),
    ],

    'revenuecat' => [
        'api_key' => env('REVENUECAT_API_KEY'),
    ],

    'ai_questions' => [
        'token' => env('AI_QUESTIONS_TOKEN'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Gerçek API model id (çağrıda kullanılır)
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-5'),
        // Panel + DB'de görünen etiket (API modelinden bağımsız)
        'model_label' => env('ANTHROPIC_MODEL_LABEL', 'claude-opus-5'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 6144),
    ],
];
