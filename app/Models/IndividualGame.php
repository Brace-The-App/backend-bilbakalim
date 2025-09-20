<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndividualGame extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'game_type',
        'difficulty_level',
        'question_count',
        'time_limit_seconds',
        'joker_count',
        'score',
        'correct_answers',
        'wrong_answers',
        'coins_earned',
        'total_time_seconds',
        'status',
        'started_at',
        'completed_at',
        'settings'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'category_id' => 'integer',
        'question_count' => 'integer',
        'time_limit_seconds' => 'integer',
        'joker_count' => 'integer',
        'score' => 'integer',
        'correct_answers' => 'integer',
        'wrong_answers' => 'integer',
        'coins_earned' => 'integer',
        'total_time_seconds' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'settings' => 'array'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
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

    public function scopeByDifficulty($query, $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getAccuracyRateAttribute()
    {
        $total = $this->correct_answers + $this->wrong_answers;
        return $total > 0 ? round(($this->correct_answers / $total) * 100, 2) : 0;
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'completed';
    }

    public function getIsActiveAttribute()
    {
        return $this->status === 'active';
    }
}
