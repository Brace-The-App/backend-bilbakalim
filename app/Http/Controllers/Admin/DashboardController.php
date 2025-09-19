<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Question;
use App\Models\Category;
use App\Models\Tournament;
use App\Models\Answer;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class.':admin|personel');
    }

    public function index()
    {
        // Dashboard statistics
        $stats = [
            'total_users' => User::count(),
            'total_questions' => Question::count(),
            'total_categories' => Category::count(),
            'total_tournaments' => Tournament::count(),
            'active_users' => User::where('account_status', 'active')->count(),
            'total_answers' => Answer::count(),
            'correct_answers' => Answer::where('is_correct', true)->count(),
        ];

        // Recent data
        $recent_users = User::latest()->take(5)->get();
        $recent_questions = Question::with('category')->latest()->take(5)->get();

        return view('admin.dashboard.index', compact('stats', 'recent_users', 'recent_questions'));
    }
}
