<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Düello coin_history kayıtları için 'duel' tipi eksikti → cevap transaction rollback oluyordu
        DB::statement("ALTER TABLE coin_history MODIFY COLUMN transaction_type ENUM(
            'earned',
            'spent',
            'bonus',
            'tournament_prize',
            'daily_reward',
            'purchase',
            'tournament_wrong_answer',
            'tournament_correct_answer',
            'duel',
            'ad_watch'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE coin_history MODIFY COLUMN transaction_type ENUM(
            'earned',
            'spent',
            'bonus',
            'tournament_prize',
            'daily_reward',
            'purchase',
            'tournament_wrong_answer',
            'tournament_correct_answer'
        ) NOT NULL");
    }
};
