<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_ad_views', function (Blueprint $table) {
            if (!Schema::hasColumn('user_ad_views', 'window_started_at')) {
                $table->timestamp('window_started_at')->nullable()->after('last_viewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_ad_views', function (Blueprint $table) {
            if (Schema::hasColumn('user_ad_views', 'window_started_at')) {
                $table->dropColumn('window_started_at');
            }
        });
    }
};
