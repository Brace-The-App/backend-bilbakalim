<?php

namespace Database\Seeders;

use App\Models\CoinPackage;
use App\Models\DiamondPackage;
use App\Models\JokerPackage;
use App\Models\PremiumPackage;
use Illuminate\Database\Seeder;

class StorePackagesSeeder extends Seeder
{
    public function run(): void
    {
        $coinPackages = [
            ['code' => 'coin_1000', 'amount' => 1000, 'price' => 4.99],
            ['code' => 'coin_2500', 'amount' => 2500, 'price' => 9.99],
            ['code' => 'coin_5000', 'amount' => 5000, 'price' => 19.99],
            ['code' => 'coin_10000', 'amount' => 10000, 'price' => 34.99],
            ['code' => 'coin_25000', 'amount' => 25000, 'price' => 79.99],
            ['code' => 'coin_50000', 'amount' => 50000, 'price' => 149.99],
            ['code' => 'coin_100000', 'amount' => 100000, 'price' => 249.99],
            ['code' => 'coin_250000', 'amount' => 250000, 'price' => 499.99],
            ['code' => 'coin_500000', 'amount' => 500000, 'price' => 999.99],
        ];

        foreach ($coinPackages as $index => $item) {
            CoinPackage::updateOrCreate(
                ['name' => strtoupper($item['code'])],
                [
                    'description' => $item['code'],
                    'coin_amount' => $item['amount'],
                    'price' => $item['price'],
                    'currency' => 'TRY',
                    'bonus_coins' => 0,
                    'is_popular' => false,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        $premiumPackages = [
            [
                'code' => 'premium_1_month',
                'name' => 'Aylik Paket',
                'duration_days' => 30,
                'price' => 29.99,
                'gift_coins' => 2500,
                'fifty_fifty_jokers' => 2,
                'double_answer_jokers' => 2,
                'hint_jokers' => 2,
                'is_best' => false,
            ],
            [
                'code' => 'premium_6_month',
                'name' => '6 Aylik Paket',
                'duration_days' => 180,
                'price' => 149.99,
                'gift_coins' => 25000,
                'fifty_fifty_jokers' => 10,
                'double_answer_jokers' => 10,
                'hint_jokers' => 10,
                'is_best' => false,
            ],
            [
                'code' => 'premium_1_year',
                'name' => 'Yillik Paket',
                'duration_days' => 365,
                'price' => 199.99,
                'gift_coins' => 50000,
                'fifty_fifty_jokers' => 25,
                'double_answer_jokers' => 25,
                'hint_jokers' => 25,
                'is_best' => true,
            ],
        ];

        foreach ($premiumPackages as $index => $item) {
            PremiumPackage::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'description' => $item['code'],
                    'duration_days' => $item['duration_days'],
                    'price' => $item['price'],
                    'currency' => 'TRY',
                    'gift_coins' => $item['gift_coins'],
                    'fifty_fifty_jokers' => $item['fifty_fifty_jokers'],
                    'double_answer_jokers' => $item['double_answer_jokers'],
                    'hint_jokers' => $item['hint_jokers'],
                    'is_best' => $item['is_best'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        $jokerPackages = [
            ['code' => 'pack_1_consumable', 'name' => 'Paket 1', 'coin_amount' => 1000, 'price' => 19.99, 'fifty_fifty_jokers' => 1, 'double_answer_jokers' => 1, 'hint_jokers' => 1],
            ['code' => 'pack_2_consumable', 'name' => 'Paket 2', 'coin_amount' => 2500, 'price' => 39.99, 'fifty_fifty_jokers' => 3, 'double_answer_jokers' => 3, 'hint_jokers' => 3],
            ['code' => 'pack_3_consumable', 'name' => 'Paket 3', 'coin_amount' => 5000, 'price' => 69.99, 'fifty_fifty_jokers' => 5, 'double_answer_jokers' => 5, 'hint_jokers' => 5],
            ['code' => 'pack_4_consumable', 'name' => 'Paket 4', 'coin_amount' => 10000, 'price' => 119.99, 'fifty_fifty_jokers' => 10, 'double_answer_jokers' => 10, 'hint_jokers' => 10],
            ['code' => 'pack_5_consumable', 'name' => 'Paket 5', 'coin_amount' => 25000, 'price' => 249.99, 'fifty_fifty_jokers' => 15, 'double_answer_jokers' => 15, 'hint_jokers' => 15],
        ];

        foreach ($jokerPackages as $index => $item) {
            JokerPackage::updateOrCreate(
                ['code' => $item['code']],
                [
                    'name' => $item['name'],
                    'description' => $item['code'],
                    'price' => $item['price'],
                    'currency' => 'TRY',
                    'coin_amount' => $item['coin_amount'],
                    'fifty_fifty_jokers' => $item['fifty_fifty_jokers'],
                    'double_answer_jokers' => $item['double_answer_jokers'],
                    'hint_jokers' => $item['hint_jokers'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }

        // Elmas paketleri - dinamik tabloya başlangıç verileri
        $diamondPackages = [
            ['name' => '10 Elmas', 'diamond_amount' => 10, 'price' => 14.99, 'gross_price' => 14.99],
            ['name' => '25 Elmas', 'diamond_amount' => 25, 'price' => 34.99, 'gross_price' => 34.99],
            ['name' => '60 Elmas', 'diamond_amount' => 60, 'price' => 79.99, 'gross_price' => 79.99],
            ['name' => '140 Elmas', 'diamond_amount' => 140, 'price' => 169.99, 'gross_price' => 169.99],
        ];

        foreach ($diamondPackages as $index => $item) {
            DiamondPackage::updateOrCreate(
                ['name' => $item['name']],
                [
                    'diamond_amount' => $item['diamond_amount'],
                    'price' => $item['price'],
                    'gross_price' => $item['gross_price'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
