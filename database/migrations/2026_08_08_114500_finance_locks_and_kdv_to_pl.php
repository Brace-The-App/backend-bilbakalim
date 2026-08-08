<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_month_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::table('finance_rate_periods', function (Blueprint $table) {
            $table->boolean('kdv_to_pl')->default(false)->after('kdv_pct');
        });
    }

    public function down(): void
    {
        Schema::table('finance_rate_periods', function (Blueprint $table) {
            $table->dropColumn('kdv_to_pl');
        });
        Schema::dropIfExists('finance_month_locks');
    }
};
