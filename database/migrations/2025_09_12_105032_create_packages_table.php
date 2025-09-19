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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->json('features')->nullable();
            $table->integer('time_limit_days')->nullable(); // days
            $table->integer('question_limit')->nullable();
            $table->integer('tournament_limit')->nullable();
            $table->boolean('ads_free')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('color_code')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
