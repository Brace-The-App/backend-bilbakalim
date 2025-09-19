<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tournament extends Model
{
    protected $fillable = [
        'title',
        'description',
        'quota',
        'rules',
        'awards',
        'start_date',
        'end_date',
        'start_time',
        'duration_minutes',
        'entry_fee',
        'question_count',
        'difficulty_level',
        'status',
        'image',
        'is_featured'
    ];

    protected $casts = [
        'quota' => 'integer',
        'rules' => 'array',
        'awards' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'start_time' => 'datetime:H:i',
        'duration_minutes' => 'integer',
        'entry_fee' => 'decimal:2',
        'question_count' => 'integer',
        'difficulty_level' => 'string',
        'status' => 'string',
        'is_featured' => 'boolean'
    ];

    // Relationships
    public function tournamentUsers(): HasMany
    {
        return $this->hasMany(TournamentUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_users')
                    ->withPivot(['joker_hakki', 'score', 'correct_answers', 'wrong_answers', 'total_time_seconds', 'status', 'joined_at', 'finished_at', 'answers_detail', 'rank'])
                    ->withTimestamps();
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    // Accessors
    public function getParticipantsCountAttribute()
    {
        return $this->tournamentUsers()->count();
    }

    public function getAvailableSlotsAttribute()
    {
        return $this->quota - $this->participants_count;
    }
}
