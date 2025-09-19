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
        Schema::table('questions', function (Blueprint $table) {
            // Make question fields nullable for translation support
            $table->text('question')->nullable()->change();
            $table->string('one_choice')->nullable()->change();
            $table->string('two_choice')->nullable()->change();
            $table->string('three_choice')->nullable()->change();
            $table->string('four_choice')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('question')->nullable(false)->change();
            $table->string('one_choice')->nullable(false)->change();
            $table->string('two_choice')->nullable(false)->change();
            $table->string('three_choice')->nullable(false)->change();
            $table->string('four_choice')->nullable(false)->change();
        });
    }
};
