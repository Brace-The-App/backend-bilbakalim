<?php

namespace App\Services;

use App\Models\Question;

/**
 * Tüm düello botları için ortak cevap motoru.
 * Zorluk seviyeleri istatistiksel hedef doğruluk bandında tutar;
 * her soru bağımsız rastgele + streak dengesi ile seçilir.
 */
class BotAnswerEngine
{
    public const TIER_EASY = 'easy';
    public const TIER_MEDIUM = 'medium';
    public const TIER_HARD = 'hard';
    public const TIER_PROFESSOR = 'professor';

    /**
     * Hedef (orta nokta) + izin verilen bant.
     * Bant dışına çıkınca bir sonraki cevap güçlü şekilde düzeltilir.
     */
    public const TIERS = [
        self::TIER_EASY => [
            'label' => 'Kolay',
            'target' => 0.22,
            'min' => 0.20,
            'max' => 0.25,
        ],
        self::TIER_MEDIUM => [
            'label' => 'Orta',
            'target' => 0.50,
            'min' => 0.40,
            'max' => 0.60,
        ],
        self::TIER_HARD => [
            'label' => 'Zor',
            'target' => 0.775,
            'min' => 0.75,
            'max' => 0.80,
        ],
        self::TIER_PROFESSOR => [
            'label' => 'Terminatör',
            'target' => 0.925,
            'min' => 0.90,
            'max' => 0.95,
        ],
    ];

    /**
     * Bahis kabul hedefi (easy daha çekingen, hard agresif).
     * Easy ≈ 3/5 kabul (~%60); yüksek çarpan biraz düşürür.
     */
    public const BET_ACCEPT = [
        self::TIER_EASY => [
            'target' => 0.62,
            'min' => 0.55,
            'max' => 0.72,
        ],
        self::TIER_MEDIUM => [
            'target' => 0.80,
            'min' => 0.72,
            'max' => 0.88,
        ],
        self::TIER_HARD => [
            'target' => 0.92,
            'min' => 0.85,
            'max' => 0.98,
        ],
        self::TIER_PROFESSOR => [
            'target' => 0.97,
            'min' => 0.92,
            'max' => 1.0,
        ],
    ];

    /**
     * Botun kendi teklif atma oranı (maç başına).
     * easy/medium ≈ 5 maçta 1; hard/professor sık.
     */
    public const BET_OFFER_MATCH_RATE = [
        self::TIER_EASY => 0.20,
        self::TIER_MEDIUM => 0.20,
        self::TIER_HARD => 0.70,
        self::TIER_PROFESSOR => 0.85,
    ];

    /** Cevap düşünme süresi (sn): [min, max] — jitter */
    public const ANSWER_DELAY = [
        self::TIER_EASY => [1.8, 4.2],
        self::TIER_MEDIUM => [1.2, 3.2],
        self::TIER_HARD => [0.7, 2.2],
        self::TIER_PROFESSOR => [0.5, 1.6],
    ];

    /** Bahis teklifine düşünme süresi (sn) */
    public const BET_THINK_DELAY = [
        self::TIER_EASY => [1.2, 3.0],
        self::TIER_MEDIUM => [0.8, 2.2],
        self::TIER_HARD => [0.5, 1.5],
        self::TIER_PROFESSOR => [0.4, 1.2],
    ];

    private string $tier;
    private int $correct = 0;
    private int $total = 0;
    private int $betsSeen = 0;
    private int $betsAccepted = 0;
    private int $offersMade = 0;
    private int $lastOfferQuestion = 0;
    /** @var null|array{enabled:bool,max_offers:int,first_at:int,min_gap:int} */
    private ?array $offerPlan = null;

    public const BET_LADDER = [2, 4, 6, 8];

    public function __construct(string $tier = self::TIER_MEDIUM)
    {
        $this->tier = self::normalizeTier($tier);
    }

