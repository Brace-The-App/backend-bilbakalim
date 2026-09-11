<?php

namespace App\Services;

use App\Models\CoinHistory;
use App\Models\User;
use App\Models\UserAdView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdWatchStatsService
{
    public function summary(): array
    {
        $base = $this->adWatchQuery();
        $today = now()->startOfDay();

        $todayWatches = (clone $base)->where('created_at', '>=', $today)->count();
        $todayUsers = (clone $base)->where('created_at', '>=', $today)->distinct('user_id')->count('user_id');
        $todayCoins = (int) (clone $base)->where('created_at', '>=', $today)->sum('coin_amount');

        $allWatches = (clone $base)->count();
        $allUsers = (clone $base)->distinct('user_id')->count('user_id');
        $allCoins = (int) (clone $base)->sum('coin_amount');

        return [
            'generated_at' => now()->toIso8601String(),
            'quota' => [
                'max_per_window' => UserAdView::MAX_VIEWS,
                'window_hours' => UserAdView::WINDOW_HOURS,
                'active_windows' => $this->activeWindowCount(),
                'exhausted_now' => $this->exhaustedNowCount(),
            ],
            'today' => [
                'date' => $today->toDateString(),
                'total_watches' => $todayWatches,
                'unique_users' => $todayUsers,
                'coins_given' => $todayCoins,
            ],
            'all_time' => [
                'total_watches' => $allWatches,
                'unique_users' => $allUsers,
                'coins_given' => $allCoins,
                'first_watch_at' => (clone $base)->min('created_at'),
                'last_watch_at' => (clone $base)->max('created_at'),
            ],
            'today_by_user' => $this->usersForPeriod($today, null, 25),
            'daily_last_7_days' => $this->dailyBreakdown(7),
            'top_users_all_time' => $this->topUsersAllTime(15),
        ];
    }

    private function adWatchQuery()
    {
        return CoinHistory::query()
            ->where('status', 'completed')
            ->where('metadata->reward_type', 'ad_watch');
    }

    private function activeWindowCount(): int
    {
        $cutoff = now()->subHours(UserAdView::WINDOW_HOURS);

        return UserAdView::query()
            ->where('view_count', '>', 0)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('window_started_at')
                    ->orWhere('window_started_at', '>', $cutoff);
            })
            ->count();
    }

    private function exhaustedNowCount(): int
    {
        $cutoff = now()->subHours(UserAdView::WINDOW_HOURS);

        return UserAdView::query()
            ->where('view_count', '>=', UserAdView::MAX_VIEWS)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('window_started_at')
                    ->orWhere('window_started_at', '>', $cutoff);
            })
            ->count();
    }

    private function usersForPeriod(Carbon $from, ?Carbon $to, int $limit): array
    {
        $query = $this->adWatchQuery()
            ->where('created_at', '>=', $from);

        if ($to) {
            $query->where('created_at', '<', $to);
        }

        return $this->mapUserWatchRows(
            $query
                ->select('user_id', DB::raw('COUNT(*) as watch_count'))
                ->groupBy('user_id')
                ->orderByDesc('watch_count')
                ->limit($limit)
                ->get()
        );
    }

    private function topUsersAllTime(int $limit): array
    {
        return $this->mapUserWatchRows(
            $this->adWatchQuery()
                ->select('user_id', DB::raw('COUNT(*) as watch_count'))
                ->groupBy('user_id')
                ->orderByDesc('watch_count')
                ->limit($limit)
                ->get()
        );
    }

    private function mapUserWatchRows($rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id'))
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return $rows->map(function ($row) use ($users) {
            $user = $users->get($row->user_id);

            return [
                'user_id' => (int) $row->user_id,
                'name' => $user?->name ?: '—',
                'email' => $user?->email,
                'watch_count' => (int) $row->watch_count,
            ];
        })->values()->all();
    }

    private function dailyBreakdown(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = $this->adWatchQuery()
            ->where('created_at', '>=', $from)
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as watch_count'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users')
            )
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $row = $rows->get($day);

            $result[] = [
                'date' => $day,
                'label' => Carbon::parse($day)->locale('tr')->isoFormat('D MMM'),
                'watch_count' => (int) ($row->watch_count ?? 0),
                'unique_users' => (int) ($row->unique_users ?? 0),
            ];
        }

        return $result;
    }
}
