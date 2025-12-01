<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('coin_purchases', function (Blueprint $table) {
            // Foreign key constraint'i kaldır
            $table->dropForeign(['payment_id']);
            // payment_id'yi string olarak değiştir
            $table->string('payment_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin_purchases', function (Blueprint $table) {
            // Geri al: string'den foreignId'ye çevir
            $table->foreignId('payment_id')->nullable()->change();
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
        });
    }
};
