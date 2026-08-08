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
        $this->middleware(function ($request, $next) {
            if (!FinanceCoinService::canAccess($request->user())) {
                abort(403, 'Bu sayfaya erişim yetkiniz yok.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $range = (string) $request->query('range', '');
        $todayStart = Carbon::parse(tr_now()->toDateString())->startOfDay();
        $agreedFrom = Carbon::parse(FinanceService::rateFor($todayStart)->effective_from)->startOfDay();

        $firstPaid = \App\Models\Payment::query()
            ->completed()
            ->selectRaw('MIN(COALESCE(paid_at, created_at)) as first_at')
            ->value('first_at');
        $allFrom = $firstPaid
            ? Carbon::parse($firstPaid)->startOfDay()
            : Carbon::parse('2020-01-01')->startOfDay();

        if ($request->filled('from') && $request->filled('to') && $range === '') {
            $from = Carbon::parse($request->query('from'))->startOfDay();
            $to = Carbon::parse($request->query('to'))->endOfDay();
            $range = 'custom';
        } elseif ($range === 'agreed' || $range === 'from_today') {
            $range = 'agreed';
            $from = $agreedFrom->copy();
            $to = now()->endOfDay();
        } elseif ($range === 'all') {
            $from = $allFrom;
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
            'agreedFrom'
        ));
    }
}
