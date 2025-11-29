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
        Schema::create('diamond_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Paket adı (10 Elmas, 20 Elmas, vb.)');
            $table->integer('diamond_amount')->comment('Elmas miktarı');
            $table->decimal('price', 10, 2)->comment('Fiyat (TL) - Google kesintisi sonrası net fiyat');
            $table->decimal('gross_price', 10, 2)->comment('Brüt fiyat (Google kesintisi öncesi)');
            $table->integer('sort_order')->default(0)->comment('Sıralama');
            $table->boolean('is_active')->default(true)->comment('Aktif mi?');
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diamond_packages');
    }
};
