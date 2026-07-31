<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reward_requests', function (Blueprint $table) {
            $table->index(
                ['user_id', 'reward_type', 'reward_date', 'status'],
                'reward_requests_user_type_date_status_index'
            );
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['user_id', 'transaction_id'], 'payments_user_id_transaction_id_index');
        });

        Schema::table('coin_purchases', function (Blueprint $table) {
            $table->index(['payment_id', 'status'], 'coin_purchases_payment_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('reward_requests', function (Blueprint $table) {
            $table->dropIndex('reward_requests_user_type_date_status_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_user_id_transaction_id_index');
        });

        Schema::table('coin_purchases', function (Blueprint $table) {
            $table->dropIndex('coin_purchases_payment_id_status_index');
        });
    }
};
