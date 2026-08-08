<?php

return [
    /*
    | Destek cevabı gönderen hesaplar.
    | İleride birden fazla eklenebilir; formda seçilir, varsayılan default_account.
    */
    'mail_accounts' => [
        [
            'id' => 'destek',
            'label' => 'Bil Bakalım Destek',
            'from_address' => env('SUPPORT_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'destek@bilbakalim.online')),
            'from_name' => env('SUPPORT_MAIL_FROM_NAME', 'Bil Bakalım Destek'),
        ],
    ],

    'default_account' => env('SUPPORT_MAIL_DEFAULT', 'destek'),
];
