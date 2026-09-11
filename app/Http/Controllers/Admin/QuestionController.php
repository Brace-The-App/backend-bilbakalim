<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use App\Models\Category;
use App\Http\Controllers\WebhookController;
use App\Http\Services\QuestionAnswerStatsService;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':view questions')->only(['index', 'show']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':create questions')->only(['create', 'store']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':edit questions')->only(['edit', 'update', 'toggleCheck', 'toggleActive', 'bulkUpdateActive', 'bulkUpdateActiveByLevel', 'bulkFixObservedLevel']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class.':delete questions')->only(['destroy']);
    }

    public function index(Request $request)
    {
        $query = Question::with(['category', 'answerStat', 'aiQualityReview:id,question_id,status,recommended_action,quality_score']);

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

        // Filtering - Category
        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        // Filtering - Kontrol (check)
        if ($request->filled('check')) {
            if ($request->check === '0' || $request->check === 0 || $request->check === 'false') {
                $query->where(function ($q) {
                    $q->where('check', false)->orWhereNull('check');
                });
            } elseif ($request->check === '1' || $request->check === 1 || $request->check === 'true') {
                $query->where('check', true);
            }
        }

        // Filtering - AI kabul
        if ($request->filled('ai_accepted')) {
            if ($request->ai_accepted === '1' || $request->ai_accepted === 1 || $request->ai_accepted === 'true') {
                $query->where('ai_accepted', true);
            } elseif ($request->ai_accepted === '0' || $request->ai_accepted === 0 || $request->ai_accepted === 'false') {
                $query->where(function ($q) {
                    $q->where('ai_accepted', false)->orWhereNull('ai_accepted');
                });
            }
        }

        // Filtering - Search (ID veya soru metni)
        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                if (ctype_digit($search)) {
                    $q->where('id', (int) $search);
                } else {
                    $q->whereRaw("JSON_EXTRACT(question, '$.tr') LIKE ?", ["%{$search}%"])
                        ->orWhereRaw("JSON_EXTRACT(question, '$.en') LIKE ?", ["%{$search}%"]);
                }
            });
        }

        // Filtering - Language (dil filtresi - birden fazla seçilebilir)
        if ($request->filled('languages')) {
            $languages = is_array($request->languages) ? $request->languages : [$request->languages];
            $query->where(function ($q) use ($languages) {
                foreach ($languages as $lang) {
                    if ($lang === 'tr') {
                        $q->orWhereRaw("JSON_EXTRACT(question, '$.tr') IS NOT NULL AND JSON_EXTRACT(question, '$.tr') != ''");
                    } elseif ($lang === 'en') {
                        $q->orWhereRaw("JSON_EXTRACT(question, '$.en') IS NOT NULL AND JSON_EXTRACT(question, '$.en') != ''");
                    }
                }
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 25;
        }

        $page = max(1, (int) $request->input('page', 1));

        $filterSignature = [
            'status' => $request->input('status'),
            'level' => $request->input('level'),
            'category_id' => $request->input('category_id'),
            'check' => $request->input('check'),
            'search' => $request->input('search'),
            'languages' => $request->input('languages'),
        ];

        $filterStats = \App\Services\AdminQuestionStats::filterStats($query, $filterSignature);
        $filteredTotalCount = $filterStats['filtered_total'];
        $languageCounts = $filterStats['language_counts'];
        $bilingualCount = $filterStats['bilingual'];
        $trOnlyCount = $filterStats['tr_only'];
        $enOnlyCount = $filterStats['en_only'];

        $lastPage = max(1, (int) ceil(max($filteredTotalCount, 1) / $perPage));
        if ($filteredTotalCount === 0) {
            $lastPage = 1;
        }
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $items = (clone $query)
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        $questions = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $filteredTotalCount,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'page',
            ]
        );

        $categories = Category::active()->get();
        $summary = \App\Services\AdminQuestionStats::summary();

        return view('admin.questions.index', compact(
            'questions',
            'categories',
            'filteredTotalCount',
            'languageCounts',
            'bilingualCount',
            'trOnlyCount',
            'enOnlyCount',
            'summary',
            'perPage'
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
        return redirect()->route('admin.questions.index', [
            'search' => $question->id,
            'edit' => $question->id,
        ]);
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

    /**
     * Seçili soruların aktif/pasif durumunu toplu güncelle (AJAX).
     */
    public function bulkUpdateActive(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer|exists:questions,id',
            'is_active' => 'required|boolean',
        ]);

        $isActive = (bool) $validated['is_active'];
        $updated = Question::query()
            ->whereIn('id', $validated['ids'])
            ->where('is_active', '!=', $isActive)
            ->update([
                'is_active' => $isActive,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'total' => count($validated['ids']),
            'is_active' => $isActive,
            'message' => $updated > 0
                ? ($isActive ? "{$updated} soru aktif edildi." : "{$updated} soru pasif edildi.")
                : 'Seçili sorular zaten istenen durumda.',
        ]);
    }

    /**
     * Zorluk seviyesine göre tüm soruların aktif/pasif durumunu toplu güncelle (AJAX).
     */
    public function bulkUpdateActiveByLevel(Request $request)
    {
        $validated = $request->validate([
            'question_level' => 'required|in:easy,medium,hard,medium_hard,all',
            'is_active' => 'required|boolean',
            'dry_run' => 'nullable|boolean',
        ]);

        $isActive = (bool) $validated['is_active'];
        $dryRun = $request->boolean('dry_run');

        $query = Question::query();
        $this->applyBulkLevelScope($query, $validated['question_level']);

        $affectedQuery = (clone $query)->where('is_active', '!=', $isActive);
        $count = (clone $affectedQuery)->count();

        $levelLabel = $this->bulkLevelLabel($validated['question_level']);

        if ($dryRun) {
            return response()->json([
                'success' => true,
                'count' => $count,
                'question_level' => $validated['question_level'],
                'is_active' => $isActive,
                'level_label' => $levelLabel,
            ]);
        }

        if ($count === 0) {
            return response()->json([
                'success' => true,
                'updated' => 0,
                'message' => "{$levelLabel} sorular zaten " . ($isActive ? 'aktif' : 'pasif') . '.',
            ]);
        }

        $updated = $affectedQuery->update([
            'is_active' => $isActive,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'question_level' => $validated['question_level'],
            'is_active' => $isActive,
            'message' => $updated . ' ' . mb_strtolower($levelLabel) . ' soru ' . ($isActive ? 'aktif' : 'pasif') . ' edildi.',
        ]);
    }

    private function applyBulkLevelScope($query, string $level): void
    {
        match ($level) {
            'easy' => $query->where('question_level', 'easy'),
            'medium' => $query->where('question_level', 'medium'),
            'hard' => $query->where('question_level', 'hard'),
            'medium_hard' => $query->whereIn('question_level', ['medium', 'hard']),
            'all' => null,
        };
    }

    private function bulkLevelLabel(string $level): string
    {
        return match ($level) {
            'easy' => 'Kolay',
            'medium' => 'Orta',
            'hard' => 'Zor',
            'medium_hard' => 'Orta + Zor',
            'all' => 'Tüm',
            default => $level,
        };
    }

    /**
     * Seçili sorularda tanımlı zorluğu gözlenen zorluğa çeker (güvenilir istatistik).
     */
    public function bulkFixObservedLevel(Request $request, QuestionAnswerStatsService $statsService)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer|exists:questions,id',
            'dry_run' => 'nullable|boolean',
        ]);

        $adminId = (int) auth()->id();
        $dryRun = $request->boolean('dry_run');
        $questions = Question::query()
            ->with('answerStat')
            ->whereIn('id', $validated['ids'])
            ->get();

        $fixable = 0;
        $skipped = 0;
        $samples = [];

        foreach ($questions as $question) {
            $canFix = $question->hasLevelMismatch();
            if ($canFix) {
                $fixable++;
                if (count($samples) < 5) {
                    $samples[] = [
                        'id' => $question->id,
                        'from' => $question->question_level,
                        'to' => $question->answerStat?->observed_difficulty,
                    ];
                }
            } else {
                $skipped++;
            }
        }

        if ($dryRun) {
            return response()->json([
                'success' => true,
                'fixable' => $fixable,
                'skipped' => $skipped,
                'total' => $questions->count(),
                'samples' => $samples,
            ]);
        }

        $fixed = 0;
        foreach ($questions as $question) {
            $result = $statsService->fixQuestionToObservedLevel($question, $adminId, 'bulk_fix_level');
            if ($result) {
                $fixed++;
            }
        }

        return response()->json([
            'success' => true,
            'fixed' => $fixed,
            'skipped' => $questions->count() - $fixed,
            'message' => $fixed > 0
                ? "{$fixed} sorunun zorluğu gözlenen seviyeye düzeltildi."
                : 'Düzeltilebilecek uyumsuz soru bulunamadı (yeterli istatistik gerekir).',
        ]);
    }

    /**
     * Soru aktif/pasif durumunu aç/kapat (AJAX).
     */
    public function toggleActive(Question $question)
    {
        $question->is_active = !$question->is_active;
        $question->save();

        $webhook = new WebhookController();
        $webhook->questionUpdated($question, $question->id);

        return response()->json([
            'success' => true,
            'is_active' => (bool) $question->is_active,
            'message' => $question->is_active ? 'Soru aktif edildi.' : 'Soru pasif edildi.',
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
