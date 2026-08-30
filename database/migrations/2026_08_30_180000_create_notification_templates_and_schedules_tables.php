<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('channel', ['email', 'sms', 'fcm']);
            $table->string('title');
            $table->text('content');
            $table->enum('source', ['preset', 'admin'])->default('admin');
            $table->string('preset_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['channel', 'is_active']);
            $table->index(['source', 'created_by']);
        });

        Schema::create('notification_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_template_id')->constrained('notification_templates')->cascadeOnDelete();
            $table->enum('schedule_type', ['once', 'daily'])->default('daily');
            $table->dateTime('send_at')->nullable();
            $table->time('send_time')->nullable();
            $table->json('target_users')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'schedule_type']);
            $table->index('send_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_schedules');
        Schema::dropIfExists('notification_templates');
    }
};
