<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Socket.IO Server URL
    |--------------------------------------------------------------------------
    |
    | This URL is used to send webhook requests to the Socket.IO server
    | for real-time notifications.
    |
    */

    'socket_url' => env('SOCKET_URL', 'http://localhost:3001'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook retry mechanism and error handling.
    |
    */

    'webhook_max_retries' => env('WEBHOOK_MAX_RETRIES', 3),
    'webhook_retry_delay' => env('WEBHOOK_RETRY_DELAY', 1000),
    'webhook_debug' => env('WEBHOOK_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Quiz System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for quiz system settings.
    |
    */

    'quiz_normal_time_limit' => env('QUIZ_NORMAL_TIME_LIMIT', 600),
    'quiz_premium_question_count' => env('QUIZ_PREMIUM_QUESTION_COUNT', 15),
    'quiz_premium_time_limit' => env('QUIZ_PREMIUM_TIME_LIMIT', 1800),
    'quiz_answer_time_limit' => env('QUIZ_ANSWER_TIME_LIMIT', 15), // Cevap süresi (saniye)
    'tournament_min_participants' => env('TOURNAMENT_MIN_PARTICIPANTS', 2),
    'tournament_max_participants' => env('TOURNAMENT_MAX_PARTICIPANTS', 100),
    'quiz_dev_mode' => env('QUIZ_DEV_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Joker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for joker counts in premium quiz.
    |
    */

        'joker_fifty_fifty_count' => env('JOKER_FIFTY_FIFTY_COUNT', 1),
        'joker_double_answer_count' => env('JOKER_DOUBLE_ANSWER_COUNT', 1),
        'joker_hint_count' => env('JOKER_HINT_COUNT', 1),
        'joker_fifty_fifty_price' => env('JOKER_FIFTY_FIFTY_PRICE', 100),
        'joker_double_answer_price' => env('JOKER_DOUBLE_ANSWER_PRICE', 150),
        'joker_hint_price' => env('JOKER_HINT_PRICE', 200),

    /*
    |--------------------------------------------------------------------------
    | Reward Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for reward system.
    |
    */

    'reward_package_3_coins' => env('REWARD_PACKAGE_3_COINS', 5000),
    'reward_package_3_fifty_fifty' => env('REWARD_PACKAGE_3_FIFTY_FIFTY', 5),
    'reward_package_3_double_answer' => env('REWARD_PACKAGE_3_DOUBLE_ANSWER', 5),
    'reward_package_3_hint' => env('REWARD_PACKAGE_3_HINT', 5),
    'reward_min_accuracy_rate' => env('REWARD_MIN_ACCURACY_RATE', 80),

    /*
    |--------------------------------------------------------------------------
    | Speed Bonus Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for speed bonus system in tournaments.
    |
    */

    'speed_bonus_fast_threshold' => env('SPEED_BONUS_FAST_THRESHOLD', 10),
    'speed_bonus_fast_points' => env('SPEED_BONUS_FAST_POINTS', 10),
    'speed_bonus_medium_threshold' => env('SPEED_BONUS_MEDIUM_THRESHOLD', 20),
    'speed_bonus_medium_points' => env('SPEED_BONUS_MEDIUM_POINTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for notification system.
    |
    */

    'notification_email_enabled' => env('NOTIFICATION_EMAIL_ENABLED', true),
    'notification_fcm_enabled' => env('NOTIFICATION_FCM_ENABLED', true),
    'notification_sms_enabled' => env('NOTIFICATION_SMS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'tr'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'tr'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'tr_TR'),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | These are the supported locales for the application.
    | Used by the translatable package.
    |
    */

    'supported_locales' => ['tr', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quiz System Configuration
    |--------------------------------------------------------------------------
    */
    
    // Coin değerleri
    'coin_values' => [
        'easy' => [
            'min' => 20,
            'max' => 50
        ],
        'medium' => [
            'min' => 50,
            'max' => 300
        ],
        'hard' => [
            'min' => 300,
            'max' => 10000
        ]
    ],

    // Joker fiyatları
    'joker_prices' => [
        'fifty_fifty' => 100,
        'double_answer' => 200,
        'hint' => 150
    ],

    // Joker başlangıç sayıları
    'joker_counts' => [
        'fifty_fifty' => 1,
        'double_answer' => 1,
        'hint' => 1
    ],

    // Premium Quiz ödül sistemi
    'reward_perfect_score_coins' => 10000,
    'reward_perfect_score_fifty_fifty' => 10,
    'reward_perfect_score_double_answer' => 10,
    'reward_perfect_score_hint' => 10,
    
    'reward_high_accuracy_coins' => 2000,
    'reward_high_accuracy_fifty_fifty' => 3,
    'reward_high_accuracy_double_answer' => 3,
    'reward_high_accuracy_hint' => 3,

];
