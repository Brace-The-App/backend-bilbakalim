<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        DB::table('finance_expense_categories')->insert([
            [
                'name' => 'Ödül / hediye',
                'slug' => 'gift',
                'is_system' => 1,
                'is_active' => 1,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Reklam bütçesi',
                'slug' => 'ad_spend',
                'is_system' => 1,
                'is_active' => 1,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'KDV (manuel fatura)',
                'slug' => 'kdv_manual',
                'is_system' => 1,
                'is_active' => 1,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Diğer',
                'slug' => 'other',
                'is_system' => 1,
                'is_active' => 1,
                'sort_order' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_expense_categories');
    }
};
