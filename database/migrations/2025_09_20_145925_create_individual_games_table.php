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
        Schema::create('individual_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('game_type')->default('individual'); // individual, practice, daily_challenge, tournament_individual
            $table->string('difficulty_level')->default('medium'); // easy, medium, hard
            $table->integer('question_count')->default(10);
            $table->integer('time_limit_seconds')->default(600); // 10 dakika
            $table->integer('joker_count')->default(3);
            $table->integer('score')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('wrong_answers')->default(0);
            $table->integer('coins_earned')->default(0);
            $table->integer('total_time_seconds')->default(0);
            $table->string('status')->default('pending'); // pending, active, completed, abandoned
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('settings')->nullable(); // Oyun ayarları
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('individual_games');
    }
};
