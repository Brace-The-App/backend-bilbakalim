<?php

namespace App\Services;

use App\Models\FinanceExpenseCategory;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceRatePeriod;
use App\Models\Payment;
use App\Models\RewardRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public const ALLOWED_VIEWER_USER_ID = 15;

    public static function canAccess(?User $user): bool
    {
        return $user !== null && (int) $user->id === self::ALLOWED_VIEWER_USER_ID;
    }

    /**
     * Dönem içi IAP satış listesi (paket kırılımı için).
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function iapSalesPaginated(Carbon $from, Carbon $to, int $perPage = 5, string $pageName = 'sales_page')
    {
        $paginator = Payment::query()
            ->completed()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('paid_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->orderByDesc(DB::raw('COALESCE(paid_at, created_at)'))
            ->paginate($perPage, ['id', 'user_id', 'amount', 'paid_at', 'created_at', 'metadata', 'transaction_id'], $pageName);

        $paginator->setCollection(
            $paginator->getCollection()->map(function ($p) {
                $when = $p->paid_at ?: $p->created_at;
                $rate = self::rateFor($when);
                $amount = (float) $p->amount;
                $feePct = (float) $rate->store_fee_pct;
                $fee = round($amount * ($feePct / 100), 2);
                $net = round($amount - $fee, 2);
                $meta = is_array($p->metadata) ? $p->metadata : [];
                $snap = is_array($meta['package_snapshot'] ?? null) ? $meta['package_snapshot'] : [];
                $type = strtolower((string) ($meta['type'] ?? 'other'));

                $pkgName = (string) (
                    $snap['name']
                    ?? $meta['package_name']
                    ?? $meta['product_id']
                    ?? $type
                );

                // Jeton / premium / joker içeriği (snapshot’tan)
                $coins = (int) ($snap['coin_amount'] ?? $snap['coins'] ?? $snap['gift_coins'] ?? 0);
                $bonus = (int) ($snap['bonus_coins'] ?? 0);
                $totalCoins = $coins + $bonus;
                $snapPrice = isset($snap['price']) ? (float) $snap['price'] : null;
                $detailParts = [];
                if ($type === 'coin' && $totalCoins > 0) {
                    $detailParts[] = number_format($totalCoins, 0, ',', '.') . ' jeton';
                    if ($bonus > 0) {
                        $detailParts[] = 'bonus ' . number_format($bonus, 0, ',', '.');
                    }
                } elseif ($type === 'premium') {
                    $days = (int) ($snap['duration_days'] ?? $snap['days'] ?? 0);
                    if ($days > 0) {
                        $detailParts[] = $days . ' gün';
                    }
                    if ($coins > 0) {
                        $detailParts[] = number_format($coins, 0, ',', '.') . ' hediye jeton';
                    }
                } elseif ($type === 'joker') {
                    $jokers = (int) ($snap['joker_amount'] ?? $snap['joker_count'] ?? $snap['quantity'] ?? 0);
                    if ($jokers > 0) {
                        $detailParts[] = number_format($jokers, 0, ',', '.') . ' joker';
                    }
                    if ($coins > 0) {
                        $detailParts[] = number_format($coins, 0, ',', '.') . ' jeton';
                    }
                }
                if ($snapPrice !== null && $snapPrice > 0) {
                    $detailParts[] = number_format($snapPrice, 2, ',', '.') . ' ₺ paket';
                }

                return [
                    'id' => $p->id,
                    'date' => Carbon::parse($when)->toDateTimeString(),
                    'user_id' => $p->user_id,
                    'type' => $type,
                    'package' => $pkgName,
                    'package_detail' => $detailParts !== [] ? implode(' · ', $detailParts) : null,
                    'coins' => $totalCoins > 0 ? $totalCoins : null,
                    'gross' => $amount,
                    'fee' => $fee,
                    'net' => $net,
                    'fee_pct' => $feePct,
                ];
            })
        );

        return $paginator;
    }

    /** @deprecated use iapSalesPaginated */
    public static function iapSalesList(Carbon $from, Carbon $to, int $limit = 50): array
    {
        return self::iapSalesPaginated($from, $to, $limit)->items();
    }

    /** Varsayılan dönem yoksa oluştur (geçmiş açık uç). */
    public static function ensureDefaultPeriod(?int $userId = null): FinanceRatePeriod
    {
        $existing = FinanceRatePeriod::query()->orderBy('effective_from')->first();
        if ($existing) {
            return $existing;
        }

        return FinanceRatePeriod::query()->create([
            'effective_from' => '2020-01-01',
            'effective_to' => null,
            'store_fee_pct' => 40,
            'income_tax_pct' => 25,
            'kdv_pct' => 0,
            'gift_payout_try' => 100,
            'coin_to_try' => 1,
            'ad_click_floor_try' => 0.20,
            'note' => 'Başlangıç dönemi',
            'created_by' => $userId,
        ]);
    }

    public static function rateFor(Carbon|string $date): FinanceRatePeriod
    {
        self::ensureDefaultPeriod();
        $d = Carbon::parse($date)->toDateString();

        $period = FinanceRatePeriod::query()
            ->where('effective_from', '<=', $d)
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($period) {
            return $period;
        }

        // Hiçbiri kapsamazsa en yakın geçmiş
        return FinanceRatePeriod::query()
            ->orderByDesc('effective_from')
            ->first() ?? self::ensureDefaultPeriod();
    }

    /**
     * Ödül talep eşiği (düello jetonu).
     * Aktif oran dönemindeki "Ödül ₺" değerinden gelir (coin→₺ = 1 iken 1:1).
     * API / panel buradan okur; config sadece fallback.
     */
    public static function giftClaimMinCoins(Carbon|string|null $date = null): int
    {
        $rate = self::rateFor($date ?? now());
        $payoutTry = (float) $rate->gift_payout_try;
        $coinToTry = (float) $rate->coin_to_try;
        if ($coinToTry > 0.0000001) {
            $coins = (int) round($payoutTry / $coinToTry);
        } else {
            $coins = (int) round($payoutTry);
        }

        if ($coins < 1) {
            return max(1, (int) config('app.gift_claim_min_coins', 250));
        }

        return $coins;
    }

    /**
     * Onayda varsayılan ödeme tutarı (₺) — aynı dönem alanı.
     */
    public static function giftPayoutTry(Carbon|string|null $date = null): float
    {
        return round((float) self::rateFor($date ?? now())->gift_payout_try, 2);
    }

    /**
     * Dönem özeti (nakit bazlı P&L).
     * Düello komisyon gelire GİRMEZ — sadece info.
     *
     * @return array<string, mixed>
     */
    public static function summarize(Carbon $from, Carbon $to): array
    {
        self::ensureDefaultPeriod();
        $fromDay = $from->copy()->startOfDay();
        $toDay = $to->copy()->endOfDay();

        $iap = self::iapBreakdown($fromDay, $toDay);
        $adRevenue = (float) FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_INCOME)
            ->where('source', FinanceLedgerEntry::SOURCE_AD_REVENUE)
            ->whereBetween('entry_date', [$fromDay->toDateString(), $toDay->toDateString()])
            ->sum('amount_try');

        $gift = self::giftBreakdown($fromDay, $toDay);

        $manualExpense = (float) FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_EXPENSE)
            ->whereIn('source', [FinanceLedgerEntry::SOURCE_MANUAL, FinanceLedgerEntry::SOURCE_KDV])
            ->whereBetween('entry_date', [$fromDay->toDateString(), $toDay->toDateString()])
            ->sum('amount_try');

        $manualByCategory = FinanceLedgerEntry::query()
            ->select('category_id', DB::raw('SUM(amount_try) as total'))
            ->with('category:id,name,slug')
            ->where('direction', FinanceLedgerEntry::DIRECTION_EXPENSE)
            ->whereIn('source', [FinanceLedgerEntry::SOURCE_MANUAL, FinanceLedgerEntry::SOURCE_KDV])
            ->whereBetween('entry_date', [$fromDay->toDateString(), $toDay->toDateString()])
            ->groupBy('category_id')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category?->name ?? 'Diğer',
                'slug' => $r->category?->slug,
                'total' => round((float) $r->total, 2),
            ])
            ->values()
            ->all();

        $incomeTotal = round($iap['net'] + $adRevenue, 2);
        $expenseTotal = round($gift['total'] + $manualExpense, 2);
        $preTax = round($incomeTotal - $expenseTotal, 2);

        // Vergi oranı: dönem ortası tarihe göre (basit / tutarlı)
        $mid = $fromDay->copy()->addDays((int) max(0, $fromDay->diffInDays($toDay) / 2));
        $taxPct = (float) self::rateFor($mid)->income_tax_pct;
        $taxBase = max(0, $preTax); // zarar varsa vergi 0
        $incomeTax = round($taxBase * ($taxPct / 100), 2);
        $finalProfit = round($preTax - $incomeTax, 2);

        // Info: düello komisyon (nakit değil)
        $duelCommissionCoins = (int) DB::table('duels')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$fromDay, $toDay])
            ->sum('app_commission');
        $coinRate = (float) self::rateFor($mid)->coin_to_try;

        $daily = self::dailySeries($fromDay, $toDay, $iap['by_day'], $gift['by_day'], $adRevenue, $manualExpense);

        return [
            'from' => $fromDay->toDateString(),
            'to' => $toDay->toDateString(),
            'iap' => $iap,
            'ad_revenue' => round($adRevenue, 2),
            'gift' => $gift,
            'manual_expense' => round($manualExpense, 2),
            'manual_by_category' => $manualByCategory,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'pre_tax_profit' => $preTax,
            'income_tax_pct' => $taxPct,
            'income_tax' => $incomeTax,
            'final_profit' => $finalProfit,
            'duel_commission_info' => [
                'coins' => $duelCommissionCoins,
                'try_equiv' => round($duelCommissionCoins * $coinRate, 2),
                'note' => 'Nakit P&L dışı — sadece bilgi',
            ],
            'daily' => $daily,
            'active_rate' => self::rateFor($toDay),
        ];
    }

    /**
     * @return array{gross:float,fee:float,net:float,count:int,by_type:array,by_day:array<string,float>}
     */
    public static function iapBreakdown(Carbon $from, Carbon $to): array
    {
        $payments = Payment::query()
            ->completed()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('paid_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->get(['id', 'amount', 'paid_at', 'created_at', 'metadata']);

        $gross = 0.0;
        $fee = 0.0;
        $net = 0.0;
        $byType = ['coin' => 0.0, 'premium' => 0.0, 'joker' => 0.0, 'diamond' => 0.0, 'other' => 0.0];
        $countByType = ['coin' => 0, 'premium' => 0, 'joker' => 0, 'diamond' => 0, 'other' => 0];
        $byDay = [];

        foreach ($payments as $p) {
            $when = $p->paid_at ?: $p->created_at;
            $rate = self::rateFor($when);
            $amount = (float) $p->amount;
            $feePct = (float) $rate->store_fee_pct;
            $rowFee = round($amount * ($feePct / 100), 2);
            $rowNet = round($amount - $rowFee, 2);

            $gross += $amount;
            $fee += $rowFee;
            $net += $rowNet;

            $type = strtolower((string) data_get($p->metadata, 'type', 'other'));
            if (!isset($byType[$type])) {
                $type = 'other';
            }
            $byType[$type] = round($byType[$type] + $rowNet, 2);
            $countByType[$type]++;

            $day = Carbon::parse($when)->toDateString();
            $byDay[$day] = round(($byDay[$day] ?? 0) + $rowNet, 2);
        }

        return [
            'gross' => round($gross, 2),
            'fee' => round($fee, 2),
            'net' => round($net, 2),
            'count' => $payments->count(),
            'by_type' => $byType,
            'count_by_type' => $countByType,
            'by_day' => $byDay,
        ];
    }

    /**
     * @return array{total:float,count:int,by_method:array,by_day:array<string,float>}
     */
    public static function giftBreakdown(Carbon $from, Carbon $to): array
    {
        $fromS = $from->toDateString();
        $toS = $to->toDateString();

        $ledger = FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_EXPENSE)
            ->where('source', FinanceLedgerEntry::SOURCE_GIFT)
            ->whereBetween('entry_date', [$fromS, $toS])
            ->get();

        $ledgerRefIds = $ledger
            ->where('reference_type', RewardRequest::class)
            ->pluck('reference_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        $total = (float) $ledger->sum('amount_try');
        $byMethod = [];
        $byDay = [];
        foreach ($ledger as $e) {
            $m = $e->payout_method ?: 'other';
            $byMethod[$m] = round(($byMethod[$m] ?? 0) + (float) $e->amount_try, 2);
            $d = $e->entry_date->toDateString();
            $byDay[$d] = round(($byDay[$d] ?? 0) + (float) $e->amount_try, 2);
        }

        // Eski onaylar (ledger yok): dönem oranı × adet
        $legacy = RewardRequest::query()
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$from, $to])
            ->when(count($ledgerRefIds) > 0, fn ($q) => $q->whereNotIn('id', $ledgerRefIds))
            ->get(['id', 'approved_at', 'metadata', 'coins_earned']);

        foreach ($legacy as $rr) {
            $meta = is_array($rr->metadata) ? $rr->metadata : [];
            if (isset($meta['finance_ledger_id'])) {
                continue;
            }
            $rate = self::rateFor($rr->approved_at);
            $amount = (float) ($meta['finance_payout_try'] ?? $rate->gift_payout_try);
            $method = (string) ($meta['payout_method'] ?? 'other');
            $total += $amount;
            $byMethod[$method] = round(($byMethod[$method] ?? 0) + $amount, 2);
            $d = Carbon::parse($rr->approved_at)->toDateString();
            $byDay[$d] = round(($byDay[$d] ?? 0) + $amount, 2);
        }

        return [
            'total' => round($total, 2),
            'count' => $ledger->count() + $legacy->count(),
            'by_method' => $byMethod,
            'by_day' => $byDay,
        ];
    }

    /**
     * @param  array<string,float>  $iapByDay
     * @param  array<string,float>  $giftByDay
     * @return list<array{date:string,income:float,expense:float,net:float}>
     */
    private static function dailySeries(
        Carbon $from,
        Carbon $to,
        array $iapByDay,
        array $giftByDay,
        float $adRevenueTotal,
        float $manualExpenseTotal
    ): array {
        // Manuel gelir/gider günlük dağılım
        $manualIncomeByDay = FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_INCOME)
            ->where('source', FinanceLedgerEntry::SOURCE_AD_REVENUE)
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->select('entry_date', DB::raw('SUM(amount_try) as total'))
            ->groupBy('entry_date')
            ->pluck('total', 'entry_date')
            ->map(fn ($v) => (float) $v)
            ->all();

        $manualExpByDay = FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_EXPENSE)
            ->whereIn('source', [FinanceLedgerEntry::SOURCE_MANUAL, FinanceLedgerEntry::SOURCE_KDV])
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->select('entry_date', DB::raw('SUM(amount_try) as total'))
            ->groupBy('entry_date')
            ->pluck('total', 'entry_date')
            ->map(fn ($v) => (float) $v)
            ->all();

        $out = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $income = ($iapByDay[$d] ?? 0) + ($manualIncomeByDay[$d] ?? 0);
            $expense = ($giftByDay[$d] ?? 0) + ($manualExpByDay[$d] ?? 0);
            $out[] = [
                'date' => $d,
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($income - $expense, 2),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /** Ödül onayında ledger kaydı */
    public static function recordGiftPayout(
        RewardRequest $rewardRequest,
        string $payoutMethod,
        ?float $amountOverride = null,
        ?int $actorId = null
    ): FinanceLedgerEntry {
        $when = $rewardRequest->approved_at ?? now();
        $rate = self::rateFor($when);
        $amount = $amountOverride !== null ? $amountOverride : (float) $rate->gift_payout_try;
        $cat = FinanceExpenseCategory::query()->where('slug', 'gift')->first();

        $entry = FinanceLedgerEntry::query()->create([
            'direction' => FinanceLedgerEntry::DIRECTION_EXPENSE,
            'source' => FinanceLedgerEntry::SOURCE_GIFT,
            'category_id' => $cat?->id,
            'entry_date' => Carbon::parse($when)->toDateString(),
            'amount_try' => round($amount, 2),
            'currency' => 'TRY',
            'label' => 'Ödül talebi #' . $rewardRequest->id,
            'note' => null,
            'payout_method' => $payoutMethod,
            'reference_type' => RewardRequest::class,
            'reference_id' => $rewardRequest->id,
            'meta' => [
                'user_id' => $rewardRequest->user_id,
                'gift_claim_coins' => $rewardRequest->coins_earned,
            ],
            'created_by' => $actorId ?? Auth::id(),
        ]);

        return $entry;
    }

    public static function closeOpenPeriodsBefore(Carbon $fromDate, ?int $exceptId = null): void
    {
        $prevDay = $fromDate->copy()->subDay()->toDateString();
        FinanceRatePeriod::query()
            ->whereNull('effective_to')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('effective_from', '<', $fromDate->toDateString())
            ->update(['effective_to' => $prevDay]);
    }
}
