<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionQualityReview extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    public const ACTION_APPROVE = 'approve';
    public const ACTION_EDIT = 'edit';
    public const ACTION_REJECT = 'reject';

    protected $fillable = [
        'question_id',
        'status',
        'provider',
        'model',
        'package',
        'external_job_id',
        'quality_score',
        'quality_band',
        'recommended_action',
        'estimated_difficulty',
        'boredom_risk',
        'ambiguity_risk',
        'duplicate_risk',
        'knowledge_confidence',
        'criteria_scores',
        'edit_reason',
        'revised_content',
        'question_snapshot',
        'raw_response',
        'assigned_at',
        'reviewed_at',
    ];

    protected $casts = [
        'question_id' => 'integer',
        'quality_score' => 'integer',
        'boredom_risk' => 'integer',
        'ambiguity_risk' => 'integer',
        'duplicate_risk' => 'integer',
        'knowledge_confidence' => 'integer',
        'criteria_scores' => 'array',
        'revised_content' => 'array',
        'question_snapshot' => 'array',
        'raw_response' => 'array',
        'assigned_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
