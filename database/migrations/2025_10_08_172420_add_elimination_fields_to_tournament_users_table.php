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
        Schema::table('tournament_users', function (Blueprint $table) {
            $table->timestamp('eliminated_at')->nullable();
            $table->string('elimination_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_users', function (Blueprint $table) {
            $table->dropColumn(['eliminated_at', 'elimination_reason']);
        });
    }
};