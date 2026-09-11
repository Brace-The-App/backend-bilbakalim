<?php

return [
    /*
    | Örnek şablon JSON dosyası (çoğaltmak için düzenleyin)
    */
    'presets_path' => env(
        'NOTIFICATION_PRESETS_PATH',
        config_path('notification_presets.json')
    ),

    'timezone' => 'Europe/Istanbul',
];
