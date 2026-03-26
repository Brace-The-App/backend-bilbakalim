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
        'msgheader' => env('NETGSM_MSGHEADER', 'ATOM GIDA'),
        'api_url' => env('NETGSM_API_URL', 'https://api.netgsm.com.tr/sms/rest/v2/send'),
    ],

    'revenuecat' => [
        'api_key' => env('REVENUECAT_API_KEY'),
    ],

];
