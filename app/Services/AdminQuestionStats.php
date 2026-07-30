<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminQuestionStats
{
    private const SUMMARY_KEY = 'admin.questions.summary';
    private const VERSION_KEY = 'admin.questions.stats_version';
    private const TTL = 900;

    public static function bump(): void
    {
        Cache::forget(self::SUMMARY_KEY);
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function summary(): array
    {
        return Cache::remember(self::SUMMARY_KEY, self::TTL, function () {
            $row = DB::table('questions')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = 0 OR is_active IS NULL THEN 1 ELSE 0 END) as passive,
                    SUM(CASE WHEN question_level = 'easy' THEN 1 ELSE 0 END) as easy,
                    SUM(CASE WHEN question_level = 'medium' THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN question_level = 'hard' THEN 1 ELSE 0 END) as hard,
                    SUM(CASE WHEN `check` = 0 OR `check` IS NULL THEN 1 ELSE 0 END) as unchecked
                ")
                ->first();

            return [
                'total' => (int) ($row->total ?? 0),
                'active' => (int) ($row->active ?? 0),
                'passive' => (int) ($row->passive ?? 0),
                'easy' => (int) ($row->easy ?? 0),
                'medium' => (int) ($row->medium ?? 0),
                'hard' => (int) ($row->hard ?? 0),
                'unchecked' => (int) ($row->unchecked ?? 0),
            ];
        });
    }

    /**
     * @return array{filtered_total:int, language_counts:array<string,int>, bilingual:int, tr_only:int, en_only:int}
     */
    public static function filterStats(Builder $query, array $filterSignature): array
    {
        $key = 'admin.questions.filter_stats.' . self::version() . '.' . md5(json_encode($filterSignature));

        return Cache::remember($key, self::TTL, function () use ($query) {
            $row = (clone $query)->toBase()
                ->selectRaw("
                    COUNT(*) as filtered_total,
                    SUM(CASE WHEN TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')), '')) != '' THEN 1 ELSE 0 END) as tr_count,
                    SUM(CASE WHEN TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.en')), '')) != '' THEN 1 ELSE 0 END) as en_count,
                    SUM(CASE WHEN TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')), '')) != ''
                              AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.en')), '')) != '' THEN 1 ELSE 0 END) as bilingual,
                    SUM(CASE WHEN TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')), '')) != ''
                              AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.en')), '')) = '' THEN 1 ELSE 0 END) as tr_only,
                    SUM(CASE WHEN TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.en')), '')) != ''
                              AND TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')), '')) = '' THEN 1 ELSE 0 END) as en_only
                ")
                ->first();

            return [
                'filtered_total' => (int) ($row->filtered_total ?? 0),
                'language_counts' => [
                    'tr' => (int) ($row->tr_count ?? 0),
                    'en' => (int) ($row->en_count ?? 0),
                ],
                'bilingual' => (int) ($row->bilingual ?? 0),
                'tr_only' => (int) ($row->tr_only ?? 0),
                'en_only' => (int) ($row->en_only ?? 0),
            ];
        });
    }
}
