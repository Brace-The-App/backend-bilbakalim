<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->index(['status', 'finished_at'], 'duels_status_finished_at_index');
            $table->index(['challenger_id', 'status'], 'duels_challenger_id_status_index');
            $table->index(['opponent_id', 'status'], 'duels_opponent_id_status_index');
        });

        Schema::table('duel_answers', function (Blueprint $table) {
            $table->index(['user_id', 'id'], 'duel_answers_user_id_id_index');
            $table->index(['duel_id', 'user_id', 'question_id'], 'duel_answers_duel_user_question_index');
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_bot')) {
                $table->index('is_bot', 'users_is_bot_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->dropIndex('duels_status_finished_at_index');
            $table->dropIndex('duels_challenger_id_status_index');
            $table->dropIndex('duels_opponent_id_status_index');
        });

        Schema::table('duel_answers', function (Blueprint $table) {
            $table->dropIndex('duel_answers_user_id_id_index');
            $table->dropIndex('duel_answers_duel_user_question_index');
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_bot')) {
                $table->dropIndex('users_is_bot_index');
            }
        });
    }
};
