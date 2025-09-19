<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = [
        'user_id',
        'question_id',
        'user_answer',
        'is_correct',
        'time_taken',
        'coins_earned'
    ];

    protected $casts = [
        'user_answer' => 'string',
        'is_correct' => 'boolean',
        'time_taken' => 'integer',
        'coins_earned' => 'integer',
        'user_id' => 'integer',
        'question_id' => 'integer'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
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
}
