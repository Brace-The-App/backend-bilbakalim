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
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('tournament_type')->default('individual')->after('is_featured'); // individual, multiplayer
            $table->integer('max_participants')->default(100)->after('quota');
            $table->integer('current_participants')->default(0)->after('max_participants');
            $table->boolean('is_ranked')->default(true)->after('current_participants');
            $table->string('ranking_type')->default('speed')->after('is_ranked'); // speed, accuracy, score
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn([
                'tournament_type',
                'max_participants',
                'current_participants',
                'is_ranked',
                'ranking_type'
            ]);
        });
    }
};