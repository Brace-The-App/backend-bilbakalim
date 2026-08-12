<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Question extends Model
{
    use HasTranslations;
    use SoftDeletes;

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
        'check',
        'ai_accepted',
        'ai_quality_review_id',
    ];

    protected $casts = [
        'correct_answer' => 'string',
        'question_level' => 'string',
        'coin_value' => 'integer',
        'is_active' => 'boolean',
        'category_id' => 'integer',
        'check' => 'boolean',
        'ai_accepted' => 'boolean',
        'ai_quality_review_id' => 'integer',
    ];

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function aiQualityReview(): BelongsTo
    {
        return $this->belongsTo(QuestionQualityReview::class, 'ai_quality_review_id');
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

    /** Tanımlı seviye ile gözlenen zorluk uyumsuz mu (güvenilir veri). */
    public function hasLevelMismatch(): bool
    {
        $stat = $this->answerStat;
        if (!$stat || !$stat->data_sufficient || (int) $stat->total_answers < 5) {
            return false;
        }

        $observed = $stat->observed_difficulty;
        if (!in_array($observed, ['easy', 'medium', 'hard'], true)) {
            return false;
        }

        return $this->question_level !== $observed;
    }

    /** Şüpheli şık dağılımı (yanlış anahtar / kötü soru adayı). */
    public function hasSuspiciousAnswers(): bool
    {
        $stat = $this->answerStat;
        $total = (int) ($stat->total_answers ?? 0);
        if (!$stat || $total < 3) {
            return false;
        }

        $counts = [
            '1' => (int) ($stat->option_1_count ?? 0),
            '2' => (int) ($stat->option_2_count ?? 0),
            '3' => (int) ($stat->option_3_count ?? 0),
            '4' => (int) ($stat->option_4_count ?? 0),
        ];

        $correct = (string) $this->correct_answer;
        $correctShare = ($counts[$correct] ?? 0) / $total * 100;
        if ($correctShare < 10) {
            return true;
        }

        foreach ($counts as $key => $count) {
            if ((string) $key === $correct) {
                continue;
            }
            if (($count / $total * 100) >= 70) {
                return true;
            }
        }

        return false;
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

    public const DELETED_LABEL_TR = 'Soru silinmiş';

    public function isRemoved(): bool
    {
        return $this->trashed();
    }

    /** Geçmiş ekranları için metin (silinmiş soruda sabit etiket). */
    public function displayQuestionTr(): string
    {
        if ($this->trashed()) {
            return self::DELETED_LABEL_TR;
        }

        $text = $this->getTranslation('question', 'tr', false);

        return $text !== '' ? $text : self::DELETED_LABEL_TR;
    }
}
