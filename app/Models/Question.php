<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Question extends Model
{
    use HasTranslations;

    public $translatable = ['question', 'one_choice', 'two_choice', 'three_choice', 'four_choice'];

    protected $fillable = [
        'question',
        'one_choice',
        'two_choice',
        'three_choice',
        'four_choice',
        'correct_answer',
        'category_id',
        'question_level',
        'coin_value',
        'image',
        'is_active',
        'admin_status',
        'check'
    ];

    protected $casts = [
        'correct_answer' => 'string',
        'question_level' => 'string',
        'coin_value' => 'integer',
        'is_active' => 'boolean',
        'category_id' => 'integer',
        'check' => 'boolean'
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function answerStat(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(QuestionAnswerStat::class);
    }

    public function adminLogs(): HasMany
    {
        return $this->hasMany(QuestionAdminLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('question_level', $level);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeInRandomOrder($query, $seed = null)
    {
        if ($seed !== null) {
            // Seed ile deterministik rastgele sıralama
            return $query->orderByRaw("RAND({$seed})");
        }
        return $query->orderByRaw('RAND()');
    }

    // Accessors
    public function getChoicesAttribute()
    {
        return [
            '1' => $this->one_choice,
            '2' => $this->two_choice,
            '3' => $this->three_choice,
            '4' => $this->four_choice,
        ];
    }

    public function getCorrectChoiceTextAttribute()
    {
        return $this->choices[$this->correct_answer] ?? '';
    }
}
