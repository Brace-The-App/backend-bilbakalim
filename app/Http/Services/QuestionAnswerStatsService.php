<?php

namespace App\Http\Services;

use App\Models\DuelAnswer;
use App\Models\GameAnswer;
use App\Models\GeneralSetting;
use App\Models\Question;
use App\Models\QuestionAdminLog;
use App\Models\QuestionAnswerStat;
use App\Models\TournamentUser;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionAnswerStatsService
{
    public const AUTO_FIX_MISMATCH_KEY = 'qas_auto_fix_mismatch';
    public const AUTO_FIX_MISMATCH_BY_KEY = 'qas_auto_fix_mismatch_by';

    public static function isAutoFixMismatchEnabled(): bool
    {
        return GeneralSetting::get(self::AUTO_FIX_MISMATCH_KEY, '0') === '1';
    }

    public static function setAutoFixMismatchEnabled(bool $enabled, ?int $adminId = null): void
    {
        GeneralSetting::set(
            self::AUTO_FIX_MISMATCH_KEY,
            $enabled ? '1' : '0',
            'boolean',
            'Güvenilir uyumsuz zorlukları otomatik düzelt (dakikada 1)'
        );

        if ($enabled && $adminId) {
            GeneralSetting::set(
                self::AUTO_FIX_MISMATCH_BY_KEY,
                (string) $adminId,
                'number',
                'Otomatik zorluk düzeltmesini açan admin'
            );
        }
    }

    public static function autoFixMismatchActorId(): ?int
    {
        $raw = GeneralSetting::get(self::AUTO_FIX_MISMATCH_BY_KEY);
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    /**
     * Güvenilir uyumsuzlardan bir soruyu gözlenen zorluğa çeker.
     * Kapalıysa veya aday yoksa null döner (cron boşuna iş yapmasın).
     *
     * @return array{question_id:int,old:string,new:string}|null
     */
    public function fixOneReliableMismatch(): ?array
    {
        if (!self::isAutoFixMismatchEnabled()) {
            return null;
        }

        $adminId = self::autoFixMismatchActorId();
        if (!$adminId) {
            Log::warning('QAS auto-fix mismatch: enabled but actor admin_id missing');

            return null;
        }

        $row = DB::table('questions')
            ->join('question_answer_stats as qas', 'questions.id', '=', 'qas.question_id')
            ->where('qas.total_answers', '>=', 5)
            ->where('qas.data_sufficient', true)
            ->whereIn('qas.observed_difficulty', ['easy', 'medium', 'hard'])
            ->whereColumn('questions.question_level', '!=', 'qas.observed_difficulty')
            ->orderBy('questions.id')
            ->select([
                'questions.id',
                'questions.question_level',
                'qas.observed_difficulty',
            ])
            ->first();

        if (!$row) {
            return null;
        }

        $question = Question::query()->find((int) $row->id);
        if (!$question) {
            return null;
        }

        $old = (string) $question->question_level;
        $new = (string) $row->observed_difficulty;

        if ($old === $new || !in_array($new, ['easy', 'medium', 'hard'], true)) {
            return null;
        }

        DB::transaction(function () use ($question, $old, $new, $adminId) {
            $question->update(['question_level' => $new]);

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => $adminId,
                'action' => 'auto_fix_level',
                'field' => 'question_level',
                'old_value' => $old,
                'new_value' => $new,
            ]);
        });

        Cache::forget('qas.chart_data.v1');

        return [
            'question_id' => (int) $question->id,
            'old' => $old,
            'new' => $new,
        ];
    }

    public function refreshAll(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        $bucket = [];

        try {
            $this->mergeGroupedAnswers(
                GameAnswer::query()
                    ->select('question_id', 'selected_option', 'is_correct', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('question_id', 'selected_option', 'is_correct')
                    ->get(),
                'selected_option',
                $bucket
            );

            $this->mergeGroupedAnswers(
                DuelAnswer::query()
                    ->select('question_id', 'selected_answer', 'is_correct', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('question_id', 'selected_answer', 'is_correct')
                    ->get(),
                'selected_answer',
                $bucket
            );

            $this->mergeTournamentAnswersAll($bucket);
        } catch (\Throwable $e) {
            Log::error('Question answer stats aggregation failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }

        $now = now()->toDateTimeString();
        $minAnswers = (int) config('app.question_stats_min_answers', 1);
        $easyMin = (float) config('app.question_stats_easy_min_percent', 70);
        $mediumMin = (float) config('app.question_stats_medium_min_percent', 40);

        $updated = 0;
        $rows = [];

        Question::query()->orderBy('id')->chunkById(500, function ($questions) use (
            &$bucket,
            &$rows,
            &$updated,
            $now,
            $minAnswers,
            $easyMin,
            $mediumMin
        ) {
            foreach ($questions as $question) {
                $totals = $bucket[$question->id] ?? $this->emptyTotals();
                $rows[] = $this->buildUpsertRow((int) $question->id, $totals, $now, $minAnswers, $easyMin, $mediumMin);
                $updated++;
                unset($bucket[$question->id]);
            }

            if (count($rows) >= 500) {
                $this->upsertChunk($rows);
                $rows = [];
            }
        });

        if (!empty($rows)) {
            $this->upsertChunk($rows);
        }

        return $updated;
    }

    public function refreshQuestion(int $questionId): QuestionAnswerStat
    {
        $totals = $this->emptyTotals();

        $this->accumulateGameAnswers($questionId, $totals);
        $this->accumulateDuelAnswers($questionId, $totals);
        $this->accumulateTournamentAnswers($questionId, $totals);

        return $this->persistStats($questionId, $totals, now());
    }

    private function emptyTotals(): array
    {
        return [
            'total' => 0,
            'correct' => 0,
            'wrong' => 0,
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
        ];
    }

    private function buildUpsertRow(
        int $questionId,
        array $totals,
        string $calculatedAt,
        int $minAnswers,
        float $easyMin,
        float $mediumMin
    ): array {
        $percentage = $totals['total'] > 0
            ? round(($totals['correct'] / $totals['total']) * 100, 2)
            : 0.0;

        $dataSufficient = $totals['total'] >= $minAnswers;

        if (!$dataSufficient) {
            $observed = 'insufficient';
        } elseif ($percentage >= $easyMin) {
            $observed = 'easy';
        } elseif ($percentage >= $mediumMin) {
            $observed = 'medium';
        } else {
            $observed = 'hard';
        }

        return [
            'question_id' => $questionId,
            'total_answers' => $totals['total'],
            'correct_count' => $totals['correct'],
            'wrong_count' => $totals['wrong'],
            'option_1_count' => $totals['1'],
            'option_2_count' => $totals['2'],
            'option_3_count' => $totals['3'],
            'option_4_count' => $totals['4'],
            'correct_percentage' => $percentage,
            'observed_difficulty' => $observed,
            'data_sufficient' => $dataSufficient ? 1 : 0,
            'last_calculated_at' => $calculatedAt,
            'created_at' => $calculatedAt,
            'updated_at' => $calculatedAt,
        ];
    }

    private function upsertChunk(array $rows): void
    {
        QuestionAnswerStat::upsert(
            $rows,
            ['question_id'],
            [
                'total_answers',
                'correct_count',
                'wrong_count',
                'option_1_count',
                'option_2_count',
                'option_3_count',
                'option_4_count',
                'correct_percentage',
                'observed_difficulty',
                'data_sufficient',
                'last_calculated_at',
                'updated_at',
            ]
        );
    }

    private function persistStats(int $questionId, array $totals, $calculatedAt): QuestionAnswerStat
    {
        $minAnswers = (int) config('app.question_stats_min_answers', 1);
        $easyMin = (float) config('app.question_stats_easy_min_percent', 70);
        $mediumMin = (float) config('app.question_stats_medium_min_percent', 40);

        $row = $this->buildUpsertRow(
            $questionId,
            $totals,
            $calculatedAt instanceof \DateTimeInterface ? $calculatedAt->format('Y-m-d H:i:s') : (string) $calculatedAt,
            $minAnswers,
            $easyMin,
            $mediumMin
        );

        $this->upsertChunk([$row]);

        return QuestionAnswerStat::where('question_id', $questionId)->firstOrFail();
    }

    private function mergeGroupedAnswers($rows, string $optionField, array &$bucket): void
    {
        foreach ($rows as $row) {
            $qid = (int) $row->question_id;
            if (!isset($bucket[$qid])) {
                $bucket[$qid] = $this->emptyTotals();
            }

            $count = (int) $row->cnt;
            $bucket[$qid]['total'] += $count;

            if ($row->is_correct) {
                $bucket[$qid]['correct'] += $count;
            } else {
                $bucket[$qid]['wrong'] += $count;
            }

            $option = (string) $row->{$optionField};
            if (in_array($option, ['1', '2', '3', '4'], true)) {
                $bucket[$qid][$option] += $count;
            }
        }
    }

    private function mergeTournamentAnswersAll(array &$bucket): void
    {
        TournamentUser::query()
            ->whereNotNull('answers_detail')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$bucket) {
                foreach ($rows as $row) {
                    $details = $row->answers_detail;
                    if (!is_array($details)) {
                        continue;
                    }

                    foreach ($details as $key => $entry) {
                        if ($key === 'jokers' || !is_array($entry)) {
                            continue;
                        }

                        if (($entry['is_pending'] ?? false) === true) {
                            continue;
                        }

                        $qid = (int) ($entry['question_id'] ?? 0);
                        if ($qid <= 0) {
                            continue;
                        }

                        if (!isset($bucket[$qid])) {
                            $bucket[$qid] = $this->emptyTotals();
                        }

                        $bucket[$qid]['total']++;

                        if (!empty($entry['is_correct'])) {
                            $bucket[$qid]['correct']++;
                        } else {
                            $bucket[$qid]['wrong']++;
                        }

                        $option = isset($entry['selected_option']) ? (string) $entry['selected_option'] : null;
                        if (in_array($option, ['1', '2', '3', '4'], true)) {
                            $bucket[$qid][$option]++;
                        }
                    }
                }
            });
    }

    private function accumulateGameAnswers(int $questionId, array &$totals): void
    {
        $rows = GameAnswer::query()
            ->where('question_id', $questionId)
            ->select('selected_option', 'is_correct', DB::raw('COUNT(*) as cnt'))
            ->groupBy('selected_option', 'is_correct')
            ->get();

        foreach ($rows as $row) {
            $count = (int) $row->cnt;
            $totals['total'] += $count;
            $row->is_correct ? $totals['correct'] += $count : $totals['wrong'] += $count;

            $option = (string) $row->selected_option;
            if (in_array($option, ['1', '2', '3', '4'], true)) {
                $totals[$option] += $count;
            }
        }
    }

    private function accumulateDuelAnswers(int $questionId, array &$totals): void
    {
        $rows = DuelAnswer::query()
            ->where('question_id', $questionId)
            ->select('selected_answer', 'is_correct', DB::raw('COUNT(*) as cnt'))
            ->groupBy('selected_answer', 'is_correct')
            ->get();

        foreach ($rows as $row) {
            $count = (int) $row->cnt;
            $totals['total'] += $count;
            $row->is_correct ? $totals['correct'] += $count : $totals['wrong'] += $count;

            $option = (string) $row->selected_answer;
            if (in_array($option, ['1', '2', '3', '4'], true)) {
                $totals[$option] += $count;
            }
        }
    }

    private function accumulateTournamentAnswers(int $questionId, array &$totals): void
    {
        TournamentUser::query()
            ->whereNotNull('answers_detail')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($questionId, &$totals) {
                foreach ($rows as $row) {
                    $details = $row->answers_detail;
                    if (!is_array($details)) {
                        continue;
                    }

                    foreach ($details as $key => $entry) {
                        if ($key === 'jokers' || !is_array($entry)) {
                            continue;
                        }

                        if (($entry['is_pending'] ?? false) === true) {
                            continue;
                        }

                        if ((int) ($entry['question_id'] ?? 0) !== $questionId) {
                            continue;
                        }

                        $totals['total']++;
                        !empty($entry['is_correct']) ? $totals['correct']++ : $totals['wrong']++;

                        $option = isset($entry['selected_option']) ? (string) $entry['selected_option'] : null;
                        if (in_array($option, ['1', '2', '3', '4'], true)) {
                            $totals[$option]++;
                        }
                    }
                }
            });
    }

    /**
     * Belirli bir şıkkı seçen kullanıcıları (oyun + düello + turnuva), en yeniden eskiye, sayfalı.
     *
     * @return array{data: array<int, array>, total: int, page: int, per_page: int, last_page: int}
     */
    public function optionAnswerers(int $questionId, string $option, int $page = 1, int $perPage = 10): array
    {
        $option = (string) $option;
        if (!in_array($option, ['1', '2', '3', '4'], true)) {
            return [
                'data' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'last_page' => 1,
            ];
        }

        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $rows = [];

        $gameRows = GameAnswer::query()
            ->with([
                'individualGame:id,game_type',
                'gameSession:id,game_type,individual_game_id',
            ])
            ->where('question_id', $questionId)
            ->where('selected_option', $option)
            ->get(['user_id', 'is_correct', 'answered_at', 'created_at', 'individual_game_id', 'game_session_id']);

        foreach ($gameRows as $row) {
            $gameType = $row->individualGame->game_type
                ?? $row->gameSession->game_type
                ?? 'normal';

            $rows[] = [
                'user_id' => (int) $row->user_id,
                'is_correct' => (bool) $row->is_correct,
                'answered_at' => $row->answered_at?->toDateTimeString()
                    ?? $row->created_at?->toDateTimeString(),
                'source' => 'game',
                'source_key' => (string) $gameType,
                'source_label' => $this->sourceLabel('game', (string) $gameType),
            ];
        }

        $duelRows = DuelAnswer::query()
            ->where('question_id', $questionId)
            ->where('selected_answer', $option)
            ->get(['user_id', 'is_correct', 'answered_at', 'created_at']);

        foreach ($duelRows as $row) {
            $rows[] = [
                'user_id' => (int) $row->user_id,
                'is_correct' => (bool) $row->is_correct,
                'answered_at' => $row->answered_at?->toDateTimeString()
                    ?? $row->created_at?->toDateTimeString(),
                'source' => 'duel',
                'source_key' => 'duel',
                'source_label' => $this->sourceLabel('duel'),
            ];
        }

        TournamentUser::query()
            ->with('tournament:id,title')
            ->whereNotNull('answers_detail')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($questionId, $option, &$rows) {
                foreach ($chunk as $tu) {
                    $details = $tu->answers_detail;
                    if (!is_array($details)) {
                        continue;
                    }

                    foreach ($details as $key => $entry) {
                        if ($key === 'jokers' || !is_array($entry)) {
                            continue;
                        }
                        if (($entry['is_pending'] ?? false) === true) {
                            continue;
                        }
                        if ((int) ($entry['question_id'] ?? 0) !== $questionId) {
                            continue;
                        }
                        if ((string) ($entry['selected_option'] ?? '') !== $option) {
                            continue;
                        }

                        $answeredAt = $entry['answered_at']
                            ?? optional($tu->finished_at)->toDateTimeString()
                            ?? optional($tu->updated_at)->toDateTimeString();

                        if ($answeredAt instanceof \DateTimeInterface) {
                            $answeredAt = $answeredAt->format('Y-m-d H:i:s');
                        } elseif (is_string($answeredAt) && $answeredAt !== '') {
                            try {
                                $answeredAt = \Carbon\Carbon::parse($answeredAt)->toDateTimeString();
                            } catch (\Throwable $e) {
                                // keep raw
                            }
                        } else {
                            $answeredAt = null;
                        }

                        $tournamentTitle = null;
                        if ($tu->tournament) {
                            $title = $tu->tournament->title;
                            if (is_array($title)) {
                                $tournamentTitle = $title['tr'] ?? $title['en'] ?? (reset($title) ?: null);
                            } elseif (is_string($title) && $title !== '') {
                                $tournamentTitle = $title;
                            }
                        }

                        $rows[] = [
                            'user_id' => (int) $tu->user_id,
                            'is_correct' => !empty($entry['is_correct']),
                            'answered_at' => $answeredAt,
                            'source' => 'tournament',
                            'source_key' => 'tournament',
                            'source_label' => $tournamentTitle
                                ? ('Turnuva · ' . $tournamentTitle)
                                : $this->sourceLabel('tournament'),
                        ];
                    }
                }
            });

        usort($rows, function ($a, $b) {
            $ta = $a['answered_at'] ?? '';
            $tb = $b['answered_at'] ?? '';
            if ($ta === $tb) {
                return ($b['user_id'] ?? 0) <=> ($a['user_id'] ?? 0);
            }

            return $tb <=> $ta;
        });

        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $slice = array_slice($rows, ($page - 1) * $perPage, $perPage);
        $userIds = array_values(array_unique(array_filter(array_column($slice, 'user_id'))));
        $users = User::withTrashed()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email', 'deleted_at'])
            ->keyBy('id');

        $data = array_map(function (array $row) use ($users) {
            $user = $users->get($row['user_id']);
            $name = $user ? trim((string) ($user->name ?? '')) : null;

            return [
                'user_id' => $row['user_id'],
                'name' => $name !== '' && $name !== null ? $name : ('Kullanıcı #' . $row['user_id']),
                'email' => $user->email ?? null,
                'is_deleted' => $user ? ($user->deleted_at !== null) : false,
                'is_correct' => $row['is_correct'],
                'answered_at' => $row['answered_at'],
                'answered_at_label' => $row['answered_at']
                    ? \Carbon\Carbon::parse($row['answered_at'])->format('d.m.Y H:i')
                    : '-',
                'source' => $row['source_key'] ?? $row['source'],
                'source_label' => $row['source_label']
                    ?? $this->sourceLabel($row['source'] ?? '', $row['source_key'] ?? null),
            ];
        }, $slice);

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }

    private function sourceLabel(string $source, ?string $gameType = null): string
    {
        if ($source === 'duel') {
            return 'Düello';
        }

        if ($source === 'tournament') {
            return 'Turnuva';
        }

        $type = $gameType ?: 'normal';
        $names = [
            'normal' => 'Günlük Quiz',
            'premium' => 'Premium Quiz',
            'tournament' => 'Turnuva',
            'daily_challenge' => 'Günlük Mücadele',
            'individual' => 'Bireysel Oyun',
            'practice' => 'Alıştırma',
        ];

        return $names[$type] ?? ('Oyun · ' . $type);
    }
}
