<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->timestamp('email_replied_at')->nullable()->after('admin_note');
            $table->text('last_email_reply')->nullable()->after('email_replied_at');
            $table->string('last_email_from', 120)->nullable()->after('last_email_reply');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn(['email_replied_at', 'last_email_reply', 'last_email_from']);
        });
    }
};
