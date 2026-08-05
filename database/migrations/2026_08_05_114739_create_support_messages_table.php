<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            /** landing | app | web_player */
            $table->string('source', 32);
            /** contact | complaint | suggestion | job | other */
            $table->string('type', 32);
            $table->string('name', 120)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('subject', 200)->nullable();
            $table->text('message');
            /** new | read | archived */
            $table->string('status', 20)->default('new');
            $table->string('platform', 32)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['source', 'type']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};
