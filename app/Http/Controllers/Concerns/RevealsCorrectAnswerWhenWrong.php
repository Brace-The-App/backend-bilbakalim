<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Question;

trait RevealsCorrectAnswerWhenWrong
{
    /**
     * Yanlış cevapta doğru şık bilgisini döndürür (doğru cevapta boş dizi).
     */
    protected function correctAnswerRevealForQuestion(Question $question, bool $isCorrect): array
    {
        if ($isCorrect) {
            return [];
        }

        $option = (string) $question->correct_answer;
        $choiceFields = [
            '1' => 'one_choice',
            '2' => 'two_choice',
            '3' => 'three_choice',
            '4' => 'four_choice',
        ];

        $field = $choiceFields[$option] ?? null;
        $text = ['tr' => null, 'en' => null];

        if ($field !== null) {
            $text['tr'] = $question->getTranslation($field, 'tr', false) ?: null;
            $text['en'] = $question->getTranslation($field, 'en', false) ?: null;
        }

        return [
            'correct_answer' => $option,
            'correct_option' => $option,
            'correct_answer_text' => $text,
        ];
    }
}
