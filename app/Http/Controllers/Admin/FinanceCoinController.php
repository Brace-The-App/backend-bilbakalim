<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinanceCoinService;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinanceCoinController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(\Spatie\Permission\Middleware\RoleMiddleware::class . ':admin|personel');
    }

    public function index(Request $request)
    {
        $range = (string) $request->query('range', '');
        $todayStart = Carbon::parse(tr_now()->toDateString())->startOfDay();
        $agreedFrom = Carbon::parse(FinanceService::rateFor($todayStart)->effective_from)->startOfDay();
        $allFrom = FinanceService::allTimeFrom();
        $reportingEpoch = FinanceService::reportingEpochFrom();

        if ($request->filled('from') && $request->filled('to') && $range === '') {
            $from = Carbon::parse($request->query('from'))->startOfDay();
            $to = Carbon::parse($request->query('to'))->endOfDay();
            $range = 'custom';
        } elseif ($range === 'agreed' || $range === 'from_today') {
            $range = 'agreed';
            $from = $agreedFrom->copy();
            $to = now()->endOfDay();
        } elseif ($range === 'all') {
            $from = $allFrom->copy();
            $to = now()->endOfDay();
        } elseif ($range === 'month') {
            $from = now()->startOfMonth()->startOfDay();
            $to = now()->endOfDay();
        } elseif ($range === 'last_month') {
            $from = now()->subMonthNoOverflow()->startOfMonth()->startOfDay();
            $to = now()->subMonthNoOverflow()->endOfMonth()->endOfDay();
        } elseif ($range === '90d') {
            $from = now()->subDays(89)->startOfDay();
            $to = now()->endOfDay();
        } else {
            $range = 'today';
            $from = $todayStart->copy();
            $to = $todayStart->copy()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $summary = FinanceCoinService::summarize($from, $to);

        return view('admin.finance.coin.index', compact(
            'summary',
            'from',
            'to',
            'range',
            'agreedFrom',
            'reportingEpoch',
            'allFrom'
        ));
    }
}
