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
        Schema::table('individual_games', function (Blueprint $table) {
            $table->integer('current_question_number')->default(1)->after('settings');
            $table->json('jokers_used')->nullable()->after('current_question_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('individual_games', function (Blueprint $table) {
            $table->dropColumn(['current_question_number', 'jokers_used']);
        });
    }
};
