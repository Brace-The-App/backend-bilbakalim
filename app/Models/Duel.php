<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Duel extends Model
{
    protected $fillable = [
        'challenger_id',
        'opponent_id',
        'multiplier',
        'status',
        'current_question_number',
        'current_question_id',
        'challenger_coins_before',
        'opponent_coins_before',
        'challenger_coins_after',
        'opponent_coins_after',
        'app_commission',
        'winner_id',
        'started_at',
        'finished_at',
        'settings'
    ];

    protected $casts = [
        'current_question_number' => 'integer',
        'challenger_coins_before' => 'integer',
        'opponent_coins_before' => 'integer',
        'challenger_coins_after' => 'integer',
        'opponent_coins_after' => 'integer',
        'app_commission' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Meydan okuyan kullanıcı
     */
    public function challenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'challenger_id');
    }

    /**
     * Rakip kullanıcı
     */
    public function opponent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    /**
     * Kazanan kullanıcı
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /**
     * Mevcut soru
     */
    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    /**
     * Düello cevapları
     */
    public function answers(): HasMany
    {
        return $this->hasMany(DuelAnswer::class);
    }

    /**
     * Multiplier değerini sayıya çevir
     */
    public function getMultiplierValueAttribute(): int
    {
        return match ($this->multiplier) {
            'x2' => 2,
            'x4' => 4,
            'x8' => 8,
            default => 1,
        };
    }

    /**
     * Soru jetoni × masa çarpanı.
     * Örnek: zor (3) × x2 = 6. Maç içi ekstra çarpan (2/4/6/8) ayrı çarpılır.
     */
    public function stakeForQuestion(?Question $question = null): int
    {
        $q = $question;
        if (!$q && $this->current_question_id) {
            $q = $this->relationLoaded('currentQuestion')
                ? $this->currentQuestion
                : $this->currentQuestion()->first();
        }

        $base = 1;
        if ($q) {
            $fromCoin = (int) ($q->coin_value ?? 0);
            $fromLevel = Question::coinValueForLevel($q->question_level);
            // Önce kayıttaki coin_value; boş/0 ise seviyeden
            $base = $fromCoin > 0 ? $fromCoin : ($fromLevel ?? 1);
            $base = max(1, $base);
        }

        return $base * $this->multiplier_value;
    }

    /**
     * Mevcut soru için stake (soru yoksa taban 1 × masa çarpanı).
     */
    public function getQuestionValueAttribute(): int
    {
        return $this->stakeForQuestion();
    }
}
