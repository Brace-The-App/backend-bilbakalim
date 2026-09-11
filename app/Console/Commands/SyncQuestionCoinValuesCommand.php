<?php

namespace App\Console\Commands;

use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mevcut soruların coin_value değerini question_level ile hizalar.
 * Kolay=1, Orta=2, Zor=3 (config: app.coin_values_by_level).
 *
 * Çalıştırmak approval gerektirir (toplu UPDATE).
 */
class SyncQuestionCoinValuesCommand extends Command
{
    protected $signature = 'questions:sync-coin-values
                            {--dry-run : Sadece sayıları göster, yazma}
                            {--level= : Sadece easy|medium|hard}';

    protected $description = 'Sync questions.coin_value from question_level (easy=1, medium=2, hard=3)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyLevel = $this->option('level');

        $levels = ['easy', 'medium', 'hard'];
        if ($onlyLevel !== null && $onlyLevel !== '') {
            if (!in_array($onlyLevel, $levels, true)) {
                $this->error('level must be easy|medium|hard');

                return self::FAILURE;
            }
            $levels = [$onlyLevel];
        }

        $this->info($dryRun ? 'DRY-RUN' : 'APPLY');
        $totalWould = 0;
        $totalUpdated = 0;

        foreach ($levels as $level) {
            $coins = Question::coinValueForLevel($level);
            if ($coins === null) {
                continue;
            }

            $mismatch = Question::query()
                ->where('question_level', $level)
                ->where('coin_value', '!=', $coins);

            $count = (clone $mismatch)->count();
            $totalWould += $count;
            $this->line("{$level} → coin={$coins}: mismatch={$count}");

            if (!$dryRun && $count > 0) {
                $updated = $mismatch->update([
                    'coin_value' => $coins,
                    'updated_at' => now(),
                ]);
                $totalUpdated += $updated;
                $this->info("  updated={$updated}");
            }
        }

        // Doğrulama özeti
        $rows = DB::table('questions')
            ->selectRaw('question_level, coin_value, COUNT(*) as cnt')
            ->whereNull('deleted_at')
            ->groupBy('question_level', 'coin_value')
            ->orderBy('question_level')
            ->orderBy('coin_value')
            ->get();

        $this->newLine();
        $this->table(['level', 'coin_value', 'count'], $rows->map(fn ($r) => [
            $r->question_level,
            $r->coin_value,
            $r->cnt,
        ])->all());

        $this->info($dryRun
            ? "Would update {$totalWould} rows. Re-run without --dry-run after approval."
            : "Updated {$totalUpdated} rows.");

        return self::SUCCESS;
    }
}
