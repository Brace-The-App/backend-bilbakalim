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
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 8)->unique()->nullable()->after('avatar');
            $table->boolean('has_used_referral')->default(false)->after('referral_code');
        });

        // Mevcut kullanıcılar için referral kodu oluştur
        $users = \App\Models\User::whereNull('referral_code')->get();
        foreach ($users as $user) {
            $user->referral_code = \App\Models\User::generateReferralCode();
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['referral_code', 'has_used_referral']);
        });
    }
};
