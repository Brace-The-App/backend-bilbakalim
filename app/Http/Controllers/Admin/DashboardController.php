<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Answer;
use App\Models\Category;
use App\Models\Duel;
use App\Models\Question;
use App\Models\RewardRequest;
use App\Models\User;
use App\Http\Services\WebhookService;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
    }

    public function index()
    {
        $onlineUserIds = app(WebhookService::class)->getOnlineUserIds();

        $stats = [
            'total_users' => User::count(),
            // Aktif = socket'e bağlı (gerçek çevrimiçi)
            'active_users' => count($onlineUserIds),
            'total_questions' => Question::count(),
            'total_categories' => Category::count(),
            'pending_rewards' => RewardRequest::where('status', 'pending')->count(),
            'finished_duels' => Duel::where('status', 'finished')->count(),
            'total_answers' => Answer::count(),
            'correct_answers' => Answer::where('is_correct', true)->count(),
            'active_ads' => Ad::where('is_active', true)->count(),
            'today_users' => User::whereDate('created_at', Carbon::today())->count(),
        ];

        $recent_users = User::with('avatarModel')->latest()->take(5)->get();
        $recent_questions = Question::with('category')->latest()->take(5)->get();

        // Son 7 gün yeni kayıt + biten düello (mini grafik)
        $chartLabels = [];
        $chartRegistrations = [];
        $chartDuels = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $chartLabels[] = $day->translatedFormat('d M');
            $chartRegistrations[] = User::whereDate('created_at', $day)->count();
            $chartDuels[] = Duel::where('status', 'finished')
                ->where(function ($q) use ($day) {
                    $q->whereDate('finished_at', $day)
                        ->orWhere(function ($q2) use ($day) {
                            $q2->whereNull('finished_at')->whereDate('updated_at', $day);
                        });
                })
                ->count();
        }

        $chartData = [
            'labels' => $chartLabels,
            'registrations' => $chartRegistrations,
            'duels' => $chartDuels,
        ];

        return view('admin.dashboard.index', compact(
            'stats',
            'recent_users',
            'recent_questions',
            'chartData'
        ));
    }
}
