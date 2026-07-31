<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'video_url')) {
                $table->dropColumn('video_url');
            }
            if (!Schema::hasColumn('ads', 'video_path')) {
                $table->string('video_path')->nullable()->after('link');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            if (Schema::hasColumn('ads', 'video_path')) {
                $table->dropColumn('video_path');
            }
            if (!Schema::hasColumn('ads', 'video_url')) {
                $table->string('video_url', 500)->nullable()->after('link');
            }
        });
    }
};
