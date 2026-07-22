<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\QuestionAnswerStatsService;
use App\Models\Category;
use App\Models\Question;
use App\Models\QuestionAdminLog;
use App\Models\QuestionAnswerStat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuestionAnswerStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':admin|personel');
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view answer statistics')->only(['index', 'showLogs']);
        $this->middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':edit answer statistics')->only(['updateLevel', 'updateStatus', 'refresh']);
    }

    public function index(Request $request)
    {
        $query = Question::query()
            ->with(['category', 'answerStat'])
            ->leftJoin('question_answer_stats as qas', 'questions.id', '=', 'qas.question_id')
            ->select('questions.*');

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
                    $q->whereNull('qas.id')
                        ->orWhere('qas.data_sufficient', false)
                        ->orWhere('qas.observed_difficulty', 'insufficient');
                });
            } else {
                $query->where('qas.observed_difficulty', $request->observed_difficulty)
                    ->where('qas.data_sufficient', true);
            }
        }

        if ($request->filled('success_min')) {
            $query->where('qas.correct_percentage', '>=', (float) $request->success_min)
                ->where('qas.data_sufficient', true);
        }

        if ($request->filled('success_max')) {
            $query->where('qas.correct_percentage', '<=', (float) $request->success_max)
                ->where('qas.data_sufficient', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('questions.id', $search)
                    ->orWhere('questions.question', 'like', '%' . $search . '%');
            });
        }

        $sort = $request->get('sort', 'correct_percentage');
        $dir = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = [
            'id' => 'questions.id',
            'total_answers' => 'qas.total_answers',
            'correct_percentage' => 'qas.correct_percentage',
            'last_calculated_at' => 'qas.last_calculated_at',
        ];

        $sortColumn = $allowedSorts[$sort] ?? 'qas.correct_percentage';
        $query->orderByRaw($sortColumn . ' IS NULL')
            ->orderBy($sortColumn, $dir)
            ->orderBy('questions.id', 'desc');

        $questions = $query->paginate(25)->withQueryString();
        $categories = Category::orderBy('id')->get();
        $minAnswers = (int) config('app.question_stats_min_answers', 20);
        $lastCalculated = QuestionAnswerStat::max('last_calculated_at');

        return view('admin.question-answer-stats.index', compact(
            'questions',
            'categories',
            'minAnswers',
            'lastCalculated'
        ));
    }

    public function updateLevel(Request $request, Question $question)
    {
        $validated = $request->validate([
            'question_level' => 'required|in:easy,medium,hard',
        ]);

        $old = $question->question_level;
        $new = $validated['question_level'];

        if ($old === $new) {
            return back()->with('info', 'Zorluk seviyesi zaten aynı.');
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

        return back()->with('success', "Soru #{$question->id} zorluk seviyesi güncellendi.");
    }

    public function updateStatus(Request $request, Question $question)
    {
        $validated = $request->validate([
            'admin_status' => 'required|in:active,passive,maintenance',
        ]);

        $old = $question->admin_status ?? ($question->is_active ? 'active' : 'passive');
        $new = $validated['admin_status'];

        if ($old === $new) {
            return back()->with('info', 'Durum zaten aynı.');
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

        return back()->with('success', "Soru #{$question->id} durumu güncellendi.");
    }

    public function refresh(QuestionAnswerStatsService $service)
    {
        $count = $service->refreshAll();

        return back()->with('success', "{$count} sorunun istatistikleri yenilendi.");
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
