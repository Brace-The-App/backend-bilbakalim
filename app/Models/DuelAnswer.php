<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuelAnswer extends Model
{
    protected $fillable = [
        'duel_id',
        'user_id',
        'question_id',
        'selected_answer',
        'is_correct',
        'diamonds_change',
        'diamonds_before',
        'diamonds_after',
        'question_value',
        'answered_at'
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'diamonds_change' => 'integer',
        'diamonds_before' => 'integer',
        'diamonds_after' => 'integer',
        'question_value' => 'integer',
        'answered_at' => 'datetime',
    ];

    /**
     * Düello ilişkisi
     */
    public function duel(): BelongsTo
    {
        return $this->belongsTo(Duel::class);
    }

    /**
     * Kullanıcı ilişkisi
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Soru ilişkisi
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
