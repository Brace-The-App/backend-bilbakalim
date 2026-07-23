<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_password_resets')) {
            Schema::create('user_password_resets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('token_hash', 64);
                $table->timestamp('expires_at');
                $table->timestamps();

                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_password_resets');
    }
};
