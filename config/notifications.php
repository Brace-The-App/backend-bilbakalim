<?php

return [
    /*
    | Canlı Bildirim Akışı — yalnızca bu kullanıcı görür (varsayılan: Muhammet #15)
    */
    'live_flow_user_id' => (int) env('NOTIFICATION_LIVE_FLOW_USER_ID', 15),

    /*
    | Örnek şablon JSON dosyası (çoğaltmak için düzenleyin)
    */
    'presets_path' => env(
        'NOTIFICATION_PRESETS_PATH',
        config_path('notification_presets.json')
    ),

    'timezone' => 'Europe/Istanbul',
];