    public static function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));
        return array_key_exists($tier, self::TIERS) ? $tier : self::TIER_MEDIUM;
    }

    public static function targetAccuracy(string $tier): float
    {
        $tier = self::normalizeTier($tier);

        return (float) self::TIERS[$tier]['target'];
    }

    public static function tierMeta(string $tier): array
    {
        $tier = self::normalizeTier($tier);

        return self::TIERS[$tier];
    }

    /** Admin select için */
    public static function tierOptions(): array
    {
        $out = [];
        foreach (self::TIERS as $key => $meta) {
            $out[$key] = self::tierAdminLabel($key);
        }

        return $out;
    }

    /** Kısa etiket: "Medium · hedef %50 · 8 soruda ~4/8" */
    public static function tierAdminLabel(string $tier): string
    {
        $tier = self::normalizeTier($tier);
        $meta = self::TIERS[$tier];
        $ex = self::discreteExamples($tier, 8);
        $best = $ex[0] ?? null;
        $exTxt = $best
            ? "8 soruda ~{$best['correct']}/8 (%{$best['pct']})"
            : '';

        return sprintf(
            '%s · hedef ~%%%d (%d–%d) · %s',
            $meta['label'],
            (int) round($meta['target'] * 100),
            (int) round($meta['min'] * 100),
            (int) round($meta['max'] * 100),
            $exTxt
        );
    }

    /**
     * N soruluk maçta banda en yakın mümkün doğru sayıları.
     *
     * @return list<array{correct:int,total:int,pct:int,in_band:bool}>
     */
    public static function discreteExamples(string $tier, int $total = 8): array
    {
        $tier = self::normalizeTier($tier);
        $meta = self::TIERS[$tier];
        $min = (float) $meta['min'];
        $max = (float) $meta['max'];
        $target = (float) $meta['target'];
        $total = max(1, $total);

        $scored = [];
        for ($c = 0; $c <= $total; $c++) {
            $pct = $c / $total;
            $inBand = $pct >= $min && $pct <= $max;
            $dist = abs($pct - $target);
            // Bant dışını cezalandır ama en yakınları da göster
            $score = $dist + ($inBand ? 0 : 0.5);
            $scored[] = [
                'correct' => $c,
                'total' => $total,
                'pct' => (int) round($pct * 100),
                'in_band' => $inBand,
                '_score' => $score,
            ];
        }

        usort($scored, fn ($a, $b) => $a['_score'] <=> $b['_score']);

        $out = [];
        foreach (array_slice($scored, 0, 3) as $row) {
            unset($row['_score']);
            $out[] = $row;
        }

        return $out;
    }

    /** Admin bilgi kartı / tooltip metni */
    public static function tierHelpText(string $tier): string
    {
        $tier = self::normalizeTier($tier);
        $meta = self::TIERS[$tier];
        $lines = [];
        $lines[] = sprintf(
            'Hedef isabet ~%%%d (bant %%%d–%%%d).',
            (int) round($meta['target'] * 100),
            (int) round($meta['min'] * 100),
            (int) round($meta['max'] * 100)
        );
        $lines[] = 'Kısa maçta yüzde kesirli olamaz; motor en yakın doğru sayısına çeker:';
        foreach ([8, 10, 12] as $n) {
            $best = self::discreteExamples($tier, $n)[0] ?? null;
            if ($best) {
                $mark = $best['in_band'] ? '✓' : '≈';
                $lines[] = " · {$n} soru → {$mark} {$best['correct']}/{$n} (%{$best['pct']})";
            }
        }

        return implode("\n", $lines);
    }

    /** Tüm seviyeler — panel özet tablosu */
    public static function tierGuideRows(): array
    {
        $rows = [];
        foreach (array_keys(self::TIERS) as $tier) {
            $meta = self::TIERS[$tier];
            $e8 = self::discreteExamples($tier, 8)[0];
            $e10 = self::discreteExamples($tier, 10)[0];
            $rows[] = [
                'key' => $tier,
                'label' => $meta['label'],
                'target_pct' => (int) round($meta['target'] * 100),
                'min_pct' => (int) round($meta['min'] * 100),
                'max_pct' => (int) round($meta['max'] * 100),
                'example_8' => "{$e8['correct']}/8 (%{$e8['pct']})",
                'example_10' => "{$e10['correct']}/10 (%{$e10['pct']})",
            ];
        }

        return $rows;
    }

    public function resetMatchStats(): void
    {
        $this->correct = 0;
        $this->total = 0;
        $this->betsSeen = 0;
        $this->betsAccepted = 0;
        $this->offersMade = 0;
        $this->lastOfferQuestion = 0;
        $this->offerPlan = null;
    }

    public function getStats(): array
    {
        return [
            'tier' => $this->tier,
            'correct' => $this->correct,
            'total' => $this->total,
            'rate' => $this->total > 0 ? $this->correct / $this->total : null,
            'target' => self::targetAccuracy($this->tier),
            'bets_seen' => $this->betsSeen,
            'bets_accepted' => $this->betsAccepted,
            'bet_accept_rate' => $this->betsSeen > 0 ? $this->betsAccepted / $this->betsSeen : null,
            'bet_accept_target' => self::betAcceptTarget($this->tier),
            'offers_made' => $this->offersMade,
        ];
    }

    public function setTier(string $tier): void
    {
        $this->tier = self::normalizeTier($tier);
    }

    /**
     * Maç başında: bu maçta teklif zinciri açılacak mı?
     * Her zaman 2→4→6→8 sırasıyla; aralıklı sorularda.
     */
    public function ensureOfferPlan(): void
    {
        if ($this->offerPlan !== null) {
            return;
        }

        $rate = (float) (self::BET_OFFER_MATCH_RATE[$this->tier] ?? 0.2);
        $enabled = (mt_rand(0, 1000) / 1000) < $rate;

        [$maxOffers, $minGap, $firstAt] = match ($this->tier) {
            self::TIER_EASY => [1, 3, random_int(2, 4)],
            self::TIER_MEDIUM => [1, 3, random_int(2, 4)],
            self::TIER_HARD => [3, 2, random_int(2, 3)],
            self::TIER_PROFESSOR => [4, 2, random_int(2, 3)],
            default => [1, 3, random_int(2, 4)],
        };

        $this->offerPlan = [
            'enabled' => $enabled,
            'max_offers' => $enabled ? $maxOffers : 0,
            'first_at' => $firstAt,
            'min_gap' => $minGap,
        ];
    }

    /**
     * Bu soruda bot teklif atsın mı?
     * Sıra: mevcut çarpandan bir üst basamak (1→2→4→6→8). Asla 2x atlamadan 6x yok.
     *
     * @return int|null 2|4|6|8
     */
    public function offerMultiplierForQuestion(int $questionNumber, int $currentApplied = 1): ?int
    {
        $this->ensureOfferPlan();
        $plan = $this->offerPlan;
        if (!$plan || empty($plan['enabled'])) {
            return null;
        }
        if ($this->offersMade >= (int) $plan['max_offers']) {
            return null;
        }
        if ($questionNumber < (int) $plan['first_at']) {
            return null;
        }
        if ($this->offersMade > 0 && $questionNumber < ($this->lastOfferQuestion + (int) $plan['min_gap'])) {
            return null;
        }

        $next = self::nextLadderStep($currentApplied);
        if ($next === null) {
            return null;
        }

        // Bot kendi teklif zincirine 2x ile başlar; insan zaten 4x yaptıysa bir üst (6) devam eder
        return $next;
    }

    public function recordOfferMade(int $questionNumber = 0): void
    {
        $this->offersMade++;
        if ($questionNumber > 0) {
            $this->lastOfferQuestion = $questionNumber;
        }
    }

    /** Mevcut çarpandan sonraki basamak (2→4→6→8) */
    public static function nextLadderStep(int $currentApplied): ?int
    {
        $current = max(1, $currentApplied);
        foreach (self::BET_LADDER as $step) {
            if ($step > $current) {
                return $step;
            }
        }

        return null;
    }

    public static function betAcceptTarget(string $tier): float
    {
        $tier = self::normalizeTier($tier);

        return (float) self::BET_ACCEPT[$tier]['target'];
    }

    /**
     * Tek seferlik karar: bu teklifi kabul etsin mi?
     * $multiplier: 2|4|6|8 (soru bahsi).
     */
    public function decideBetAccept(int $multiplier): bool
    {
        $tier = $this->tier;
        $meta = self::BET_ACCEPT[$tier];
        $target = (float) $meta['target'];
        $min = (float) $meta['min'];
        $max = (float) $meta['max'];

        $p = $target * $this->multiplierAcceptFactor($multiplier);

        // Maç içi oran banda çekilsin (easy ~3/5 hissi)
        if ($this->betsSeen >= 2) {
            $rate = $this->betsAccepted / $this->betsSeen;
            if ($rate < $min) {
                $p = min(0.98, $p + 0.25);
            } elseif ($rate > $max) {
                $p = max(0.08, $p - 0.30);
            } elseif ($rate < $target) {
                $p = min(0.95, $p + 0.10);
            } elseif ($rate > $target) {
                $p = max(0.12, $p - 0.10);
            }
        }

        $p = max(0.05, min(0.99, $p));
        $accept = (mt_rand(0, 1000) / 1000) < $p;

        $this->betsSeen++;
        if ($accept) {
            $this->betsAccepted++;
        }

        return $accept;
    }

    /** İnsanvari cevap gecikmesi (sn) */
    public function answerDelaySeconds(): float
    {
        return $this->randomInRange(self::ANSWER_DELAY[$this->tier] ?? [1.2, 3.0]);
    }

    /** Bahis teklifine düşünme süresi (sn) */
    public function betThinkDelaySeconds(): float
    {
        return $this->randomInRange(self::BET_THINK_DELAY[$this->tier] ?? [0.8, 2.0]);
    }

    private function multiplierAcceptFactor(int $multiplier): float
    {
        // Yüksek çarpan: easy daha çekingen, hard neredeyse umursamaz
        $table = [
            self::TIER_EASY => [2 => 1.08, 4 => 0.88, 6 => 0.72, 8 => 0.58],
            self::TIER_MEDIUM => [2 => 1.05, 4 => 0.95, 6 => 0.85, 8 => 0.75],
            self::TIER_HARD => [2 => 1.02, 4 => 1.00, 6 => 0.96, 8 => 0.92],
            self::TIER_PROFESSOR => [2 => 1.01, 4 => 1.00, 6 => 0.99, 8 => 0.97],
        ];
        $row = $table[$this->tier] ?? $table[self::TIER_MEDIUM];

        return (float) ($row[$multiplier] ?? $row[4] ?? 0.9);
    }

    private function randomInRange(array $range): float
    {
        $min = (float) ($range[0] ?? 1.0);
        $max = (float) ($range[1] ?? 2.0);
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        return round($min + (mt_rand(0, 1000) / 1000) * ($max - $min), 2);
    }

    /**
     * Sonraki şıkkı seç (1–4 string).
     * $forceCorrect: test --correct bayrağı.
     */
    public function pickChoice(?Question $question, bool $forceCorrect = false): string
    {
        $correct = $question ? (string) $question->correct_answer : '1';
        if ($correct === '' || $correct === '0') {
            $correct = '1';
        }

        if ($forceCorrect) {
            return $correct;
        }

        $wantCorrect = $this->shouldAnswerCorrect();

        if ($wantCorrect) {
            return $correct;
        }

        return $this->randomWrongChoice($correct);
    }

    /** API sonucu geldikten sonra çağır */
    public function recordResult(bool $isCorrect): void
    {
        $this->total++;
        if ($isCorrect) {
            $this->correct++;
        }
    }

    /**
     * Seviye bandına göre doğru/yanlış kararı.
     * Bant dışındaysa zorla çeker; bant içinde hedefe yumuşak yaklaşır.
     * Kısa maçlarda (8–12 soru) oranı seviyeye yakın tutar.
     */
    private function shouldAnswerCorrect(): bool
    {
        $meta = self::TIERS[$this->tier];
        $target = (float) $meta['target'];
        $min = (float) $meta['min'];
        $max = (float) $meta['max'];

        // İlk cevap: hedefe göre
        if ($this->total === 0) {
            return (mt_rand(0, 1000) / 1000) < $target;
        }

        $rate = $this->correct / $this->total;

        // Sert düzeltme: bant altı → kesin doğru; bant üstü → kesin yanlış
        if ($rate < $min) {
            return true;
        }
        if ($rate > $max) {
            return false;
        }

        // Bant içinde: bir sonraki cevap sonrası beklenen oran hedefe çekilsin
        // P ≈ target + gain*(target - rate)
        $gain = $this->total < 4 ? 2.2 : 1.6;
        $p = $target + $gain * ($target - $rate);

        // Dar bantlarda (easy/hard) daha agresif
        $bandWidth = $max - $min;
        if ($bandWidth <= 0.08) {
            $p = $target + ($gain + 0.6) * ($target - $rate);
        }

        $p = max(0.04, min(0.96, $p));

        return (mt_rand(0, 1000) / 1000) < $p;
    }

    private function randomWrongChoice(string $correct): string
    {
        $wrong = [];
        foreach (['1', '2', '3', '4'] as $opt) {
            if ($opt !== $correct) {
                $wrong[] = $opt;
            }
        }

        if ($wrong === []) {
            return $correct;
        }

        return $wrong[array_rand($wrong)];
    }
}
