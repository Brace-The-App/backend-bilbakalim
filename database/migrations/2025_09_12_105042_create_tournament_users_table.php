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
        Schema::create('tournament_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('joker_hakki')->default(3);
            $table->integer('score')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('wrong_answers')->default(0);
            $table->integer('total_time_seconds')->default(0);
            $table->enum('status', ['waiting', 'active', 'eliminated', 'completed'])->default('waiting');
            $table->timestamp('joined_at')->nullable();
            $table->json('answers_detail')->nullable();
            $table->timestamps();
            
            $table->unique(['tournament_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_users');
    }
};
