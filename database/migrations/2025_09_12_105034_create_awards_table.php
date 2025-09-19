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
        Schema::create('awards', function (Blueprint $table) {
            $table->id();
            $table->string('brand_name');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('award_value', 10, 2);
            $table->enum('award_type', ['coin', 'gift_card', 'product', 'discount']);
            $table->enum('status', ['pending', 'approved', 'delivered', 'cancelled'])->default('pending');
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->date('valid_until')->nullable();
            $table->json('delivery_info')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('awards');
    }
};
