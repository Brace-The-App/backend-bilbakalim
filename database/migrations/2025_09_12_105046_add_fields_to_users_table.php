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
            $table->string('phone')->nullable()->after('email');
            $table->string('profile_image')->nullable()->after('phone');
            $table->boolean('auto_question')->default(true)->after('profile_image');
            $table->boolean('game_sound')->default(true)->after('auto_question');
            $table->boolean('face_id')->default(false)->after('game_sound');
            $table->boolean('fingerprint')->default(false)->after('face_id');
            $table->foreignId('package_id')->nullable()->constrained('packages')->after('fingerprint');
            $table->integer('role_id')->default(2)->after('package_id'); // 1: admin, 2: user
            $table->integer('total_coins')->default(0)->after('role_id');
            $table->datetime('last_login_at')->nullable()->after('total_coins');
            $table->string('device_token')->nullable()->after('last_login_at');
            $table->enum('account_status', ['active', 'suspended', 'pending'])->default('active')->after('device_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'profile_image', 'auto_question', 'game_sound',
                'face_id', 'fingerprint', 'package_id', 'role_id',
                'total_coins', 'last_login_at', 'device_token', 'account_status'
            ]);
        });
    }
};
