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
        Schema::create('duels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenger_id')->constrained('users')->onDelete('cascade')->comment('Meydan okuyan kullanıcı');
            $table->foreignId('opponent_id')->nullable()->constrained('users')->onDelete('cascade')->comment('Rakip kullanıcı (rastgele seçilir)');
            $table->enum('multiplier', ['x1', 'x2', 'x4', 'x8'])->default('x1')->comment('Çarpan (X2, X4, X8)');
            $table->enum('status', ['waiting', 'active', 'finished', 'cancelled'])->default('waiting')->comment('Düello durumu');
            $table->integer('current_question_number')->default(0)->comment('Mevcut soru numarası');
            $table->foreignId('current_question_id')->nullable()->constrained('questions')->onDelete('set null')->comment('Mevcut soru');
            $table->integer('challenger_diamonds_before')->default(0)->comment('Meydan okuyanın başlangıç elması');
            $table->integer('opponent_diamonds_before')->default(0)->comment('Rakibin başlangıç elması');
            $table->integer('challenger_diamonds_after')->default(0)->comment('Meydan okuyanın bitiş elması');
            $table->integer('opponent_diamonds_after')->default(0)->comment('Rakibin bitiş elması');
            $table->integer('app_commission')->default(0)->comment('Uygulama komisyonu');
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null')->comment('Kazanan kullanıcı');
            $table->timestamp('started_at')->nullable()->comment('Başlangıç zamanı');
            $table->timestamp('finished_at')->nullable()->comment('Bitiş zamanı');
            $table->json('settings')->nullable()->comment('Ek ayarlar');
            $table->timestamps();
            
            $table->index('challenger_id');
            $table->index('opponent_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duels');
    }
};
