<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->nullable()->after('last_login_at');
        });

        // Mevcut kayıtlar için makul başlangıç değeri
        DB::table('users')->whereNull('last_active_at')->update([
            'last_active_at' => DB::raw('COALESCE(last_login_at, updated_at, created_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_active_at');
        });
    }
};
