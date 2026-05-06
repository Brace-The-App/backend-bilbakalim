<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\Category;
use App\Http\Controllers\WebhookController;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view questions')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create questions')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit questions')->only(['edit', 'update', 'toggleCheck']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete questions')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Question::with('category');

        // Filtering - Status
        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === '1' || $status === 1 || $status === 'true') {
                $query->where('is_active', true);
            } elseif ($status === '0' || $status === 0 || $status === 'false') {
                $query->where('is_active', false);
            }
        }

        // Filtering - Level
        if ($request->filled('level')) {
            $query->where('question_level', $request->level);
        }

        // Filtering - Search (translatable field için JSON arama)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereRaw("JSON_EXTRACT(question, '$.tr') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_EXTRACT(question, '$.en') LIKE ?", ["%{$search}%"]);
            });
        }

        // Filtering - Language (dil filtresi - birden fazla seçilebilir)
        if ($request->filled('languages')) {
            $languages = is_array($request->languages) ? $request->languages : [$request->languages];
            $query->where(function($q) use ($languages) {
                foreach ($languages as $lang) {
                    if ($lang === 'tr') {
                        $q->orWhereRaw("JSON_EXTRACT(question, '$.tr') IS NOT NULL AND JSON_EXTRACT(question, '$.tr') != ''");
                    } elseif ($lang === 'en') {
                        $q->orWhereRaw("JSON_EXTRACT(question, '$.en') IS NOT NULL AND JSON_EXTRACT(question, '$.en') != ''");
                    }
                }
            });
        }

        $supportedLocales = config('app.supported_locales', ['tr', 'en']);

        $filteredTotalCount = (clone $query)->count();
        $languageCounts = [];

        foreach ($supportedLocales as $locale) {
            $languageCounts[$locale] = (clone $query)
                ->whereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.\"{$locale}\"')), '')) != ''")
                ->count();
        }

        $bilingualCount = null;
        $trOnlyCount = null;
        $enOnlyCount = null;

        if (in_array('tr', $supportedLocales, true) && in_array('en', $supportedLocales, true)) {
            $bilingualCount = (clone $query)
                ->whereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')), '')) != ''")
                ->whereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.en')), '')) != ''")
                ->count();

            $trOnlyCount = (clone $query)
                ->whereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')), '')) != ''")
                ->whereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.en')), '')) = ''")
                ->count();

            $enOnlyCount = (clone $query)
                ->whereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.en')), '')) != ''")
                ->whereRaw("TRIM(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(question, '$.tr')), '')) = ''")
                ->count();
        }

        $questions = $query->latest()->paginate(10);
        $categories = Category::active()->get();

        return view('admin.questions.index', compact(
            'questions',
            'categories',
            'filteredTotalCount',
            'languageCounts',
            'bilingualCount',
            'trOnlyCount',
            'enOnlyCount'
        ));
    }

    public function create()
    {
        return redirect()->route('admin.questions.index');
    }

    public function store(Request $request)
    {
        $supportedLocales = config('app.supported_locales', ['tr', 'en']);

        $rules = [
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
                $rules["question.{$locale}"] = [
                    'required',
                    'string',
                    Rule::unique('questions', "question->{$locale}"),
                ];
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
                'message' => 'Doğrulama hatası.',
                'errors' => $e->errors(),
            ], 422);
        }

        $question = new Question();

        // Set translations
        foreach ($supportedLocales as $locale) {
            // TR zorunlu; diğer diller opsiyonel. Boş string gelirse DB'de tutulmasın.
            foreach (['question', 'one_choice', 'two_choice', 'three_choice', 'four_choice'] as $field) {
                $key = "{$field}.{$locale}";
                if (!$request->has($key)) {
                    continue;
                }

                $value = $request->input($key);
                $value = is_string($value) ? trim($value) : $value;

                if ($value === '' || $value === null) {
                    // create'te boş bırakılan locale için hiçbir şey set etmiyoruz
                    continue;
                }

                $question->setTranslation($field, $locale, $value);
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
                $rules["question.{$locale}"] = [
                    'required',
                    'string',
                    Rule::unique('questions', "question->{$locale}")->ignore($question->id),
                ];
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
                'message' => 'Doğrulama hatası.',
                'errors' => $e->errors(),
            ], 422);
        }



        // Set translations (boş gelen EN alanları DB'den sil)
        foreach ($supportedLocales as $locale) {
            foreach (['question', 'one_choice', 'two_choice', 'three_choice', 'four_choice'] as $field) {
                $key = "{$field}.{$locale}";
                if (!$request->has($key)) {
                    continue;
                }

                $value = $request->input($key);
                $value = is_string($value) ? trim($value) : $value;

                // TR alanları zorunlu; boş olamaz (validation yakalar)
                if ($locale !== 'tr' && ($value === '' || $value === null)) {
                    // İlgili locale çevirisini kaldır
                    $question->forgetTranslation($field, $locale);
                    continue;
                }

                if ($value !== '' && $value !== null) {
                    $question->setTranslation($field, $locale, $value);
                }
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

    /**
     * Soru "kontrol edildi" işaretini aç/kapat (AJAX).
     */
    public function toggleCheck(Question $question)
    {
        $question->check = !$question->check;
        $question->save();

        return response()->json([
            'success' => true,
            'check' => (int) $question->check,
            'message' => $question->check ? 'Soru kontrol edildi olarak işaretlendi.' : 'Soru kontrol edilmedi olarak işaretlendi.'
        ]);
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
