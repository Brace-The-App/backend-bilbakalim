<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameIfNeeded('duels', 'challenger_diamonds_before', 'challenger_coins_before');
        $this->renameIfNeeded('duels', 'opponent_diamonds_before', 'opponent_coins_before');
        $this->renameIfNeeded('duels', 'challenger_diamonds_after', 'challenger_coins_after');
        $this->renameIfNeeded('duels', 'opponent_diamonds_after', 'opponent_coins_after');

        $this->renameIfNeeded('duel_answers', 'diamonds_change', 'coins_change');
        $this->renameIfNeeded('duel_answers', 'diamonds_before', 'coins_before');
        $this->renameIfNeeded('duel_answers', 'diamonds_after', 'coins_after');
    }

    public function down(): void
    {
        $this->renameIfNeeded('duels', 'challenger_coins_before', 'challenger_diamonds_before');
        $this->renameIfNeeded('duels', 'opponent_coins_before', 'opponent_diamonds_before');
        $this->renameIfNeeded('duels', 'challenger_coins_after', 'challenger_diamonds_after');
        $this->renameIfNeeded('duels', 'opponent_coins_after', 'opponent_diamonds_after');

        $this->renameIfNeeded('duel_answers', 'coins_change', 'diamonds_change');
        $this->renameIfNeeded('duel_answers', 'coins_before', 'diamonds_before');
        $this->renameIfNeeded('duel_answers', 'coins_after', 'diamonds_after');
    }

    private function renameIfNeeded(string $table, string $from, string $to): void
    {
        if (Schema::hasColumn($table, $from) && !Schema::hasColumn($table, $to)) {
            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement("ALTER TABLE `{$table}` CHANGE `{$from}` `{$to}` INT NOT NULL DEFAULT 0");
                return;
            }

            if ($driver === 'pgsql') {
                DB::statement("ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}");
                return;
            }

            // SQLite / diğerleri
            DB::statement("ALTER TABLE {$table} RENAME COLUMN {$from} TO {$to}");
        }
    }
};
