<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameSession extends Model
{
    protected $fillable = [
        'session_id',
        'user_id',
        'individual_game_id',
        'tournament_id',
        'game_type',
        'status',
        'current_question',
        'current_question_index',
        'total_questions',
        'correct_answers',
        'wrong_answers',
        'joker_used',
        'score',
        'time_remaining',
        'started_at',
        'last_activity_at',
        'game_data'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'individual_game_id' => 'integer',
        'tournament_id' => 'integer',
        'current_question_index' => 'integer',
        'total_questions' => 'integer',
        'correct_answers' => 'integer',
        'wrong_answers' => 'integer',
        'joker_used' => 'integer',
        'score' => 'integer',
        'time_remaining' => 'integer',
        'started_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'current_question' => 'array',
        'game_data' => 'array'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function individualGame(): BelongsTo
    {
        return $this->belongsTo(IndividualGame::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function gameAnswers(): HasMany
    {
        return $this->hasMany(GameAnswer::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('game_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessors
    public function getProgressPercentageAttribute()
    {
        return $this->total_questions > 0 ? round(($this->current_question_index / $this->total_questions) * 100, 2) : 0;
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'completed';
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }

    public function getAccuracyRateAttribute()
    {
        $total = $this->correct_answers + $this->wrong_answers;
        return $total > 0 ? round(($this->correct_answers / $total) * 100, 2) : 0;
    }
}
