<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\QuestionAnswerStatsService;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionAdminLog;
use App\Models\QuestionAnswerStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QuestionAnswerStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view answer statistics')->only(['index', 'showLogs', 'showDetail', 'optionAnswers']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':edit answer statistics')->only(['updateLevel', 'updateStatus', 'refresh']);
    }

    public function index(Request $request)
    {
        $search = $request->filled('search') ? trim((string) $request->search) : '';
        $isIdSearch = $search !== '' && ctype_digit($search);

        $query = Question::query()
            ->with(['category', 'answerStat'])
            ->join('question_answer_stats as qas', 'questions.id', '=', 'qas.question_id')
            ->where('qas.total_answers', '>', 0)
            ->select('questions.*');

        // Saf ID araması: diğer filtreleri yok say, doğrudan soruyu getir
        if ($isIdSearch) {
            $query->where('questions.id', (int) $search);
        } else {
        if ($request->filled('category_id')) {
            $query->where('questions.category_id', $request->category_id);
        }

        if ($request->filled('level')) {
            $query->where('questions.question_level', $request->level);
        }

        if ($request->filled('admin_status')) {
            $query->where('questions.admin_status', $request->admin_status);
        }

        if ($request->filled('observed_difficulty')) {
            if ($request->observed_difficulty === 'insufficient') {
                $query->where(function ($q) {
                    $q->where('qas.data_sufficient', false)
                        ->orWhere('qas.observed_difficulty', 'insufficient');
                });
            } else {
                $query->where('qas.observed_difficulty', $request->observed_difficulty)
                    ->where('qas.data_sufficient', true);
            }
        }

        // Uyumsuz: sadece güvenilir örneklem (5+ cevap)
        if ($request->filled('mismatch') && $request->mismatch === '1') {
            $query->where('qas.total_answers', '>=', 5)
                ->where('qas.data_sufficient', true)
                ->whereColumn('questions.question_level', '!=', 'qas.observed_difficulty')
                ->whereIn('qas.observed_difficulty', ['easy', 'medium', 'hard']);
        }

        if ($request->filled('confidence')) {
            if ($request->confidence === 'weak') {
                $query->whereBetween('qas.total_answers', [1, 2]);
            } elseif ($request->confidence === 'medium') {
                $query->whereBetween('qas.total_answers', [3, 4]);
            } elseif ($request->confidence === 'reliable') {
                $query->where('qas.total_answers', '>=', 5);
            }
        }

        // Şüpheli şık: doğru şık < %10 VEYA yanlış bir şık ≥ %70 (min 3 cevap)
        if ($request->filled('suspicious') && $request->suspicious === '1') {
            $query->where('qas.total_answers', '>=', 3)
                ->where(function ($q) {
                    $q->where(function ($correctLow) {
                        $correctLow->where(function ($inner) {
                            $inner->where('questions.correct_answer', '1')
                                ->whereRaw('(qas.option_1_count / qas.total_answers * 100) < 10');
                        })->orWhere(function ($inner) {
                            $inner->where('questions.correct_answer', '2')
                                ->whereRaw('(qas.option_2_count / qas.total_answers * 100) < 10');
                        })->orWhere(function ($inner) {
                            $inner->where('questions.correct_answer', '3')
                                ->whereRaw('(qas.option_3_count / qas.total_answers * 100) < 10');
                        })->orWhere(function ($inner) {
                            $inner->where('questions.correct_answer', '4')
                                ->whereRaw('(qas.option_4_count / qas.total_answers * 100) < 10');
                        });
                    })->orWhere(function ($wrongHigh) {
                        $wrongHigh->where(function ($inner) {
                            $inner->where('questions.correct_answer', '!=', '1')
                                ->whereRaw('(qas.option_1_count / qas.total_answers * 100) >= 70');
                        })->orWhere(function ($inner) {
                            $inner->where('questions.correct_answer', '!=', '2')
                                ->whereRaw('(qas.option_2_count / qas.total_answers * 100) >= 70');
                        })->orWhere(function ($inner) {
                            $inner->where('questions.correct_answer', '!=', '3')
                                ->whereRaw('(qas.option_3_count / qas.total_answers * 100) >= 70');
                        })->orWhere(function ($inner) {
                            $inner->where('questions.correct_answer', '!=', '4')
                                ->whereRaw('(qas.option_4_count / qas.total_answers * 100) >= 70');
                        });
                    });
                });
        }

        // EN çevirisi olmayan sorular
        if ($request->filled('missing_en') && $request->missing_en === '1') {
            $query->where(function ($q) {
                $q->whereRaw("JSON_EXTRACT(questions.question, '$.en') IS NULL")
                    ->orWhereRaw("TRIM(JSON_UNQUOTE(JSON_EXTRACT(questions.question, '$.en'))) = ''")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(questions.question, '$.en')) = 'null'");
            });
        }

        if ($request->filled('success_min')) {
            $query->where('qas.correct_percentage', '>=', (float) $request->success_min)
                ->where('qas.data_sufficient', true);
        }

        if ($request->filled('success_max')) {
            $query->where('qas.correct_percentage', '<=', (float) $request->success_max)
                ->where('qas.data_sufficient', true);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('questions.id', $search)
                    ->orWhere('questions.question', 'like', '%' . $search . '%');
            });
        }
        } // end !isIdSearch

        $sort = $request->get('sort', 'total_answers');
        $dir = $request->get('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'id' => 'questions.id',
            'total_answers' => 'qas.total_answers',
            'correct_percentage' => 'qas.correct_percentage',
            'last_calculated_at' => 'qas.last_calculated_at',
        ];

        $sortColumn = $allowedSorts[$sort] ?? 'qas.total_answers';

        $query->orderBy($sortColumn, $dir)
            ->orderBy('questions.id', 'desc');

        $questions = $query->paginate(25)->withQueryString();
        $categories = Category::orderBy('id')->get();
        $minAnswers = (int) config('app.question_stats_min_answers', 1);
        $lastCalculated = QuestionAnswerStat::max('last_calculated_at');
        $chartData = Cache::remember('qas.chart_data.v1', 60, function () {
            return $this->buildChartData();
        });

        return view('admin.question-answer-stats.index', compact(
            'questions',
            'categories',
            'minAnswers',
            'lastCalculated',
            'chartData'
        ));
    }

    private function buildChartData(): array
    {
        // Tek sorguda özet + güven kovaları + başarı bantları
        $agg = DB::table('question_answer_stats')
            ->where('total_answers', '>', 0)
            ->selectRaw("
                COUNT(*) as analyzed,
                COALESCE(SUM(total_answers), 0) as total_answers,
                COALESCE(AVG(correct_percentage), 0) as avg_success,
                SUM(CASE WHEN total_answers = 1 THEN 1 ELSE 0 END) as a1,
                SUM(CASE WHEN total_answers = 2 THEN 1 ELSE 0 END) as a2,
                SUM(CASE WHEN total_answers BETWEEN 3 AND 4 THEN 1 ELSE 0 END) as a34,
                SUM(CASE WHEN total_answers >= 5 THEN 1 ELSE 0 END) as a5,
                SUM(CASE WHEN correct_percentage < 40 THEN 1 ELSE 0 END) as hard_band,
                SUM(CASE WHEN correct_percentage >= 40 AND correct_percentage < 70 THEN 1 ELSE 0 END) as medium_band,
                SUM(CASE WHEN correct_percentage >= 70 THEN 1 ELSE 0 END) as easy_band
            ")
            ->first();

        $totalAnalyzed = (int) ($agg->analyzed ?? 0);
        $totalAnswers = (int) ($agg->total_answers ?? 0);
        $avgSuccess = round((float) ($agg->avg_success ?? 0), 1);
        $weakCount = (int) (($agg->a1 ?? 0) + ($agg->a2 ?? 0));
        $mediumConfCount = (int) ($agg->a34 ?? 0);
        $reliableCount = (int) ($agg->a5 ?? 0);

        $observed = DB::table('question_answer_stats')
            ->where('total_answers', '>', 0)
            ->selectRaw('observed_difficulty, COUNT(*) as cnt')
            ->groupBy('observed_difficulty')
            ->pluck('cnt', 'observed_difficulty')
            ->toArray();

        $defined = DB::table('questions')
            ->join('question_answer_stats as qas', 'questions.id', '=', 'qas.question_id')
            ->where('qas.total_answers', '>', 0)
            ->selectRaw('questions.question_level, COUNT(*) as cnt')
            ->groupBy('questions.question_level')
            ->pluck('cnt', 'question_level')
            ->toArray();

        $mismatchBase = DB::table('questions')
            ->join('question_answer_stats as qas', 'questions.id', '=', 'qas.question_id')
            ->where('qas.total_answers', '>=', 5)
            ->where('qas.data_sufficient', true)
            ->whereIn('qas.observed_difficulty', ['easy', 'medium', 'hard'])
            ->whereColumn('questions.question_level', '!=', 'qas.observed_difficulty');

        $mismatch = (clone $mismatchBase)->count();

        $levelLabel = ['easy' => 'Kolay', 'medium' => 'Orta', 'hard' => 'Zor'];
        $mismatchPairs = (clone $mismatchBase)
            ->selectRaw('questions.question_level as defined_level, qas.observed_difficulty as observed_level, COUNT(*) as cnt')
            ->groupBy('questions.question_level', 'qas.observed_difficulty')
            ->orderByDesc('cnt')
            ->get()
            ->map(function ($row) use ($levelLabel) {
                return [
                    'from' => $levelLabel[$row->defined_level] ?? $row->defined_level,
                    'to' => $levelLabel[$row->observed_level] ?? $row->observed_level,
                    'count' => (int) $row->cnt,
                ];
            })
            ->values()
            ->all();

        $mismatchRows = (clone $mismatchBase)
            ->orderByDesc('qas.total_answers')
            ->limit(8)
            ->get([
                'questions.id',
                'questions.question',
                'questions.question_level',
                'qas.observed_difficulty',
                'qas.correct_percentage',
                'qas.total_answers',
            ]);

        $mismatchList = [];
        foreach ($mismatchRows as $q) {
            $raw = $q->question;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $text = is_array($decoded) ? ($decoded['tr'] ?? reset($decoded) ?: $raw) : $raw;
            } else {
                $text = (string) $raw;
            }
            $mismatchList[] = [
                'id' => $q->id,
                'text' => \Illuminate\Support\Str::limit(strip_tags((string) $text), 50),
                'defined' => $q->question_level,
                'observed' => $q->observed_difficulty,
                'pct' => round((float) $q->correct_percentage, 1),
                'total' => (int) $q->total_answers,
            ];
        }

        $topHard = DB::table('questions')
            ->join('question_answer_stats as qas', 'questions.id', '=', 'qas.question_id')
            ->where('qas.total_answers', '>=', 3)
            ->where('qas.data_sufficient', true)
            ->orderBy('qas.correct_percentage')
            ->orderByDesc('qas.total_answers')
            ->limit(8)
            ->get([
                'questions.id',
                'questions.question',
                'qas.correct_percentage',
                'qas.total_answers',
            ]);

        $labels = [];
        $values = [];
        $totals = [];
        $ids = [];
        foreach ($topHard as $q) {
            $raw = $q->question;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                $text = is_array($decoded) ? ($decoded['tr'] ?? reset($decoded) ?: $raw) : $raw;
            } else {
                $text = (string) $raw;
            }
            $labels[] = '#' . $q->id . ' · ' . \Illuminate\Support\Str::limit(strip_tags((string) $text), 28);
            $values[] = round((float) $q->correct_percentage, 1);
            $totals[] = (int) $q->total_answers;
            $ids[] = (int) $q->id;
        }

        return [
            'summary' => [
                'analyzed' => $totalAnalyzed,
                'total_answers' => $totalAnswers,
                'avg_success' => $avgSuccess,
                'mismatch' => $mismatch,
                'reliable' => $reliableCount,
                'weak' => $weakCount,
                'medium_conf' => $mediumConfCount,
                'answer_buckets' => [
                    '1' => (int) ($agg->a1 ?? 0),
                    '2' => (int) ($agg->a2 ?? 0),
                    '3_4' => (int) ($agg->a34 ?? 0),
                    '5_plus' => (int) ($agg->a5 ?? 0),
                ],
                'avg_answers_per_question' => $totalAnalyzed > 0
                    ? round($totalAnswers / $totalAnalyzed, 2)
                    : 0,
                'mismatch_pairs' => $mismatchPairs,
                'mismatch_list' => $mismatchList,
            ],
            'observed' => [
                'easy' => (int) ($observed['easy'] ?? 0),
                'medium' => (int) ($observed['medium'] ?? 0),
                'hard' => (int) ($observed['hard'] ?? 0),
                'insufficient' => (int) ($observed['insufficient'] ?? 0),
            ],
            'defined' => [
                'easy' => (int) ($defined['easy'] ?? 0),
                'medium' => (int) ($defined['medium'] ?? 0),
                'hard' => (int) ($defined['hard'] ?? 0),
            ],
            'success_bands' => [
                'hard' => (int) ($agg->hard_band ?? 0),
                'medium' => (int) ($agg->medium_band ?? 0),
                'easy' => (int) ($agg->easy_band ?? 0),
            ],
            'top_hard' => [
                'labels' => $labels,
                'values' => $values,
                'totals' => $totals,
                'ids' => $ids,
            ],
        ];
    }

    public function updateLevel(Request $request, Question $question)
    {
        $validated = $request->validate([
            'question_level' => 'required|in:easy,medium,hard',
        ]);

        $old = $question->question_level;
        $new = $validated['question_level'];

        if ($old === $new) {
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Zorluk seviyesi zaten aynı.', 'unchanged' => true]);
            }
            return back()->withFragment('qas-list')->with('info', 'Zorluk seviyesi zaten aynı.');
        }

        DB::transaction(function () use ($question, $old, $new) {
            $question->update(['question_level' => $new]);

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => Auth::id(),
                'action' => 'update_level',
                'field' => 'question_level',
                'old_value' => $old,
                'new_value' => $new,
            ]);
        });

        Cache::forget('qas.chart_data.v1');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Soru #{$question->id} zorluk seviyesi güncellendi.",
                'question_id' => $question->id,
                'field' => 'question_level',
                'value' => $new,
            ]);
        }

        return back()->withFragment('qas-list')->with('success', "Soru #{$question->id} zorluk seviyesi güncellendi.");
    }

    public function updateStatus(Request $request, Question $question)
    {
        $validated = $request->validate([
            'admin_status' => 'required|in:active,passive,maintenance',
        ]);

        $old = $question->admin_status ?? ($question->is_active ? 'active' : 'passive');
        $new = $validated['admin_status'];

        if ($old === $new) {
            if ($request->expectsJson()) {
                return response()->json(['success' => true, 'message' => 'Durum zaten aynı.', 'unchanged' => true]);
            }
            return back()->withFragment('qas-list')->with('info', 'Durum zaten aynı.');
        }

        DB::transaction(function () use ($question, $old, $new) {
            $question->update([
                'admin_status' => $new,
                'is_active' => $new === 'active',
            ]);

            QuestionAdminLog::create([
                'question_id' => $question->id,
                'admin_id' => Auth::id(),
                'action' => 'update_status',
                'field' => 'admin_status',
                'old_value' => $old,
                'new_value' => $new,
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Soru #{$question->id} durumu güncellendi.",
                'question_id' => $question->id,
                'field' => 'admin_status',
                'value' => $new,
            ]);
        }

        return back()->withFragment('qas-list')->with('success', "Soru #{$question->id} durumu güncellendi.");
    }

    public function refresh()
    {
        Cache::forget('qas.chart_data.v1');

        dispatch(function () {
            Artisan::call('questions:refresh-answer-stats');
            Cache::forget('qas.chart_data.v1');
        })->afterResponse();

        return back()->withFragment('qas-list')->with(
            'success',
            'İstatistik yenileme arka planda başlatıldı. Birkaç dakika sonra sayfayı yenileyin.'
        );
    }

    public function showDetail(Question $question)
    {
        $question->load(['category', 'answerStat']);
        $stat = $question->answerStat;
        $total = (int) ($stat->total_answers ?? 0);
        $confidence = $this->confidenceMeta($total);

        $choices = [
            '1' => [
                'label' => 'A',
                'text_tr' => $question->getTranslation('one_choice', 'tr', false) ?: $question->getTranslation('one_choice', 'tr'),
                'text_en' => $question->getTranslation('one_choice', 'en', false) ?: null,
                'count' => (int) ($stat->option_1_count ?? 0),
            ],
            '2' => [
                'label' => 'B',
                'text_tr' => $question->getTranslation('two_choice', 'tr', false) ?: $question->getTranslation('two_choice', 'tr'),
                'text_en' => $question->getTranslation('two_choice', 'en', false) ?: null,
                'count' => (int) ($stat->option_2_count ?? 0),
            ],
            '3' => [
                'label' => 'C',
                'text_tr' => $question->getTranslation('three_choice', 'tr', false) ?: $question->getTranslation('three_choice', 'tr'),
                'text_en' => $question->getTranslation('three_choice', 'en', false) ?: null,
                'count' => (int) ($stat->option_3_count ?? 0),
            ],
            '4' => [
                'label' => 'D',
                'text_tr' => $question->getTranslation('four_choice', 'tr', false) ?: $question->getTranslation('four_choice', 'tr'),
                'text_en' => $question->getTranslation('four_choice', 'en', false) ?: null,
                'count' => (int) ($stat->option_4_count ?? 0),
            ],
        ];

        foreach ($choices as $key => &$choice) {
            $choice['key'] = (string) $key;
            $choice['percent'] = $total > 0 ? round($choice['count'] / $total * 100, 1) : 0;
            $choice['is_correct'] = (string) $question->correct_answer === (string) $key;
            $choice['missing_en'] = empty($choice['text_en']);
        }
        unset($choice);

        $logs = QuestionAdminLog::with('admin')
            ->where('question_id', $question->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (QuestionAdminLog $log) {
                return [
                    'admin' => $log->admin->name ?? ('#' . $log->admin_id),
                    'field' => $log->field,
                    'old_value' => $log->old_value,
                    'new_value' => $log->new_value,
                    'created_at' => optional($log->created_at)->format('d.m.Y H:i'),
                ];
            });

        $image = $question->image;
        if (!empty($image) && !str_starts_with($image, 'http')) {
            $image = asset('storage/' . ltrim($image, '/'));
        }

        $textTr = $question->getTranslation('question', 'tr', false) ?: $question->getTranslation('question', 'tr');
        $textEn = $question->getTranslation('question', 'en', false) ?: null;

        return response()->json([
            'success' => true,
            'question' => [
                'id' => $question->id,
                'text_tr' => $textTr,
                'text_en' => $textEn,
                'missing_en' => empty($textEn),
                'category' => $question->category?->getTranslation('name', 'tr'),
                'level' => $question->question_level,
                'status' => $question->admin_status ?? ($question->is_active ? 'active' : 'passive'),
                'correct_answer' => (string) $question->correct_answer,
                'image' => $image,
                'choices' => array_values($choices),
                'stats' => [
                    'total' => $total,
                    'correct' => (int) ($stat->correct_count ?? 0),
                    'wrong' => (int) ($stat->wrong_count ?? 0),
                    'percentage' => round((float) ($stat->correct_percentage ?? 0), 1),
                    'observed' => $stat->observed_difficulty ?? 'insufficient',
                ],
                'confidence' => $confidence,
                'edit_url' => route('admin.questions.edit', $question),
                'suspicious' => $this->isSuspiciousDistribution($question, $stat, $total),
                'logs' => $logs,
            ],
        ]);
    }

    private function isSuspiciousDistribution(Question $question, $stat, int $total): bool
    {
        if ($total < 3 || !$stat) {
            return false;
        }

        $counts = [
            '1' => (int) ($stat->option_1_count ?? 0),
            '2' => (int) ($stat->option_2_count ?? 0),
            '3' => (int) ($stat->option_3_count ?? 0),
            '4' => (int) ($stat->option_4_count ?? 0),
        ];

        $correct = (string) $question->correct_answer;
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

    private function confidenceMeta(int $total): array
    {
        if ($total >= 5) {
            return ['key' => 'reliable', 'label' => 'Güvenilir', 'hint' => '5+ cevap'];
        }
        if ($total >= 3) {
            return ['key' => 'medium', 'label' => 'Orta', 'hint' => '3-4 cevap'];
        }

        return ['key' => 'weak', 'label' => 'Zayıf', 'hint' => '1-2 cevap'];
    }

    public function optionAnswers(Request $request, Question $question, string $option, QuestionAnswerStatsService $service)
    {
        if (!in_array($option, ['1', '2', '3', '4'], true)) {
            return response()->json(['success' => false, 'message' => 'Geçersiz şık.'], 422);
        }

        $page = max(1, (int) $request->get('page', 1));
        $result = $service->optionAnswerers((int) $question->id, $option, $page, 10);

        $labels = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
        $choiceText = match ($option) {
            '1' => $question->getTranslation('one_choice', 'tr', false) ?: $question->getTranslation('one_choice', 'tr'),
            '2' => $question->getTranslation('two_choice', 'tr', false) ?: $question->getTranslation('two_choice', 'tr'),
            '3' => $question->getTranslation('three_choice', 'tr', false) ?: $question->getTranslation('three_choice', 'tr'),
            '4' => $question->getTranslation('four_choice', 'tr', false) ?: $question->getTranslation('four_choice', 'tr'),
            default => '',
        };

        return response()->json([
            'success' => true,
            'question_id' => $question->id,
            'option' => $option,
            'option_label' => $labels[$option] ?? $option,
            'option_text' => $choiceText,
            'is_correct' => (string) $question->correct_answer === (string) $option,
            'answers' => $result,
        ]);
    }

    public function showLogs(Question $question)
    {
        $logs = QuestionAdminLog::with('admin')
            ->where('question_id', $question->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'logs' => $logs->map(function (QuestionAdminLog $log) {
                return [
                    'admin' => $log->admin->name ?? ('#' . $log->admin_id),
                    'action' => $log->action,
                    'field' => $log->field,
                    'old_value' => $log->old_value,
                    'new_value' => $log->new_value,
                    'created_at' => optional($log->created_at)->format('d.m.Y H:i'),
                ];
            }),
        ]);
    }
}
