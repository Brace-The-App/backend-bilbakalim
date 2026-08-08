<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\RewardRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Coin bazlı ekonomi özeti (nakit TL finansından ayrı).
 * Dönem rakamları seçili tarihe göre sayfa yenilenince yeniden hesaplanır.
 */
class FinanceCoinService
{
    public static function canAccess(?User $user): bool
    {
        return FinanceService::canAccess($user);
    }

    /**
     * @return array<string, mixed>
     */
    public static function summarize(Carbon $from, Carbon $to): array
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->endOfDay();

        $iapCoins = self::iapCoinGrants($fromDay, $toDay);
        $giftClaims = self::giftClaimCoins($fromDay, $toDay);
        $commission = self::duelCommissionStats($fromDay, $toDay);
        $botPeriod = self::botDuelPeriodStats($fromDay, $toDay);
        $pools = self::pools();
        $recentMatches = self::recentMatches($fromDay, $toDay, 3, 'humans');
        $botRecentMatches = self::recentMatches($fromDay, $toDay, 3, 'bots');
        $commissionMatches = self::recentCommissionMatches($fromDay, $toDay, 5);
        $dailyNet = self::dailyNetSeries($fromDay, $toDay);
        $pendingOpen = self::openPendingGiftClaimCount();
        $minCoins = FinanceService::giftClaimMinCoins();
        $heatFrom = $toDay->copy()->subDays(29)->startOfDay();
        $heatCalendar = self::dailyNetSeries($heatFrom, $toDay);

        // Coin gelir: paketlerden verilen jeton + uygulama kesintisi (sisteme giren)
        $incomeIap = (int) $iapCoins['total_coins'];
        $incomeCommission = (int) $commission['total'];
        $incomeTotal = $incomeIap + $incomeCommission;

        // Coin gider: hediye taleplerinde düşülen düello jetonu (kullanıcıdan çıkan / kilitlenen)
        $expenseGifts = (int) $giftClaims['total_coins'];
        $expenseTotal = $expenseGifts;
        $net = $incomeTotal - $expenseTotal;

        $balanceDenom = max(1, $incomeTotal + $expenseTotal);
        $humanDuel = (int) ($pools['human_duel'] ?? 0);
        $giftCapacity = $minCoins > 0 ? intdiv($humanDuel, $minCoins) : 0;
        $towardNext = $minCoins > 0 ? ($humanDuel % $minCoins) : 0;

