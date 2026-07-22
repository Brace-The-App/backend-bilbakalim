<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAnswerStat extends Model
{
    protected $fillable = [
        'question_id',
        'total_answers',
        'correct_count',
        'wrong_count',
        'option_1_count',
        'option_2_count',
        'option_3_count',
        'option_4_count',
        'correct_percentage',
        'observed_difficulty',
        'data_sufficient',
        'last_calculated_at',
    ];

    protected $casts = [
        'total_answers' => 'integer',
        'correct_count' => 'integer',
        'wrong_count' => 'integer',
        'option_1_count' => 'integer',
        'option_2_count' => 'integer',
        'option_3_count' => 'integer',
        'option_4_count' => 'integer',
        'correct_percentage' => 'float',
        'data_sufficient' => 'boolean',
        'last_calculated_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function getObservedDifficultyLabelAttribute(): string
    {
        return match ($this->observed_difficulty) {
            'easy' => 'Kolay',
            'medium' => 'Orta',
            'hard' => 'Zor',
            default => 'Veri yetersiz',
        };
    }
}
