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
        Schema::create('coin_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Paket adı
            $table->text('description')->nullable(); // Paket açıklaması
            $table->integer('coin_amount'); // Jeton miktarı
            $table->decimal('price', 10, 2); // Fiyat
            $table->string('currency', 3)->default('TRY'); // Para birimi
            $table->integer('bonus_coins')->default(0); // Bonus jeton
            $table->boolean('is_popular')->default(false); // Popüler paket mi
            $table->boolean('is_active')->default(true); // Aktif mi
            $table->string('color_code')->nullable(); // Renk kodu
            $table->string('icon')->nullable(); // İkon
            $table->integer('sort_order')->default(0); // Sıralama
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_packages');
    }
};
