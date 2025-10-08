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
        Schema::table('game_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('individual_game_id')->nullable()->after('game_session_id');
            $table->string('selected_option')->nullable()->after('user_answer');
            $table->string('joker_used')->nullable()->after('is_joker_used');
            $table->integer('time_spent')->nullable()->after('time_taken');
            
            $table->foreign('individual_game_id')->references('id')->on('individual_games')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_answers', function (Blueprint $table) {
            $table->dropForeign(['individual_game_id']);
            $table->dropColumn([
                'individual_game_id',
                'selected_option',
                'joker_used',
                'time_spent'
            ]);
        });
    }
};
