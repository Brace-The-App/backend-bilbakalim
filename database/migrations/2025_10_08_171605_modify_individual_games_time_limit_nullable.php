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
            $table->integer('time_limit_seconds')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('individual_games', function (Blueprint $table) {
            $table->integer('time_limit_seconds')->nullable(false)->change();
        });
    }
};