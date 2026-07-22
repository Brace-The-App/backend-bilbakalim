<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            if (!Schema::hasColumn('questions', 'admin_status')) {
                $table->enum('admin_status', ['active', 'passive', 'maintenance'])
                    ->default('active')
                    ->after('is_active');
            }
        });

        // Mevcut is_active değerlerini admin_status ile senkronize et
        if (Schema::hasColumn('questions', 'admin_status')) {
            DB::table('questions')->where('is_active', 1)->update(['admin_status' => 'active']);
            DB::table('questions')->where('is_active', 0)->update(['admin_status' => 'passive']);
        }

        Schema::create('question_answer_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->unsignedInteger('total_answers')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->unsignedInteger('option_1_count')->default(0);
            $table->unsignedInteger('option_2_count')->default(0);
            $table->unsignedInteger('option_3_count')->default(0);
            $table->unsignedInteger('option_4_count')->default(0);
            $table->decimal('correct_percentage', 5, 2)->default(0);
            $table->enum('observed_difficulty', ['easy', 'medium', 'hard', 'insufficient'])->default('insufficient');
            $table->boolean('data_sufficient')->default(false);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique('question_id');
            $table->index(['correct_percentage', 'observed_difficulty'], 'qas_pct_obs_diff_idx');
        });

        Schema::create('question_admin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            // users.id bazı ortamlarda INT, bazılarında BIGINT olduğu için FK eklenmiyor
            $table->unsignedBigInteger('admin_id');
            $table->string('action', 50);
            $table->string('field', 50);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->timestamps();

            $table->index('admin_id');
            $table->index(['question_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_admin_logs');
        Schema::dropIfExists('question_answer_stats');

        Schema::table('questions', function (Blueprint $table) {
            if (Schema::hasColumn('questions', 'admin_status')) {
                $table->dropColumn('admin_status');
            }
        });
    }
};