        return [
            'from' => $fromDay->toDateString(),
            'to' => $toDay->toDateString(),
            'iap' => $iapCoins,
            'gift_claims' => $giftClaims,
            'gift_alert' => [
                'pending_count' => $pendingOpen,
                'min_coins' => $minCoins,
            ],
            'gift_pressure' => [
                'human_duel' => $humanDuel,
                'min_coins' => $minCoins,
                'capacity' => $giftCapacity,
                'toward_next' => $towardNext,
                'progress_pct' => $minCoins > 0 ? round(100 * $towardNext / $minCoins, 1) : 0,
            ],
            'daily_net' => $dailyNet,
            'heat_calendar' => $heatCalendar,
            'commission' => $commission,
            'commission_matches' => $commissionMatches,
            'bot_period' => $botPeriod,
            'pools' => $pools,
            'recent_matches' => $recentMatches,
            'bot_recent_matches' => $botRecentMatches,
            'income' => [
                'iap' => $incomeIap,
                'commission' => $incomeCommission,
                'total' => $incomeTotal,
            ],
            'expense' => [
                'gifts' => $expenseGifts,
                'total' => $expenseTotal,
            ],
            'net' => $net,
            'balance_pct' => [
                'income' => round(100 * $incomeTotal / $balanceDenom, 1),
                'expense' => round(100 * $expenseTotal / $balanceDenom, 1),
                'iap_of_income' => $incomeTotal > 0 ? round(100 * $incomeIap / $incomeTotal, 1) : 0,
                'commission_of_income' => $incomeTotal > 0 ? round(100 * $incomeCommission / $incomeTotal, 1) : 0,
            ],
        ];
    }

    /**
     * Açık (bekleyen) hediye talebi sayısı — dönem filtresinden bağımsız.
     */
    public static function openPendingGiftClaimCount(): int
    {
        return (int) RewardRequest::query()
            ->where('reward_type', 'duel')
            ->where('status', 'pending')
            ->count();
    }

    /**
     * Günlük net coin serisi (sparkline). Uzun aralıklarda son 45 gün.
     *
     * @return list<array{date:string,label:string,income:int,expense:int,net:int}>
     */
    public static function dailyNetSeries(Carbon $from, Carbon $to): array
    {
        $toDay = $to->copy()->endOfDay();
        $fromDay = $from->copy()->startOfDay();
        if ($fromDay->diffInDays($toDay) > 44) {
            $fromDay = $toDay->copy()->subDays(44)->startOfDay();
        }

        $days = [];
        for ($d = $fromDay->copy(); $d->lte($toDay); $d->addDay()) {
            $key = $d->toDateString();
            $days[$key] = [
                'date' => $key,
                'label' => $d->format('d.m'),
                'income' => 0,
                'expense' => 0,
                'net' => 0,
            ];
        }
        if ($days === []) {
            return [];
        }

        $rangeFrom = $fromDay->copy()->startOfDay();
        $rangeTo = $toDay->copy()->endOfDay();

        $payments = Payment::query()
            ->completed()
            ->where(function ($q) use ($rangeFrom, $rangeTo) {
                $q->whereBetween('paid_at', [$rangeFrom, $rangeTo])
                    ->orWhere(function ($q2) use ($rangeFrom, $rangeTo) {
                        $q2->whereNull('paid_at')->whereBetween('created_at', [$rangeFrom, $rangeTo]);
                    });
            })
            ->get(['metadata', 'paid_at', 'created_at']);

        foreach ($payments as $p) {
            $meta = is_array($p->metadata) ? $p->metadata : [];
            if (strtolower((string) ($meta['type'] ?? '')) !== 'coin') {
                continue;
            }
            $snap = is_array($meta['package_snapshot'] ?? null) ? $meta['package_snapshot'] : [];
            $granted = (int) ($snap['coin_amount'] ?? $snap['coins'] ?? 0) + (int) ($snap['bonus_coins'] ?? 0);
            if ($granted <= 0) {
                continue;
            }
            $when = $p->paid_at ?? $p->created_at;
            if (!$when) {
                continue;
            }
            $key = Carbon::parse($when)->toDateString();
            if (!isset($days[$key])) {
                continue;
            }
            $days[$key]['income'] += $granted;
        }

        $commRows = DB::table('duels')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$rangeFrom, $rangeTo])
            ->selectRaw('DATE(finished_at) as d, SUM(app_commission) as c')
            ->groupBy('d')
            ->get();

        foreach ($commRows as $row) {
            $key = (string) $row->d;
            if (!isset($days[$key])) {
                continue;
            }
            $days[$key]['income'] += (int) $row->c;
        }

        $minDefault = FinanceService::giftClaimMinCoins();
        $giftRows = RewardRequest::query()
            ->where('reward_type', 'duel')
            ->where('status', '!=', 'rejected')
            ->whereBetween('created_at', [$rangeFrom, $rangeTo])
            ->get(['created_at', 'coins_earned', 'metadata']);

        foreach ($giftRows as $rr) {
            $meta = is_array($rr->metadata) ? $rr->metadata : [];
            $amt = (int) ($meta['claimed_amount'] ?? $rr->coins_earned ?? $minDefault);
            if ($amt <= 0) {
                $amt = $minDefault;
            }
            $key = Carbon::parse($rr->created_at)->toDateString();
            if (!isset($days[$key])) {
                continue;
            }
            $days[$key]['expense'] += $amt;
        }

        foreach ($days as &$day) {
            $day['net'] = (int) $day['income'] - (int) $day['expense'];
        }
        unset($day);

        return array_values($days);
    }

    /**
     * Bakiyeler — bot / insan ayrımı (yenilemede güncel okunur).
     *
     * @return array<string,mixed>
     */
    public static function pools(): array
    {
        $humanWallet = (int) User::query()->where(fn ($q) => $q->where('is_bot', false)->orWhereNull('is_bot'))->sum('coins');
        $humanDuel = (int) User::query()->where(fn ($q) => $q->where('is_bot', false)->orWhereNull('is_bot'))->sum('duel_earned_coins');
        $botWallet = (int) User::query()->where('is_bot', true)->sum('coins');
        $botDuel = (int) User::query()->where('is_bot', true)->sum('duel_earned_coins');
        $botCount = (int) User::query()->where('is_bot', true)->count();
        $humanCount = (int) User::query()->where(fn ($q) => $q->where('is_bot', false)->orWhereNull('is_bot'))->count();

        return [
            'human_wallet' => $humanWallet,
            'human_duel' => $humanDuel,
            'bot_wallet' => $botWallet,
            'bot_duel' => $botDuel,
            'bot_count' => $botCount,
            'human_count' => $humanCount,
            'all_wallet' => $humanWallet + $botWallet,
            'all_duel' => $humanDuel + $botDuel,
        ];
    }

    /**
     * Seçili dönemdeki son bitmiş maçlar.
     *
     * @param  'all'|'humans'|'bots'  $mode
     * @return list<array<string,mixed>>
     */
    public static function recentMatches(Carbon $from, Carbon $to, int $limit = 3, string $mode = 'all'): array
    {
        $botIds = User::query()->where('is_bot', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $botSet = array_fill_keys($botIds, true);

        $q = DB::table('duels')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$from, $to])
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit($limit);

        if ($mode === 'bots') {
            if ($botIds === []) {
                return [];
            }
            $q->where(function ($w) use ($botIds) {
                $w->whereIn('challenger_id', $botIds)->orWhereIn('opponent_id', $botIds);
            });
        } elseif ($mode === 'humans') {
            if ($botIds !== []) {
                $q->whereNotIn('challenger_id', $botIds)
                    ->whereNotNull('opponent_id')
                    ->whereNotIn('opponent_id', $botIds);
            } else {
                $q->whereNotNull('opponent_id');
            }
        }

        $rows = $q->get([
            'id',
            'challenger_id',
            'opponent_id',
            'winner_id',
            'app_commission',
            'challenger_coins_before',
            'challenger_coins_after',
            'opponent_coins_before',
            'opponent_coins_after',
            'finished_at',
        ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $userIds = $rows->flatMap(fn ($r) => [(int) $r->challenger_id, (int) $r->opponent_id])
            ->unique()
            ->filter()
            ->values()
            ->all();
        $names = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'is_bot'])
            ->keyBy('id');

        $label = function (int $id) use ($names, $botSet): string {
            $u = $names->get($id);
            if (!$u) {
                return '#'.$id;
            }
            $n = trim((string) $u->name);
            if ($n === '') {
                $n = '#'.$id;
            }
            if (isset($botSet[$id]) || !empty($u->is_bot)) {
                $n .= ' (bot)';
            }

            return $n;
        };

        $out = [];
        foreach ($rows as $d) {
            $cId = (int) $d->challenger_id;
            $oId = (int) ($d->opponent_id ?? 0);
            $cDelta = (int) $d->challenger_coins_after - (int) $d->challenger_coins_before;
            $oDelta = (int) $d->opponent_coins_after - (int) $d->opponent_coins_before;
            $wid = $d->winner_id !== null ? (int) $d->winner_id : null;

            $out[] = [
                'id' => (int) $d->id,
                'finished_at' => (string) $d->finished_at,
                'challenger' => $label($cId),
                'opponent' => $oId > 0 ? $label($oId) : '—',
                'challenger_delta' => $cDelta,
                'opponent_delta' => $oDelta,
                'commission' => (int) $d->app_commission,
                'winner' => $wid ? $label($wid) : null,
                'involves_bot' => isset($botSet[$cId]) || ($oId > 0 && isset($botSet[$oId])),
            ];
        }

        return $out;
    }

    /**
     * Kesinti alınan son maçlar (uygulama geliri; bot cüzdan hareketi değil).
     *
     * @return list<array<string,mixed>>
     */
    public static function recentCommissionMatches(Carbon $from, Carbon $to, int $limit = 5): array
    {
        $botIds = User::query()->where('is_bot', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $botSet = array_fill_keys($botIds, true);

        $rows = DB::table('duels')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$from, $to])
            ->where('app_commission', '>', 0)
            ->orderByDesc('finished_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get([
                'id',
                'challenger_id',
                'opponent_id',
                'winner_id',
                'app_commission',
                'finished_at',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $userIds = $rows->flatMap(fn ($r) => [(int) $r->challenger_id, (int) $r->opponent_id, (int) ($r->winner_id ?? 0)])
            ->unique()
            ->filter()
            ->values()
            ->all();
        $names = User::query()->whereIn('id', $userIds)->get(['id', 'name', 'is_bot'])->keyBy('id');

        $label = function (int $id) use ($names, $botSet): string {
            if ($id <= 0) {
                return '—';
            }
            $u = $names->get($id);
            if (!$u) {
                return '#'.$id;
            }
            $n = trim((string) $u->name) ?: '#'.$id;
            if (isset($botSet[$id]) || !empty($u->is_bot)) {
                $n .= ' (bot)';
            }

            return $n;
        };

        $out = [];
        foreach ($rows as $d) {
            $wid = $d->winner_id !== null ? (int) $d->winner_id : 0;
            $out[] = [
                'id' => (int) $d->id,
                'finished_at' => (string) $d->finished_at,
                'challenger' => $label((int) $d->challenger_id),
                'opponent' => $label((int) ($d->opponent_id ?? 0)),
                'winner' => $wid ? $label($wid) : null,
                'commission' => (int) $d->app_commission,
                'from_bot_winner' => $wid > 0 && isset($botSet[$wid]),
            ];
        }

        return $out;
    }

    /**
     * Dönem düello komisyonları (app_commission).
     *
     * @return array{total:int,duels:int,with_fee:int,avg:float,from_bot_winners:int,from_human_winners:int}
     */
    public static function duelCommissionStats(Carbon $from, Carbon $to): array
    {
        $botIds = User::query()->where('is_bot', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $base = DB::table('duels')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$from, $to]);

        $total = (int) (clone $base)->sum('app_commission');
        $duels = (int) (clone $base)->count();
        $withFee = (int) (clone $base)->where('app_commission', '>', 0)->count();

        $fromBot = 0;
        $fromHuman = 0;
        if ($botIds !== []) {
            $fromBot = (int) (clone $base)->where('app_commission', '>', 0)->whereIn('winner_id', $botIds)->sum('app_commission');
            $fromHuman = (int) (clone $base)->where('app_commission', '>', 0)->whereNotIn('winner_id', $botIds)->sum('app_commission');
        } else {
            $fromHuman = $total;
        }

        return [
            'total' => $total,
            'duels' => $duels,
            'with_fee' => $withFee,
            'avg' => $withFee > 0 ? round($total / $withFee, 2) : 0,
            'from_bot_winners' => $fromBot,
            'from_human_winners' => $fromHuman,
            'rule' => '%10',
        ];
    }

    /**
     * Botların seçili dönemde kazandığı / kaybettiği cüzdan jetonu (duels before/after).
     *
     * @return array<string,mixed>
     */
    public static function botDuelPeriodStats(Carbon $from, Carbon $to): array
    {
        $botIds = User::query()->where('is_bot', true)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($botIds === []) {
            return [
                'won' => 0,
                'lost' => 0,
                'net' => 0,
                'wins' => 0,
                'losses' => 0,
                'draws_or_cancel' => 0,
                'matches' => 0,
            ];
        }

        $duels = DB::table('duels')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$from, $to])
            ->where(function ($q) use ($botIds) {
                $q->whereIn('challenger_id', $botIds)->orWhereIn('opponent_id', $botIds);
            })
            ->get([
                'id',
                'challenger_id',
                'opponent_id',
                'winner_id',
                'challenger_coins_before',
                'challenger_coins_after',
                'opponent_coins_before',
                'opponent_coins_after',
                'app_commission',
            ]);

        $won = 0;
        $lost = 0;
        $wins = 0;
        $losses = 0;
        $noWinner = 0;
        $botIdSet = array_fill_keys($botIds, true);

        foreach ($duels as $d) {
            $delta = 0;
            $botIsChallenger = isset($botIdSet[(int) $d->challenger_id]);
            $botIsOpponent = isset($botIdSet[(int) $d->opponent_id]);

            if ($botIsChallenger) {
                $delta += (int) $d->challenger_coins_after - (int) $d->challenger_coins_before;
            }
            if ($botIsOpponent) {
                $delta += (int) $d->opponent_coins_after - (int) $d->opponent_coins_before;
            }

            if ($delta > 0) {
                $won += $delta;
            } elseif ($delta < 0) {
                $lost += abs($delta);
            }

            $wid = $d->winner_id !== null ? (int) $d->winner_id : null;
            if ($wid === null) {
                $noWinner++;
            } elseif (isset($botIdSet[$wid])) {
                $wins++;
            } elseif ($botIsChallenger || $botIsOpponent) {
                $losses++;
            }
        }

        return [
            'won' => $won,
            'lost' => $lost,
            'net' => $won - $lost,
            'wins' => $wins,
            'losses' => $losses,
            'draws_or_cancel' => $noWinner,
            'matches' => $duels->count(),
        ];
    }

    /**
     * @return array{total_coins:int,count:int,by_package:list<array{name:string,count:int,coins:int}>}
     */
    public static function iapCoinGrants(Carbon $from, Carbon $to): array
    {
        $payments = Payment::query()
            ->completed()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('paid_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->get(['id', 'metadata', 'amount', 'paid_at', 'created_at']);

        $total = 0;
        $count = 0;
        $byPackage = [];

        foreach ($payments as $p) {
            $meta = is_array($p->metadata) ? $p->metadata : [];
            $type = strtolower((string) ($meta['type'] ?? ''));
            if ($type !== 'coin') {
                continue;
            }
            $snap = is_array($meta['package_snapshot'] ?? null) ? $meta['package_snapshot'] : [];
            $coins = (int) ($snap['coin_amount'] ?? $snap['coins'] ?? 0);
            $bonus = (int) ($snap['bonus_coins'] ?? 0);
            $granted = $coins + $bonus;
            if ($granted <= 0) {
                continue;
            }
            $name = (string) ($snap['name'] ?? 'Jeton paketi');
            $total += $granted;
            $count++;
            if (!isset($byPackage[$name])) {
                $byPackage[$name] = ['name' => $name, 'count' => 0, 'coins' => 0];
            }
            $byPackage[$name]['count']++;
            $byPackage[$name]['coins'] += $granted;
        }

        usort($byPackage, fn ($a, $b) => $b['coins'] <=> $a['coins']);

        return [
            'total_coins' => $total,
            'count' => $count,
            'by_package' => array_values($byPackage),
        ];
    }

    /**
     * @return array{total_coins:int,count:int,pending:int,approved:int,rejected:int}
     */
    public static function giftClaimCoins(Carbon $from, Carbon $to): array
    {
        $rows = RewardRequest::query()
            ->where('reward_type', 'duel')
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'status', 'coins_earned', 'metadata']);

        $minDefault = FinanceService::giftClaimMinCoins();
        $total = 0;
        $pending = 0;
        $approved = 0;
        $rejected = 0;

        foreach ($rows as $rr) {
            $meta = is_array($rr->metadata) ? $rr->metadata : [];
            $amt = (int) ($meta['claimed_amount'] ?? $rr->coins_earned ?? $minDefault);
            if ($amt <= 0) {
                $amt = $minDefault;
            }
            if ($rr->status === 'rejected') {
                $rejected += $amt;
                continue;
            }
            $total += $amt;
            if ($rr->status === 'pending') {
                $pending += $amt;
            } else {
                $approved += $amt;
            }
        }

        return [
            'total_coins' => $total,
            'count' => $rows->where('status', '!=', 'rejected')->count(),
            'pending_count' => $rows->where('status', 'pending')->count(),
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
        ];
    }
}
