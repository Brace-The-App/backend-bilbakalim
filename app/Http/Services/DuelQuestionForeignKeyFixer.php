<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * duels / duel_answers FK'leri bazen hâlâ questions_old'a bakıyor.
 * Yeni questions ID'leri yazılınca 1452 Integrity hatası oluşuyor.
 */
class DuelQuestionForeignKeyFixer
{
    private const CACHE_KEY = 'duel_question_fk_fixed_v1';

    public function ensure(): void
    {
        if (Cache::get(self::CACHE_KEY)) {
            return;
        }

        try {
            if (!Schema::hasTable('questions')) {
                return;
            }

            $needsFix = $this->foreignKeysPointToQuestionsOld();
            if (!$needsFix) {
                $this->ensureTableColumnFk('duels', 'current_question_id', 'SET NULL');
                $this->ensureTableColumnFk('duel_answers', 'question_id', 'CASCADE');
                Cache::forever(self::CACHE_KEY, true);
                return;
            }

            $this->nullOrphan('duels', 'current_question_id');
            $this->deleteOrphan('duel_answers', 'question_id');
            $this->retargetAllFromQuestionsOld();
            $this->ensureTableColumnFk('duels', 'current_question_id', 'SET NULL');
            $this->ensureTableColumnFk('duel_answers', 'question_id', 'CASCADE');

            Cache::forever(self::CACHE_KEY, true);
            Log::info('Duel question foreign keys retargeted to questions table');
        } catch (\Throwable $e) {
            Log::error('Duel question FK fix failed', ['error' => $e->getMessage()]);
        }
    }

    public function forceClearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function foreignKeysPointToQuestionsOld(): bool
    {
        $row = DB::selectOne("
            SELECT COUNT(*) AS cnt
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME = 'questions_old'
              AND TABLE_NAME IN ('duels', 'duel_answers')
        ");

        return ((int) ($row->cnt ?? 0)) > 0;
    }

    private function retargetAllFromQuestionsOld(): void
    {
        $fks = DB::select("
            SELECT
                kcu.TABLE_NAME,
                kcu.COLUMN_NAME,
                kcu.CONSTRAINT_NAME,
                rc.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE kcu
            INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON rc.CONSTRAINT_SCHEMA = kcu.TABLE_SCHEMA
               AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.REFERENCED_TABLE_NAME = 'questions_old'
              AND kcu.REFERENCED_COLUMN_NAME = 'id'
        ");

        foreach ($fks as $fk) {
            $table = $fk->TABLE_NAME;
            $column = $fk->COLUMN_NAME;
            $constraint = $fk->CONSTRAINT_NAME;
            $onDelete = strtoupper((string) ($fk->DELETE_RULE ?: 'RESTRICT'));

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $nullable = DB::selectOne("
                SELECT IS_NULLABLE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
            ", [$table, $column]);

            if ($nullable && strtoupper($nullable->IS_NULLABLE) === 'YES') {
                $this->nullOrphan($table, $column);
            } else {
                $this->deleteOrphan($table, $column);
            }

            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraint}`");

            $this->alignColumnTypeToQuestionsId($table, $column, $nullable && strtoupper($nullable->IS_NULLABLE) === 'YES');

            $newName = "{$table}_{$column}_foreign";
            $exists = DB::selectOne("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND CONSTRAINT_NAME = ?
            ", [$table, $newName]);
            if ($exists) {
                $newName = "{$table}_{$column}_fk_questions";
            }

            DB::statement("
                ALTER TABLE `{$table}`
                ADD CONSTRAINT `{$newName}`
                FOREIGN KEY (`{$column}`)
                REFERENCES `questions` (`id`)
                ON DELETE {$onDelete}
            ");
        }
    }

    private function ensureTableColumnFk(string $table, string $column, string $onDelete): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        $existing = DB::selectOne("
            SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ", [$table, $column]);

        if ($existing && $existing->REFERENCED_TABLE_NAME === 'questions') {
            return;
        }

        if ($existing) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$existing->CONSTRAINT_NAME}`");
        }

        if ($onDelete === 'SET NULL') {
            $this->nullOrphan($table, $column);
        } else {
            $this->deleteOrphan($table, $column);
        }

        $this->alignColumnTypeToQuestionsId($table, $column, $onDelete === 'SET NULL');

        $fkName = "{$table}_{$column}_foreign";
        DB::statement("
            ALTER TABLE `{$table}`
            ADD CONSTRAINT `{$fkName}`
            FOREIGN KEY (`{$column}`)
            REFERENCES `questions` (`id`)
            ON DELETE {$onDelete}
        ");
    }

    private function alignColumnTypeToQuestionsId(string $table, string $column, bool $nullable): void
    {
        $ref = DB::selectOne("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'questions'
              AND COLUMN_NAME = 'id'
        ");
        $child = DB::selectOne("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ", [$table, $column]);

        if (!$ref || !$child) {
            return;
        }

        if (strtolower((string) $ref->COLUMN_TYPE) === strtolower((string) $child->COLUMN_TYPE)) {
            return;
        }

        $nullSql = $nullable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$ref->COLUMN_TYPE} {$nullSql}");
    }

    private function nullOrphan(string $table, string $column): void
    {
        DB::statement("
            UPDATE `{$table}` t
            LEFT JOIN `questions` q ON q.id = t.`{$column}`
            SET t.`{$column}` = NULL
            WHERE t.`{$column}` IS NOT NULL AND q.id IS NULL
        ");
    }

    private function deleteOrphan(string $table, string $column): void
    {
        DB::statement("
            DELETE t FROM `{$table}` t
            LEFT JOIN `questions` q ON q.id = t.`{$column}`
            WHERE t.`{$column}` IS NOT NULL AND q.id IS NULL
        ");
    }
}
