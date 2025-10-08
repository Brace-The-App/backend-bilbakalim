<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\Category;
use App\Http\Controllers\WebhookController;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view questions')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create questions')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit questions')->only(['edit', 'update']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete questions')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Question::with('category');

        // Filtering
        if ($request->filled('status')) {
            $query->where('is_active', $request->status);
        }

        if ($request->filled('level')) {
            $query->where('question_level', $request->level);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('question', 'like', "%{$search}%");
        }

        $questions = $query->latest()->paginate(10);
        $categories = Category::active()->get();

        return view('admin.questions.index', compact('questions', 'categories'));
    }

    public function create()
    {
        return redirect()->route('admin.questions.index');
    }

    public function store(Request $request)
    {
        $supportedLocales = config('app.supported_locales', ['tr', 'en']);

        $rules = [
            'question' => 'required|unique:questions,question',
            'category_id' => 'required|exists:categories,id',
            'correct_answer' => 'required|in:1,2,3,4',
            'question_level' => 'required|in:easy,medium,hard',
            'coin_value' => 'required|integer|min:1|max:100000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'nullable|in:on,1,true',
        ];

        // Add validation rules for each supported locale
        foreach ($supportedLocales as $locale) {
            if ($locale === 'tr') {
                $rules["question.{$locale}"] = 'required|string';
                $rules["one_choice.{$locale}"] = 'required|string|max:255';
                $rules["two_choice.{$locale}"] = 'required|string|max:255';
                $rules["three_choice.{$locale}"] = 'required|string|max:255';
                $rules["four_choice.{$locale}"] = 'required|string|max:255';
            } else {
                $rules["question.{$locale}"] = 'nullable|string';
                $rules["one_choice.{$locale}"] = 'nullable|string|max:255';
                $rules["two_choice.{$locale}"] = 'nullable|string|max:255';
                $rules["three_choice.{$locale}"] = 'nullable|string|max:255';
                $rules["four_choice.{$locale}"] = 'nullable|string|max:255';
            }
        }

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Question validation failed:', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Soru kaydedilemedi, lütfen tekrar deneyiniz.'
            ], 500);
        }

        $exists = \App\Models\Question::where("question->tr", $request->input("question.tr"))->exists();


        if ($exists) {
            return response()->json([
                'message' => 'Aynı soru zaten mevcut.'
            ], 500);
        }

        $question = new Question();

        // Set translations
        foreach ($supportedLocales as $locale) {
            if ($request->filled("question.{$locale}")) {
                $question->setTranslation('question', $locale, $request->input("question.{$locale}"));
            }
            if ($request->filled("one_choice.{$locale}")) {
                $question->setTranslation('one_choice', $locale, $request->input("one_choice.{$locale}"));
            }
            if ($request->filled("two_choice.{$locale}")) {
                $question->setTranslation('two_choice', $locale, $request->input("two_choice.{$locale}"));
            }
            if ($request->filled("three_choice.{$locale}")) {
                $question->setTranslation('three_choice', $locale, $request->input("three_choice.{$locale}"));
            }
            if ($request->filled("four_choice.{$locale}")) {
                $question->setTranslation('four_choice', $locale, $request->input("four_choice.{$locale}"));
            }
        }

        $question->category_id = $request->category_id;
        $question->correct_answer = $request->correct_answer;
        $question->question_level = $request->question_level;
        $question->coin_value = $request->coin_value;

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image')->store('questions', 'public');
            $question->image = $image;
        }

        $question->is_active = $request->has('is_active') && $request->is_active !== null;
        $question->save();

        if (!$question->save()) {
            \Log::error('Question save failed:', [
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Soru kaydedilemedi, lütfen tekrar deneyiniz.'
            ], 500);
        }

        return response()->json([
            'message' => 'Soru başarıyla oluşturuldu.'
        ], 200);

    }

    public function show(Question $question)
    {
        return redirect()->route('admin.questions.index');
    }

    public function edit(Question $question)
    {
        return redirect()->route('admin.questions.index');
    }

    public function update(Request $request, Question $question)
    {
        $supportedLocales = config('app.supported_locales', ['tr', 'en']);

        $rules = [
            'question' => 'required|unique:questions,question',
            'category_id' => 'required|exists:categories,id',
            'correct_answer' => 'required|in:1,2,3,4',
            'question_level' => 'required|in:easy,medium,hard',
            'coin_value' => 'required|integer|min:1|max:100000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'is_active' => 'nullable|in:on,1,true',
        ];

        // Add validation rules for each supported locale
        foreach ($supportedLocales as $locale) {
            if ($locale === 'tr') {
                $rules["question.{$locale}"] = 'required|string';
                $rules["one_choice.{$locale}"] = 'required|string|max:255';
                $rules["two_choice.{$locale}"] = 'required|string|max:255';
                $rules["three_choice.{$locale}"] = 'required|string|max:255';
                $rules["four_choice.{$locale}"] = 'required|string|max:255';
            } else {
                $rules["question.{$locale}"] = 'nullable|string';
                $rules["one_choice.{$locale}"] = 'nullable|string|max:255';
                $rules["two_choice.{$locale}"] = 'nullable|string|max:255';
                $rules["three_choice.{$locale}"] = 'nullable|string|max:255';
                $rules["four_choice.{$locale}"] = 'nullable|string|max:255';
            }
        }

        try {
            $request->validate($rules);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Question update validation failed:', [
                'errors' => $e->errors(),
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Soru güncellenemedi, lütfen tekrar deneyiniz.'
            ], 500);
        }



        // Set translations
        foreach ($supportedLocales as $locale) {
            if ($request->filled("question.{$locale}")) {
                $question->setTranslation('question', $locale, $request->input("question.{$locale}"));
            }
            if ($request->filled("one_choice.{$locale}")) {
                $question->setTranslation('one_choice', $locale, $request->input("one_choice.{$locale}"));
            }
            if ($request->filled("two_choice.{$locale}")) {
                $question->setTranslation('two_choice', $locale, $request->input("two_choice.{$locale}"));
            }
            if ($request->filled("three_choice.{$locale}")) {
                $question->setTranslation('three_choice', $locale, $request->input("three_choice.{$locale}"));
            }
            if ($request->filled("four_choice.{$locale}")) {
                $question->setTranslation('four_choice', $locale, $request->input("four_choice.{$locale}"));
            }
        }

        $question->category_id = $request->category_id;
        $question->correct_answer = $request->correct_answer;
        $question->question_level = $request->question_level;
        $question->coin_value = $request->coin_value;

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($question->image && \Storage::exists('public/' . $question->image)) {
                \Storage::delete('public/' . $question->image);
            }

            $image = $request->file('image')->store('questions', 'public');
            $question->image = $image;
        }

        // Handle image removal
        if ($request->has('remove_image') && $request->remove_image == '1') {
            // Delete old image if exists
            if ($question->image && \Storage::exists('public/' . $question->image)) {
                \Storage::delete('public/' . $question->image);
            }
            $question->image = null;
        }

        $question->is_active = $request->has('is_active') && $request->is_active !== null;
        $question->save();

        if (!$question->save()) {
            \Log::error('Question save failed:', [
                'request' => $request->all()
            ]);
            return response()->json([
                'message' => 'Soru güncellenemedi, lütfen tekrar deneyiniz.'
            ], 500);
        }



        $webhook = new WebhookController();
        $webhook->questionUpdated($question, $question->id);

        return response()->json([
            'message' => 'Soru başarıyla güncellendi.'
        ], 200);
    }

    public function destroy(Question $question)
    {
        // Check if question has answers
        if ($question->answers()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Bu soruya ait cevaplar bulunduğu için silinemez.'
            ], 422);
        }

        $question->delete();
        return response()->json([
            'success' => true,
            'message' => 'Soru başarıyla silindi.'
        ]);
    }
}
