<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (!Schema::hasColumn('ads', 'reward_coins')) {
                $table->unsignedSmallInteger('reward_coins')->default(1)->after('sort_order');
            }
            if (!Schema::hasColumn('ads', 'cta_text')) {
                $table->string('cta_text', 120)->nullable()->after('link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'reward_coins')) {
                $table->dropColumn('reward_coins');
            }
            if (Schema::hasColumn('ads', 'cta_text')) {
                $table->dropColumn('cta_text');
            }
        });
    }
};
