<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_quality_reviews', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempt')->default(1)->after('status');
            $table->unsignedBigInteger('previous_review_id')->nullable()->after('attempt');
            $table->index(['status', 'attempt']);
            $table->index('previous_review_id');
        });

        // Mevcut failed/reviewed satırlarını deneme=1 kabul et (default zaten 1)
        DB::table('question_quality_reviews')->whereNull('attempt')->update(['attempt' => 1]);
    }

    public function down(): void
    {
        Schema::table('question_quality_reviews', function (Blueprint $table) {
            $table->dropIndex(['status', 'attempt']);
            $table->dropIndex(['previous_review_id']);
            $table->dropColumn(['attempt', 'previous_review_id']);
        });
    }
};
