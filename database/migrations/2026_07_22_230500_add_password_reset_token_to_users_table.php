<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'password_reset_token')) {
                $table->string('password_reset_token', 64)->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'password_reset_expires_at')) {
                $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'password_reset_expires_at')) {
                $table->dropColumn('password_reset_expires_at');
            }
            if (Schema::hasColumn('users', 'password_reset_token')) {
                $table->dropColumn('password_reset_token');
            }
        });
    }
};
