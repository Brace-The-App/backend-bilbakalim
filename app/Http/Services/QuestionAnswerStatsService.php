<?php

namespace App\Http\Services;

use App\Models\DuelAnswer;
use App\Models\GameAnswer;
use App\Models\Question;
use App\Models\QuestionAnswerStat;
use App\Models\TournamentUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuestionAnswerStatsService
{
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
        $minAnswers = (int) config('app.question_stats_min_answers', 20);
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
        $minAnswers = (int) config('app.question_stats_min_answers', 20);
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
}
