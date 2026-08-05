<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_rate_periods', function (Blueprint $table) {
            $table->id();
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = açık uç
            $table->decimal('store_fee_pct', 5, 2)->default(40); // google kesinti %
            $table->decimal('income_tax_pct', 5, 2)->default(25);
            $table->decimal('kdv_pct', 5, 2)->default(0);
            $table->decimal('gift_payout_try', 10, 2)->default(100); // ödül talep başı TL
            $table->decimal('coin_to_try', 10, 4)->default(1); // 1 coin = ? TL
            $table->decimal('ad_click_floor_try', 10, 4)->default(0.20);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_rate_periods');
    }
};
