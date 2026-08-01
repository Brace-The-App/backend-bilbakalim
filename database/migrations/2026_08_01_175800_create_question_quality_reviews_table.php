<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_quality_reviews', function (Blueprint $table) {
            $table->id();
            // questions.id = int(11) signed — foreignId (bigint unsigned) FK kırıyor
            $table->integer('question_id');
            $table->string('status', 32)->default('pending')->index(); // pending|reviewed|failed|expired
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('package', 64)->nullable();
            $table->string('external_job_id', 128)->nullable()->index();

            $table->unsignedTinyInteger('quality_score')->nullable(); // 0-100
            $table->string('quality_band', 32)->nullable()->index(); // high|medium|low|very_low|reject
            $table->string('recommended_action', 32)->nullable()->index(); // approve|edit|reject
            $table->string('estimated_difficulty', 32)->nullable();

            $table->unsignedTinyInteger('boredom_risk')->nullable();
            $table->unsignedTinyInteger('ambiguity_risk')->nullable();
            $table->unsignedTinyInteger('duplicate_risk')->nullable();
            $table->unsignedTinyInteger('knowledge_confidence')->nullable();

            $table->json('criteria_scores')->nullable();
            $table->text('edit_reason')->nullable();
            $table->json('revised_content')->nullable();
            $table->json('question_snapshot')->nullable();
            $table->json('raw_response')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->index(['question_id', 'status']);
            $table->index(['status', 'assigned_at']);
            $table->index(['quality_score', 'quality_band']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_quality_reviews');
    }
};
