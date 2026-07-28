<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reward_requests MODIFY reward_type ENUM('daily','weekly','tournament','duel') NOT NULL DEFAULT 'duel'");
    }

    public function down(): void
    {
        DB::table('reward_requests')->where('reward_type', 'duel')->update(['reward_type' => 'daily']);
        DB::statement("ALTER TABLE reward_requests MODIFY reward_type ENUM('daily','weekly','tournament') NOT NULL DEFAULT 'daily'");
    }
};
