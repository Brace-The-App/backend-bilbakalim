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
        Schema::create('coin_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('coin_amount');
            $table->enum('transaction_type', ['earned', 'spent', 'bonus', 'tournament_prize', 'daily_reward', 'purchase']);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->string('description')->nullable();
            $table->json('metadata')->nullable(); // additional info like question_id, tournament_id, etc.
            $table->integer('balance_before')->default(0);
            $table->integer('balance_after')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin_history');
    }
};
