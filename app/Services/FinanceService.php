<?php

namespace App\Services;

use App\Models\FinanceExpenseCategory;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceMonthLock;
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
        $manualIncomeRows = FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_INCOME)
            ->whereIn('source', [
                FinanceLedgerEntry::SOURCE_AD_REVENUE,
                FinanceLedgerEntry::SOURCE_OTHER_INCOME,
            ])
            ->whereBetween('entry_date', [$fromDay->toDateString(), $toDay->toDateString()])
            ->get(['amount_try', 'source', 'meta']);

        $adRevenue = 0.0;
        $otherIncome = 0.0;
        $taxableManualIncome = 0.0;
        $nontaxableManualIncome = 0.0;
        foreach ($manualIncomeRows as $row) {
            $amt = (float) $row->amount_try;
            if ($row->source === FinanceLedgerEntry::SOURCE_AD_REVENUE) {
                $adRevenue += $amt;
            } else {
                $otherIncome += $amt;
            }
            // meta.counts_for_tax: true dahil, false hariç. Eski kayıtlar (yok) → dahil (eski davranış).
            $meta = is_array($row->meta) ? $row->meta : [];
            $counts = array_key_exists('counts_for_tax', $meta)
                ? (bool) $meta['counts_for_tax']
                : true;
            if ($counts) {
                $taxableManualIncome += $amt;
            } else {
                $nontaxableManualIncome += $amt;
            }
        }
        $adRevenue = round($adRevenue, 2);
        $otherIncome = round($otherIncome, 2);
        $manualIncome = round($adRevenue + $otherIncome, 2);
        $taxableManualIncome = round($taxableManualIncome, 2);
        $nontaxableManualIncome = round($nontaxableManualIncome, 2);

        $gift = self::giftBreakdown($fromDay, $toDay);

        $manualExpenseRows = FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_EXPENSE)
            ->whereIn('source', [FinanceLedgerEntry::SOURCE_MANUAL, FinanceLedgerEntry::SOURCE_KDV])
            ->whereBetween('entry_date', [$fromDay->toDateString(), $toDay->toDateString()])
            ->get(['amount_try', 'source', 'meta']);

        $manualOtherExpense = 0.0;
        $kdvInvoiceExpense = 0.0;
        $taxableManualExpense = 0.0;
        $nontaxableManualExpense = 0.0;
        foreach ($manualExpenseRows as $row) {
            $amt = (float) $row->amount_try;
            if ($row->source === FinanceLedgerEntry::SOURCE_KDV) {
                $kdvInvoiceExpense += $amt;
            } else {
                $manualOtherExpense += $amt;
            }
            $meta = is_array($row->meta) ? $row->meta : [];
            $counts = array_key_exists('counts_for_tax', $meta)
                ? (bool) $meta['counts_for_tax']
                : true;
            if ($counts) {
                $taxableManualExpense += $amt;
            } else {
                $nontaxableManualExpense += $amt;
            }
        }
        $manualOtherExpense = round($manualOtherExpense, 2);
        $kdvInvoiceExpense = round($kdvInvoiceExpense, 2);
        $manualExpense = round($manualOtherExpense + $kdvInvoiceExpense, 2);
        $taxableManualExpense = round($taxableManualExpense, 2);
        $nontaxableManualExpense = round($nontaxableManualExpense, 2);

        $manualByCategory = FinanceLedgerEntry::query()
            ->select('category_id', DB::raw('SUM(amount_try) as total'))
            ->with('category:id,name,slug')
            ->where('direction', FinanceLedgerEntry::DIRECTION_EXPENSE)
            ->where('source', FinanceLedgerEntry::SOURCE_MANUAL)
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

        $iapNetForIncome = round((float) ($iap['net'] ?? 0) - (float) ($iap['refund_net'] ?? 0), 2);
        $incomeTotal = round($iapNetForIncome + $manualIncome, 2);
        $expenseTotal = round($gift['total'] + $manualExpense, 2);

        $mid = $fromDay->copy()->addDays((int) max(0, $fromDay->diffInDays($toDay) / 2));
        $activeMid = self::rateFor($mid);
        $taxPct = (float) $activeMid->income_tax_pct;
        $kdvPct = (float) $activeMid->kdv_pct;
        $kdvToPl = (bool) ($activeMid->kdv_to_pl ?? false);
        $storePctMid = (float) $activeMid->store_fee_pct;

        $taxableExpenseTotal = round($gift['total'] + $taxableManualExpense, 2);
        $taxableProfit = round($iapNetForIncome + $taxableManualIncome - $taxableExpenseTotal, 2);

        $kdvRefOnIap = $kdvPct > 0 ? round((float) ($iap['gross'] ?? 0) * ($kdvPct / 100), 2) : 0.0;
        $kdvPlExpense = ($kdvToPl && $kdvPct > 0) ? $kdvRefOnIap : 0.0;

        $expenseTotalWithKdv = round($expenseTotal + $kdvPlExpense, 2);
        $preTax = round($incomeTotal - $expenseTotalWithKdv, 2);
        if ($kdvPlExpense > 0) {
            $taxableProfit = round($taxableProfit - $kdvPlExpense, 2);
        }
        $taxBase = max(0, $taxableProfit);
        $incomeTax = round($taxBase * ($taxPct / 100), 2);
        $finalProfit = round($preTax - $incomeTax, 2);

        $duelCommissionCoins = (int) DB::table('duels')
            ->where('status', 'finished')
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$fromDay, $toDay])
            ->sum('app_commission');
        $coinRate = (float) $activeMid->coin_to_try;

        $daily = self::dailySeries($fromDay, $toDay, $iap['by_day'], $gift['by_day'], $manualIncome, $manualExpense);

        return [
            'from' => $fromDay->toDateString(),
            'to' => $toDay->toDateString(),
            'iap' => $iap,
            'ad_revenue' => round($adRevenue, 2),
            'other_income' => round($otherIncome, 2),
            'manual_income' => $manualIncome,
            'gift' => $gift,
            'manual_expense' => $manualExpense,
            'manual_other_expense' => $manualOtherExpense,
            'kdv_invoice' => $kdvInvoiceExpense,
            'kdv_pl_expense' => $kdvPlExpense,
            'manual_by_category' => $manualByCategory,
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotalWithKdv,
            'pre_tax_profit' => $preTax,
            'taxable_profit' => $taxableProfit,
            'tax_base' => $taxBase,
            'nontaxable_manual_income' => $nontaxableManualIncome,
            'taxable_manual_income' => $taxableManualIncome,
            'nontaxable_manual_expense' => $nontaxableManualExpense,
            'taxable_manual_expense' => $taxableManualExpense,
            'income_tax_pct' => $taxPct,
            'income_tax' => $incomeTax,
            'final_profit' => $finalProfit,
            'rates' => [
                'store_fee_pct' => $storePctMid,
                'income_tax_pct' => $taxPct,
                'kdv_pct' => $kdvPct,
                'kdv_to_pl' => $kdvToPl,
                'kdv_ref_on_iap_gross' => $kdvRefOnIap,
                'kdv_note' => $kdvToPl
                    ? 'Dönem KDV% P&L giderine yazılıyor (IAP brüt × %).'
                    : 'Dönem KDV% sadece referans; fatura KDV’si manuel. Store ≠ KDV.',
            ],
            'tracks' => [
                'auto_income' => $iapNetForIncome,
                'manual_income' => $manualIncome,
                'ad_revenue' => round($adRevenue, 2),
                'other_income' => round($otherIncome, 2),
                'auto_expense' => round($gift['total'], 2),
                'manual_expense' => $manualOtherExpense,
                'kdv_expense' => $kdvInvoiceExpense,
                'kdv_pl_expense' => $kdvPlExpense,
            ],
            'duel_commission_info' => [
                'coins' => $duelCommissionCoins,
                'try_equiv' => round($duelCommissionCoins * $coinRate, 2),
                'note' => 'Nakit P&L dışı — sadece bilgi',
            ],
            'daily' => $daily,
            'active_rate' => self::rateFor($toDay),
            'locked_months' => self::lockedMonthsInRange($fromDay, $toDay),
        ];
    }

    /**
     * IAP satış + iade.
     * Satış: paid_at dönemde, status completed|refunded (iade edilmiş satış da brütte kalsın).
     * İade: refunded_at dönemde (status=refunded veya refunded_at dolu).
     *
     * @return array<string,mixed>
     */
    public static function iapBreakdown(Carbon $from, Carbon $to): array
    {
        $sales = Payment::query()
            ->whereIn('status', ['completed', 'refunded'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($q2) use ($from, $to) {
                        $q2->whereNull('paid_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->get(['id', 'amount', 'paid_at', 'created_at', 'metadata', 'status']);

        $gross = 0.0;
        $fee = 0.0;
        $net = 0.0;
        $byType = ['coin' => 0.0, 'premium' => 0.0, 'joker' => 0.0, 'diamond' => 0.0, 'other' => 0.0];
        $countByType = ['coin' => 0, 'premium' => 0, 'joker' => 0, 'diamond' => 0, 'other' => 0];
        $byDay = [];

        foreach ($sales as $p) {
            $when = $p->paid_at ?: $p->created_at;
            $rate = self::rateFor($when);
            $amount = abs((float) $p->amount);
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

        $refunds = Payment::query()
            ->where(function ($q) {
                $q->where('status', 'refunded')->orWhereNotNull('refunded_at');
            })
            ->whereNotNull('refunded_at')
            ->whereBetween('refunded_at', [$from, $to])
            ->get(['id', 'amount', 'refunded_at', 'paid_at', 'metadata']);

        $refundGross = 0.0;
        $refundFee = 0.0;
        $refundNet = 0.0;
        foreach ($refunds as $p) {
            $when = $p->refunded_at ?: $p->paid_at;
            $rate = self::rateFor($when);
            $amount = abs((float) $p->amount);
            $feePct = (float) $rate->store_fee_pct;
            $rowFee = round($amount * ($feePct / 100), 2);
            $rowNet = round($amount - $rowFee, 2);
            $refundGross += $amount;
            $refundFee += $rowFee;
            $refundNet += $rowNet;

            $day = Carbon::parse($when)->toDateString();
            $byDay[$day] = round(($byDay[$day] ?? 0) - $rowNet, 2);
        }

        return [
            'gross' => round($gross, 2),
            'fee' => round($fee, 2),
            'net' => round($net, 2),
            'count' => $sales->count(),
            'refund_gross' => round($refundGross, 2),
            'refund_fee' => round($refundFee, 2),
            'refund_net' => round($refundNet, 2),
            'refund_count' => $refunds->count(),
            'net_after_refunds' => round($net - $refundNet, 2),
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
        float $manualIncomeTotal,
        float $manualExpenseTotal
    ): array {
        // Manuel gelir/gider günlük dağılım
        $manualIncomeByDay = FinanceLedgerEntry::query()
            ->where('direction', FinanceLedgerEntry::DIRECTION_INCOME)
            ->whereIn('source', [
                FinanceLedgerEntry::SOURCE_AD_REVENUE,
                FinanceLedgerEntry::SOURCE_OTHER_INCOME,
            ])
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

    public static function isMonthLocked(Carbon|string $date): bool
    {
        $d = Carbon::parse($date);

        return FinanceMonthLock::query()
            ->where('year', (int) $d->year)
            ->where('month', (int) $d->month)
            ->exists();
    }

    public static function assertDateWritable(Carbon|string $date): void
    {
        $err = self::monthLockMessage($date);
        if ($err) {
            abort(422, $err);
        }
    }

    public static function monthLockMessage(Carbon|string $date): ?string
    {
        if (!self::isMonthLocked($date)) {
            return null;
        }
        $d = Carbon::parse($date);

        return sprintf('%02d.%04d dönemi kilitli — kayıt eklenemez/düzenlenemez.', (int) $d->month, (int) $d->year);
    }

    /**
     * @return list<string> "YYYY-MM"
     */
    public static function lockedMonthsInRange(Carbon $from, Carbon $to): array
    {
        $locks = FinanceMonthLock::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('year', [(int) $from->year, (int) $to->year]);
            })
            ->get();

        $out = [];
        foreach ($locks as $lock) {
            $key = sprintf('%04d-%02d', $lock->year, $lock->month);
            $start = Carbon::create($lock->year, $lock->month, 1)->startOfMonth();
            if ($start->betweenIncluded($from->copy()->startOfMonth(), $to->copy()->endOfMonth())) {
                $out[] = $key;
            }
        }

        return $out;
    }

    public static function lockMonth(int $year, int $month, ?int $userId = null, ?string $note = null): FinanceMonthLock
    {
        return FinanceMonthLock::query()->updateOrCreate(
            ['year' => $year, 'month' => $month],
            ['locked_by' => $userId, 'note' => $note]
        );
    }

    public static function unlockMonth(int $year, int $month): void
    {
        FinanceMonthLock::query()->where('year', $year)->where('month', $month)->delete();
    }
}
