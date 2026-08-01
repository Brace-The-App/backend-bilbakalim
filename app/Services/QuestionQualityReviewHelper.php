<?php

namespace App\Services;

class QuestionQualityReviewHelper
{
    /** Türkçe kriter anahtarları → azami puan (prompt çıktısı). */
    public const CRITERIA_MAX = [
        'bilgi_dogrulugu' => 20,
        'dil_kalitesi' => 12,
        'tek_kesin_cevap' => 10,
        'celdirici_kalitesi' => 10,
        'zorluk_dengesi' => 10,
        'kullanici_ilgisi' => 10,
        'kategori_uygunlugu' => 8,
        'dil_tutarliligi' => 8,
        'ozgunluk' => 7,
        'guncellik_format' => 5,
    ];

    /** Eski İngilizce anahtar → Türkçe (geriye uyumluluk). */
    private const LEGACY_KEY_MAP = [
        'knowledge_accuracy' => 'bilgi_dogrulugu',
        'clarity_language' => 'dil_kalitesi',
        'single_correct' => 'tek_kesin_cevap',
        'distractor_quality' => 'celdirici_kalitesi',
        'difficulty_balance' => 'zorluk_dengesi',
        'engagement' => 'kullanici_ilgisi',
        'category_fit' => 'kategori_uygunlugu',
        'tr_en_consistency' => 'dil_tutarliligi',
        'originality' => 'ozgunluk',
        'freshness_neutrality' => 'guncellik_format',
    ];

    public static function prompt(): string
    {
        return (string) config('ai_question_review.prompt', '');
    }

    public static function pendingTimeoutMinutes(): int
    {
        return max(5, (int) config('ai_question_review.pending_timeout_minutes', 30));
    }

