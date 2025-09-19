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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('one_choice');
            $table->string('two_choice');
            $table->string('three_choice');
            $table->string('four_choice');
            $table->enum('correct_answer', ['1', '2', '3', '4']);
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->enum('question_level', ['easy', 'medium', 'hard'])->default('easy');
            $table->integer('coin_value')->default(10);
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
