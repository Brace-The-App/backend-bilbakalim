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
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('quota')->default(100);
            $table->json('rules')->nullable();
            $table->json('awards')->nullable(); // prize distribution
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->time('start_time')->nullable();
            $table->integer('duration_minutes')->default(60);
            $table->decimal('entry_fee', 8, 2)->default(0);
            $table->integer('question_count')->default(20);
            $table->enum('difficulty_level', ['easy', 'medium', 'hard', 'mixed'])->default('mixed');
            $table->enum('status', ['upcoming', 'active', 'completed', 'cancelled'])->default('upcoming');
            $table->string('image')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournaments');
    }
};
