<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentUser extends Model
{
    protected $fillable = [
        'tournament_id',
        'user_id',
        'joker_hakki',
        'score',
        'correct_answers',
        'wrong_answers',
        'total_time_seconds',
        'status',
        'joined_at',
        'finished_at',
        'answers_detail',
        'rank'
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'user_id' => 'integer',
        'joker_hakki' => 'integer',
        'score' => 'integer',
        'correct_answers' => 'integer',
        'wrong_answers' => 'integer',
        'total_time_seconds' => 'integer',
        'status' => 'string',
        'joined_at' => 'datetime',
        'finished_at' => 'datetime',
        'answers_detail' => 'array',
        'rank' => 'integer'
    ];

    // Relationships
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByRank($query)
    {
        return $query->orderBy('rank');
    }

    public function scopeByScore($query)
    {
        return $query->orderByDesc('score')
                    ->orderBy('total_time_seconds');
    }
}
