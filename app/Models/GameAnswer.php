<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAnswer extends Model
{
    protected $fillable = [
        'game_session_id',
        'question_id',
        'user_id',
        'user_answer',
        'is_correct',
        'is_joker_used',
        'time_taken',
        'coins_earned',
        'points_earned',
        'answered_at'
    ];

    protected $casts = [
        'game_session_id' => 'integer',
        'question_id' => 'integer',
        'user_id' => 'integer',
        'is_correct' => 'boolean',
        'is_joker_used' => 'boolean',
        'time_taken' => 'integer',
        'coins_earned' => 'integer',
        'points_earned' => 'integer',
        'answered_at' => 'datetime'
    ];

    // Relationships
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    public function scopeIncorrect($query)
    {
        return $query->where('is_correct', false);
    }

    public function scopeJokerUsed($query)
    {
        return $query->where('is_joker_used', true);
    }
}
