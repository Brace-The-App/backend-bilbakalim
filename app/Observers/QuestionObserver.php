<?php

namespace App\Observers;

use App\Models\Question;
use App\Services\AdminQuestionStats;

class QuestionObserver
{
    public function created(Question $question): void
    {
        AdminQuestionStats::bump();
    }

    public function updated(Question $question): void
    {
        AdminQuestionStats::bump();
    }

    public function deleted(Question $question): void
    {
        AdminQuestionStats::bump();
    }
}
