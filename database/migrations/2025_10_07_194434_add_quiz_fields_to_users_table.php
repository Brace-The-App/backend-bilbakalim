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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('coins')->default(0)->after('total_coins');
            $table->boolean('is_premium')->default(false)->after('coins');
            $table->integer('fifty_fifty_jokers')->default(0)->after('is_premium');
            $table->integer('double_answer_jokers')->default(0)->after('fifty_fifty_jokers');
            $table->integer('hint_jokers')->default(0)->after('double_answer_jokers');
            $table->string('avatar')->nullable()->after('hint_jokers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'coins',
                'is_premium',
                'fifty_fifty_jokers',
                'double_answer_jokers',
                'hint_jokers',
                'avatar'
            ]);
        });
    }
};
