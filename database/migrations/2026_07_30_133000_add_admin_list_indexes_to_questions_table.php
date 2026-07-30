<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'duel_earned_coins')) {
                $table->unsignedBigInteger('duel_earned_coins')->default(0)->after('total_coins');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'duel_earned_coins')) {
                $table->dropColumn('duel_earned_coins');
            }
        });
    }
};
