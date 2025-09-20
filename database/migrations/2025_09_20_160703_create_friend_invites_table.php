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
        Schema::create('friend_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('invited_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('invite_code')->unique();
            $table->string('invite_link');
            $table->string('phone_number')->nullable();
            $table->string('email')->nullable();
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
            $table->integer('reward_coins')->default(0);
            $table->integer('bonus_coins')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['inviter_id', 'status']);
            $table->index(['invite_code']);
            $table->index(['phone_number']);
            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friend_invites');
    }
};