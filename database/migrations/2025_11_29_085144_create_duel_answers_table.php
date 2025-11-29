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
        Schema::create('duel_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duel_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->string('selected_answer')->comment('Seçilen cevap (1, 2, 3, 4)');
            $table->boolean('is_correct')->default(false)->comment('Cevap doğru mu?');
            $table->integer('diamonds_change')->default(0)->comment('Elmas değişimi (+ veya -)');
            $table->integer('diamonds_before')->default(0)->comment('Cevap öncesi elmas');
            $table->integer('diamonds_after')->default(0)->comment('Cevap sonrası elmas');
            $table->integer('question_value')->default(10)->comment('Soru değeri (multiplier ile çarpılmış)');
            $table->timestamp('answered_at')->nullable()->comment('Cevap verilme zamanı');
            $table->timestamps();
            
            $table->index('duel_id');
            $table->index('user_id');
            $table->index('question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duel_answers');
    }
};
