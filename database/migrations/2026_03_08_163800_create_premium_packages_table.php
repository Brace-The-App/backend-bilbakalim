<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_packages', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_days');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('TRY');
            $table->unsignedInteger('gift_coins')->default(0);
            $table->unsignedInteger('fifty_fifty_jokers')->default(0);
            $table->unsignedInteger('double_answer_jokers')->default(0);
            $table->unsignedInteger('hint_jokers')->default(0);
            $table->boolean('is_best')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_packages');
    }
};
