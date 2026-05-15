<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiQuestionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $supportedLocales = config('app.supported_locales', ['tr', 'en']);

        $rules = [
            'category_id' => ['required', 'exists:categories,id'],
            'correct_answer' => ['required', Rule::in(['1', '2', '3', '4'])],
            'question_level' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'coin_value' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        foreach ($supportedLocales as $locale) {
            if ($locale === 'tr') {
                $rules["question.$locale"] = ['required', 'string'];
                $rules["one_choice.$locale"] = ['required', 'string', 'max:255'];
                $rules["two_choice.$locale"] = ['required', 'string', 'max:255'];
                $rules["three_choice.$locale"] = ['required', 'string', 'max:255'];
                $rules["four_choice.$locale"] = ['required', 'string', 'max:255'];
            } else {
                $rules["question.$locale"] = ['nullable', 'string'];
                $rules["one_choice.$locale"] = ['nullable', 'string', 'max:255'];
                $rules["two_choice.$locale"] = ['nullable', 'string', 'max:255'];
                $rules["three_choice.$locale"] = ['nullable', 'string', 'max:255'];
                $rules["four_choice.$locale"] = ['nullable', 'string', 'max:255'];
            }
        }

        $validated = $request->validate($rules);

        $questionTr = trim((string) ($validated['question']['tr'] ?? ''));

        // Duplicate engeli: TR soru metni aynıysa ekleme.
        // Spatie Translatable JSON sakladığı için JSON_EXTRACT kullanıyoruz.
        $existing = Question::query()
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')) = ?", [$questionTr])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate question.',
                'question_id' => $existing->id,
            ], 409);
        }

        $question = new Question();

        foreach ($supportedLocales as $locale) {
            foreach (['question', 'one_choice', 'two_choice', 'three_choice', 'four_choice'] as $field) {
                $value = $validated[$field][$locale] ?? null;
                $value = is_string($value) ? trim($value) : $value;

                if ($value === '' || $value === null) {
                    continue;
                }

                $question->setTranslation($field, $locale, $value);
            }
        }

        $question->category_id = (int) $validated['category_id'];
        $question->correct_answer = (string) $validated['correct_answer'];
        $question->question_level = (string) $validated['question_level'];
        $question->coin_value = (int) $validated['coin_value'];
        $question->is_active = (bool) ($validated['is_active'] ?? true);

        $question->save();

        return response()->json([
            'success' => true,
            'message' => 'Question created.',
            'question_id' => $question->id,
        ], 201);
    }
}

