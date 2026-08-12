<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('question_duplicate_dismissals')) {
            return;
        }

        Schema::create('question_duplicate_dismissals', function (Blueprint $table) {
            $table->id();
            $table->string('group_key', 500)->unique();
            $table->json('question_ids');
            $table->string('type', 10)->default('near');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->timestamps();

            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_duplicate_dismissals');
    }
};