    /**
     * @param  array<string, mixed>  $raw  kriter_analizleri veya eski criteria_scores
     * @return array<string, array{puan:int, max_puan:int, yuzde:float|int, max?:int, score?:int}>
     */
    public static function normalizeCriteriaScores(array $raw): array
    {
        $normalizedInput = [];
        foreach ($raw as $key => $item) {
            $trKey = self::LEGACY_KEY_MAP[$key] ?? $key;
            $normalizedInput[$trKey] = $item;
        }

        $out = [];
        foreach (self::CRITERIA_MAX as $key => $max) {
            $item = $normalizedInput[$key] ?? null;
            $score = 0;
            if (is_array($item)) {
                $score = (int) ($item['puan'] ?? $item['score'] ?? $item['value'] ?? 0);
                if (isset($item['max_puan'])) {
                    $max = (int) $item['max_puan'];
                } elseif (isset($item['max'])) {
                    $max = (int) $item['max'];
                }
            } elseif (is_numeric($item)) {
                $score = (int) $item;
            }
            $score = max(0, min($max, $score));
            $yuzde = $max > 0 ? round(($score / $max) * 100, 2) : 0;
            if (is_array($item) && isset($item['yuzde']) && is_numeric($item['yuzde'])) {
                $yuzde = (float) $item['yuzde'];
            }
            $out[$key] = [
                'puan' => $score,
                'max_puan' => $max,
                'yuzde' => $yuzde,
                // geriye uyumluluk
                'score' => $score,
                'max' => $max,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{puan?:int, score?:int}>  $criteria
     */
    public static function sumCriteria(array $criteria): int
    {
        $sum = 0;
        foreach ($criteria as $row) {
            $sum += (int) ($row['puan'] ?? $row['score'] ?? 0);
        }

        return max(0, min(100, $sum));
    }

    public static function bandFromScore(int $score): string
    {
        return match (true) {
            $score >= 80 => 'high',
            $score >= 60 => 'medium',
            $score >= 40 => 'low',
            $score >= 20 => 'very_low',
            default => 'reject',
        };
    }

    public static function bandLabelFromScore(int $score): string
    {
        return match (true) {
            $score >= 80 => 'Yüksek kaliteli',
            $score >= 60 => 'Orta kaliteli',
            $score >= 40 => 'Düşük kaliteli',
            $score >= 20 => 'Çok düşük kaliteli',
            default => 'Kullanıma uygun değil',
        };
    }

    public static function mapAction(?string $action): ?string
    {
        if ($action === null || $action === '') {
            return null;
        }
        $a = mb_strtolower(trim($action), 'UTF-8');

        return match (true) {
            in_array($a, ['onayla', 'approve'], true) => 'approve',
            in_array($a, ['düzenle', 'duzenle', 'edit'], true) => 'edit',
            in_array($a, ['reddet', 'reject'], true) => 'reject',
            default => $action,
        };
    }

    public static function clampPct(mixed $value): int
    {
        return max(0, min(100, (int) $value));
    }

    /**
     * POST gövdesinden (yeni orjinal/analiz_sonucu veya eski flat) alanları çıkar.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *   question_id:?int,
     *   review_id:?int,
     *   quality_score:?int,
     *   quality_band:?string,
     *   recommended_action:?string,
     *   estimated_difficulty:?string,
     *   boredom_risk:?int,
     *   ambiguity_risk:?int,
     *   duplicate_risk:?int,
     *   knowledge_confidence:?int,
     *   criteria_scores:array,
     *   edit_reason:?string,
     *   analiz_mesaji:?string,
     *   revised_content:?array,
     *   orjinal:?array,
     *   analiz_sonucu:?array,
     *   provider:?string,
     *   model:?string,
     *   package:?string,
     *   external_job_id:?string,
     *   failed:bool,
     *   fail_reason:?string
     * }
     */
    public static function extractFromPayload(array $payload): array
    {
        $orjinal = is_array($payload['orjinal'] ?? null) ? $payload['orjinal'] : null;
        $analiz = is_array($payload['analiz_sonucu'] ?? null) ? $payload['analiz_sonucu'] : null;
        $ek = is_array($analiz['ek_analizler'] ?? null) ? $analiz['ek_analizler'] : [];
        $kriter = is_array($analiz['kriter_analizleri'] ?? null)
            ? $analiz['kriter_analizleri']
            : (is_array($payload['criteria_scores'] ?? null) ? $payload['criteria_scores'] : []);

        $criteria = self::normalizeCriteriaScores($kriter);

        $qualityScore = null;
        if (isset($analiz['ana_kalite_yuzdesi']) && is_numeric($analiz['ana_kalite_yuzdesi'])) {
            $qualityScore = self::clampPct($analiz['ana_kalite_yuzdesi']);
        } elseif (isset($payload['quality_score']) && is_numeric($payload['quality_score'])) {
            $qualityScore = self::clampPct($payload['quality_score']);
        } elseif ($criteria !== []) {
            $qualityScore = self::sumCriteria($criteria);
        }

        $band = $analiz['kalite_seviyesi'] ?? $payload['quality_band'] ?? null;
        if (($band === null || $band === '') && $qualityScore !== null) {
            $band = self::bandFromScore($qualityScore);
        }

        $actionRaw = $analiz['onerilen_islem'] ?? $payload['recommended_action'] ?? null;
        $action = self::mapAction(is_string($actionRaw) ? $actionRaw : null);
        if ($action === null && $qualityScore !== null) {
            $action = match (self::bandFromScore($qualityScore)) {
                'high' => 'approve',
                'medium', 'low' => 'edit',
                default => 'reject',
            };
        }

        $questionId = $payload['question_id']
            ?? ($orjinal['question_id'] ?? null)
            ?? ($analiz['symbolCode'] ?? null);

        $revised = $analiz['duzeltilmis_icerik'] ?? $payload['revised_content'] ?? null;

        return [
            'question_id' => $questionId !== null && $questionId !== '' ? (int) $questionId : null,
            'review_id' => isset($payload['review_id']) ? (int) $payload['review_id'] : null,
            'quality_score' => $qualityScore,
            'quality_band' => is_string($band) ? $band : null,
            'recommended_action' => $action,
            'estimated_difficulty' => isset($ek['tahmini_zorluk'])
                ? (string) $ek['tahmini_zorluk']
                : (isset($payload['estimated_difficulty']) ? (string) $payload['estimated_difficulty'] : null),
            'boredom_risk' => isset($ek['tahmini_sikicilik_riski'])
                ? self::clampPct($ek['tahmini_sikicilik_riski'])
                : (isset($payload['boredom_risk']) ? self::clampPct($payload['boredom_risk']) : null),
            'ambiguity_risk' => isset($ek['belirsizlik_riski'])
                ? self::clampPct($ek['belirsizlik_riski'])
                : (isset($payload['ambiguity_risk']) ? self::clampPct($payload['ambiguity_risk']) : null),
            'duplicate_risk' => isset($ek['mukerrerlik_riski'])
                ? self::clampPct($ek['mukerrerlik_riski'])
                : (isset($payload['duplicate_risk']) ? self::clampPct($payload['duplicate_risk']) : null),
            'knowledge_confidence' => isset($ek['bilgi_dogrulugu_guveni'])
                ? self::clampPct($ek['bilgi_dogrulugu_guveni'])
                : (isset($payload['knowledge_confidence']) ? self::clampPct($payload['knowledge_confidence']) : null),
            'criteria_scores' => $criteria,
            'edit_reason' => isset($analiz['duzeltme_gerekcesi'])
                ? (string) $analiz['duzeltme_gerekcesi']
                : (isset($payload['edit_reason']) ? (string) $payload['edit_reason'] : null),
            'analiz_mesaji' => isset($analiz['analiz_mesaji']) ? (string) $analiz['analiz_mesaji'] : null,
            'revised_content' => is_array($revised) ? $revised : null,
            'orjinal' => $orjinal,
            'analiz_sonucu' => $analiz,
            'provider' => isset($payload['provider']) ? (string) $payload['provider'] : null,
            'model' => isset($payload['model']) ? (string) $payload['model'] : null,
            'package' => isset($payload['package']) ? (string) $payload['package'] : null,
            'external_job_id' => isset($payload['external_job_id']) ? (string) $payload['external_job_id'] : null,
            'failed' => !empty($payload['failed']),
            'fail_reason' => isset($payload['fail_reason']) ? (string) $payload['fail_reason'] : null,
        ];
    }
}
