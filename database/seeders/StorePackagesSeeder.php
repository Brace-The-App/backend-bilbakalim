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
        // Eski yüksek coin paketlerini pasife al
        CoinPackage::query()->update(['is_active' => false]);

        // Jeton paketleri (Google Play komisyonu dahil fiyatlar)
        $coinPackages = [
            ['code' => 'coin_3', 'amount' => 3, 'price' => 4.99],
            ['code' => 'coin_6', 'amount' => 6, 'price' => 9.99],
            ['code' => 'coin_12', 'amount' => 12, 'price' => 19.99],
            ['code' => 'coin_30', 'amount' => 30, 'price' => 49.99],
            ['code' => 'coin_60', 'amount' => 60, 'price' => 99.99],
            ['code' => 'coin_120', 'amount' => 120, 'price' => 199.99],
            ['code' => 'coin_300', 'amount' => 300, 'price' => 499.99],
            ['code' => 'coin_600', 'amount' => 600, 'price' => 999.99],
            ['code' => 'coin_1200', 'amount' => 1200, 'price' => 1999.99],
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
                    'is_popular' => $item['amount'] === 30,
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
                'price' => 50.00,
                'gift_coins' => 30,
                'fifty_fifty_jokers' => 2,
                'double_answer_jokers' => 2,
                'hint_jokers' => 2,
                'is_best' => false,
            ],
            [
                'code' => 'premium_6_month',
                'name' => '6 Aylik Paket',
                'duration_days' => 180,
                'price' => 100.00,
                'gift_coins' => 60,
                'fifty_fifty_jokers' => 10,
                'double_answer_jokers' => 10,
                'hint_jokers' => 10,
                'is_best' => false,
            ],
            [
                'code' => 'premium_1_year',
                'name' => 'Yillik Paket',
                'duration_days' => 365,
                'price' => 200.00,
                'gift_coins' => 120,
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

        // Joker paketleri: 10 / 100 / 1000 / 3000 / 5000 (≈1 coin = 1 TL + Google ~%40 → price ≈ coin/0.6)
        $jokerPackages = [
            ['code' => 'pack_1_consumable', 'name' => 'Paket 1', 'coin_amount' => 10, 'price' => 16.99, 'fifty_fifty_jokers' => 1, 'double_answer_jokers' => 1, 'hint_jokers' => 1],
            ['code' => 'pack_2_consumable', 'name' => 'Paket 2', 'coin_amount' => 100, 'price' => 166.99, 'fifty_fifty_jokers' => 3, 'double_answer_jokers' => 3, 'hint_jokers' => 3],
            ['code' => 'pack_3_consumable', 'name' => 'Paket 3', 'coin_amount' => 1000, 'price' => 1666.99, 'fifty_fifty_jokers' => 5, 'double_answer_jokers' => 5, 'hint_jokers' => 5],
            ['code' => 'pack_4_consumable', 'name' => 'Paket 4', 'coin_amount' => 3000, 'price' => 4999.99, 'fifty_fifty_jokers' => 10, 'double_answer_jokers' => 10, 'hint_jokers' => 10],
            ['code' => 'pack_5_consumable', 'name' => 'Paket 5', 'coin_amount' => 5000, 'price' => 8333.99, 'fifty_fifty_jokers' => 15, 'double_answer_jokers' => 15, 'hint_jokers' => 15],
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

        // Elmas paketleri — şu an kapalı (düello/meydan okuma coin ile)
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
                    'is_active' => false,
                ]
            );
        }
    }
}
