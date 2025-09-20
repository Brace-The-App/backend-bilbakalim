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
        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('individual_game_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('tournament_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('game_type'); // individual, tournament
            $table->string('status')->default('active'); // active, completed, abandoned
            $table->json('current_question')->nullable(); // Mevcut soru bilgisi
            $table->integer('current_question_index')->default(0);
            $table->integer('total_questions')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('wrong_answers')->default(0);
            $table->integer('joker_used')->default(0);
            $table->integer('score')->default(0);
            $table->integer('time_remaining')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->json('game_data')->nullable(); // Oyun durumu verileri
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_sessions');
    }
};
