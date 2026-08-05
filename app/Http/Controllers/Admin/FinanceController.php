<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceExpenseCategory;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceRatePeriod;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!FinanceService::canAccess($request->user())) {
                abort(403, 'Bu sayfaya erişim yetkiniz yok.');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        FinanceService::ensureDefaultPeriod(Auth::id());

        $range = (string) $request->query('range', '');
        $todayStart = Carbon::parse(tr_now()->toDateString())->startOfDay();
        $firstPaid = \App\Models\Payment::query()
            ->completed()
            ->selectRaw('MIN(COALESCE(paid_at, created_at)) as first_at')
            ->value('first_at');
        $allFrom = $firstPaid
            ? Carbon::parse($firstPaid)->startOfDay()
            : Carbon::parse('2020-01-01')->startOfDay();

        // Kararlaştırılan tarih = aktif oran döneminin başlangıcı
        $agreedFrom = Carbon::parse(FinanceService::rateFor($todayStart)->effective_from)->startOfDay();

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
            // Varsayılan / Sadece bugün
            $range = 'today';
            $from = $todayStart->copy();
            $to = $todayStart->copy()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $summary = FinanceService::summarize($from, $to);
        // Manuel gider formu: ödül (otomatik) ve KDV (ayrı sekme) listede olmasın
        $categories = FinanceExpenseCategory::query()
            ->where('is_active', true)
            ->whereNotIn('slug', ['gift', 'kdv_manual'])
            ->orderBy('sort_order')
            ->get();
        $recentEntries = FinanceLedgerEntry::query()
            ->with('category:id,name')
            ->whereIn('source', [
                FinanceLedgerEntry::SOURCE_MANUAL,
                FinanceLedgerEntry::SOURCE_AD_REVENUE,
                FinanceLedgerEntry::SOURCE_KDV,
            ])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get();
        $periods = FinanceRatePeriod::query()->orderByDesc('effective_from')->limit(12)->get();
        $iapSales = FinanceService::iapSalesPaginated($from, $to, 5, 'sales_page')
            ->appends($request->except('sales_page'))
            ->fragment('fin-sales');

        return view('admin.finance.index', compact(
            'summary',
            'from',
            'to',
            'range',
            'categories',
            'recentEntries',
            'periods',
            'iapSales',
            'agreedFrom'
        ));
    }

    public function storeEntry(Request $request)
    {
        foreach (['amount_try'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => self::normalizeNumber($request->input($field))]);
            }
        }

        $validated = $request->validate([
            'direction' => 'required|in:income,expense',
            'source' => 'required|in:ad_revenue,manual,kdv',
            'category_id' => 'nullable|integer|exists:finance_expense_categories,id',
            'entry_date' => 'required|date',
            'amount_try' => 'required|numeric|min:0.01|max:99999999',
            'label' => 'nullable|string|max:200',
            'note' => 'nullable|string|max:2000',
            'payout_method' => 'nullable|in:multinet,papara,havale,parsela,other',
        ], [
            'amount_try.required' => 'Tutar gerekli.',
            'amount_try.numeric' => 'Tutar geçerli bir sayı olmalı.',
            'entry_date.required' => 'Tarih gerekli.',
        ]);

        if ($validated['direction'] === 'income' && $validated['source'] !== 'ad_revenue') {
            $validated['source'] = 'ad_revenue';
        }
        if ($validated['source'] === 'kdv') {
            $validated['direction'] = 'expense';
            $kdvCat = FinanceExpenseCategory::query()->where('slug', 'kdv_manual')->first();
            $validated['category_id'] = $kdvCat?->id ?? $validated['category_id'];
        }
        if ($validated['source'] === 'ad_revenue') {
            $validated['direction'] = 'income';
            $validated['category_id'] = null;
        }
        if ($validated['source'] === 'manual' && empty($validated['category_id'])) {
            $other = FinanceExpenseCategory::query()->where('slug', 'other')->first();
            $validated['category_id'] = $other?->id;
        }
        if ($validated['source'] === 'manual' && !empty($validated['category_id'])) {
            $cat = FinanceExpenseCategory::query()->find($validated['category_id']);
            if ($cat && in_array($cat->slug, ['gift', 'kdv_manual'], true)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Ödül otomatik (talep onayından) gelir; KDV için üstteki KDV sekmesini kullanın.');
            }
        }

        FinanceLedgerEntry::query()->create([
            'direction' => $validated['direction'],
            'source' => $validated['source'],
            'category_id' => $validated['category_id'] ?? null,
            'entry_date' => $validated['entry_date'],
            'amount_try' => round((float) $validated['amount_try'], 2),
            'currency' => 'TRY',
            'label' => $validated['label'] ?? null,
            'note' => $validated['note'] ? trim(strip_tags($validated['note'])) : null,
            'payout_method' => $validated['payout_method'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.finance.index', array_filter([
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'range' => $request->input('range'),
            ]))
            ->with('success', 'Kayıt eklendi.');
    }

    public function destroyEntry(int $id)
    {
        $entry = FinanceLedgerEntry::query()->findOrFail($id);
        // Otomatik hediye kayıtları da silinebilir (düzeltme için)
        $entry->delete();

        return redirect()
            ->route('admin.finance.index')
            ->with('success', 'Kayıt silindi.');
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
        ]);
        $name = trim(strip_tags($validated['name']));
        $slug = Str::slug($name, '-');
        if ($slug === '') {
            $slug = 'kat-' . Str::lower(Str::random(6));
        }
        $base = $slug;
        $i = 1;
        while (FinanceExpenseCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        FinanceExpenseCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 50,
        ]);

        return redirect()
            ->route('admin.finance.settings')
            ->with('success', 'Kategori eklendi.');
    }

    public function settings()
    {
        FinanceService::ensureDefaultPeriod(Auth::id());
        $periods = FinanceRatePeriod::query()->orderByDesc('effective_from')->get();
        $categories = FinanceExpenseCategory::query()->orderBy('sort_order')->get();
        $currentClaimCoins = FinanceService::giftClaimMinCoins();
        $currentPayoutTry = FinanceService::giftPayoutTry();

        return view('admin.finance.settings', compact(
            'periods',
            'categories',
            'currentClaimCoins',
            'currentPayoutTry'
        ));
    }

    public function storePeriod(Request $request)
    {
        foreach (['store_fee_pct', 'income_tax_pct', 'kdv_pct', 'gift_payout_try', 'coin_to_try', 'ad_click_floor_try'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => self::normalizeNumber($request->input($field))]);
            }
        }

        $validated = $request->validate([
            'effective_from' => 'required|date',
            'store_fee_pct' => 'required|numeric|min:0|max:100',
            'income_tax_pct' => 'required|numeric|min:0|max:100',
            'kdv_pct' => 'nullable|numeric|min:0|max:100',
            'gift_payout_try' => 'required|numeric|min:0|max:999999',
            'coin_to_try' => 'required|numeric|min:0|max:100',
            'ad_click_floor_try' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string|max:255',
        ], [
            'store_fee_pct.numeric' => 'Store % sayı olmalı.',
            'income_tax_pct.numeric' => 'GV % sayı olmalı.',
            'gift_payout_try.numeric' => 'Ödül tutarı sayı olmalı.',
            'coin_to_try.numeric' => 'Coin→₺ sayı olmalı.',
        ]);

        $from = Carbon::parse($validated['effective_from'])->startOfDay();
        FinanceService::closeOpenPeriodsBefore($from);

        FinanceRatePeriod::query()->create([
            'effective_from' => $from->toDateString(),
            'effective_to' => null,
            'store_fee_pct' => $validated['store_fee_pct'],
            'income_tax_pct' => $validated['income_tax_pct'],
            'kdv_pct' => $validated['kdv_pct'] ?? 0,
            'gift_payout_try' => $validated['gift_payout_try'],
            'coin_to_try' => $validated['coin_to_try'],
            'ad_click_floor_try' => $validated['ad_click_floor_try'] ?? 0.20,
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.finance.settings')
            ->with('success', 'Yeni oran dönemi eklendi. Önceki açık dönem kapatıldı.');
    }

    /**
     * Finans görünümünü bugün TR 00:00'dan başlat:
     * aktif oran dönemini bugüne çeker (oranlar korunur) + panele yönlendirir.
     */
    public function startFromToday()
    {
        $today = Carbon::parse(tr_now()->toDateString())->startOfDay();
        $current = FinanceService::rateFor($today);

        $alreadyToday = FinanceRatePeriod::query()
            ->whereDate('effective_from', $today->toDateString())
            ->whereNull('effective_to')
            ->first();

        if (!$alreadyToday) {
            FinanceService::closeOpenPeriodsBefore($today);
            FinanceRatePeriod::query()->create([
                'effective_from' => $today->toDateString(),
                'effective_to' => null,
                'store_fee_pct' => $current->store_fee_pct,
                'income_tax_pct' => $current->income_tax_pct,
                'kdv_pct' => $current->kdv_pct,
                'gift_payout_try' => $current->gift_payout_try,
                'coin_to_try' => $current->coin_to_try,
                'ad_click_floor_try' => $current->ad_click_floor_try,
                'note' => 'Başlangıç: ' . $today->format('d.m.Y') . ' 00:00',
                'created_by' => Auth::id(),
            ]);
        }

        return redirect()
            ->route('admin.finance.index', ['range' => 'agreed'])
            ->with('success', 'Finans başlangıcı ' . $today->format('d.m.Y') . ' 00:00 olarak ayarlandı. Özet kararlaştırılan tarihten itibaren.');
    }

    /** TR / EN sayı girdilerini float'a çevir (1.250,50 → 1250.5). */
    private static function normalizeNumber(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $s = trim(str_replace(["\xc2\xa0", ' '], '', (string) $value));
        $s = str_replace('%', '', $s);
        if ($s === '') {
            return $value;
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        } elseif (str_contains($s, '.')) {
            $parts = explode('.', $s, 2);
            if (isset($parts[1]) && strlen($parts[1]) === 3 && strlen($parts[0]) >= 1) {
                // 1.250 → 1250 (TR binlik)
                $s = $parts[0] . $parts[1];
            }
        }
        $s = preg_replace('/[^0-9.\-]/', '', $s) ?? $s;

        return is_numeric($s) ? $s : $value;
    }
}
