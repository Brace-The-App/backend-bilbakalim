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
        return match($this->multiplier) {
            'x2' => 2,
            'x4' => 4,
            'x8' => 8,
            default => 1,
        };
    }

    /**
     * Soru değerini hesapla (multiplier ile)
     */
    public function getQuestionValueAttribute(): int
    {
        return 10 * $this->multiplier_value;
    }
}
