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
                FinanceLedgerEntry::SOURCE_OTHER_INCOME,
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
        $defaultKdvPct = (float) (FinanceService::rateFor($todayStart)->kdv_pct ?? 0);

        return view('admin.finance.index', compact(
            'summary',
            'from',
            'to',
            'range',
            'categories',
            'recentEntries',
            'periods',
            'iapSales',
            'agreedFrom',
            'defaultKdvPct'
        ));
    }

    public function storeEntry(Request $request)
    {
        foreach (['amount_try', 'base_amount_try', 'percent'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => self::normalizeNumber($request->input($field))]);
            }
        }

        $validated = $request->validate([
            'direction' => 'required|in:income,expense',
            'source' => 'required|in:ad_revenue,other_income,manual,kdv',
            'category_id' => 'nullable|integer|exists:finance_expense_categories,id',
            'entry_date' => 'required|date',
            'amount_mode' => 'nullable|in:fixed,percent',
            'amount_try' => 'nullable|numeric|min:0|max:99999999',
            'base_amount_try' => 'nullable|numeric|min:0|max:99999999',
            'percent' => 'nullable|numeric|min:0|max:100',
            'counts_for_tax' => 'nullable|boolean',
            'label' => 'nullable|string|max:200',
            'note' => 'nullable|string|max:2000',
            'payout_method' => 'nullable|in:multinet,papara,havale,parsela,other',
        ], [
            'entry_date.required' => 'Tarih gerekli.',
        ]);

        $mode = ($validated['amount_mode'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed';
        $meta = [];

        if ($mode === 'percent') {
            $base = round((float) ($validated['base_amount_try'] ?? 0), 2);
            $pct = round((float) ($validated['percent'] ?? 0), 4);
            if ($base <= 0 || $pct <= 0) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Yüzde girişinde matrah/baz tutar ve yüzde gerekli.');
            }
            $amount = round($base * $pct / 100, 2);
            $meta = [
                'amount_mode' => 'percent',
                'base_amount_try' => $base,
                'percent' => $pct,
            ];
        } else {
            $amount = round((float) ($validated['amount_try'] ?? 0), 2);
            $meta = ['amount_mode' => 'fixed'];
        }

        if ($amount < 0.01) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Hesaplanan / girilen tutar en az 0,01 ₺ olmalı.');
        }

        if ($lockErr = FinanceService::monthLockMessage($validated['entry_date'])) {
            return redirect()->back()->withInput()->with('error', $lockErr);
        }

        if ($validated['source'] === 'kdv') {
            $validated['direction'] = 'expense';
            $kdvCat = FinanceExpenseCategory::query()->where('slug', 'kdv_manual')->first();
            $validated['category_id'] = $kdvCat?->id ?? $validated['category_id'];
            $meta['counts_for_tax'] = $request->boolean('counts_for_tax');
        }
        if (in_array($validated['source'], ['ad_revenue', 'other_income'], true)) {
            $validated['direction'] = 'income';
            $validated['category_id'] = null;
            $meta['counts_for_tax'] = $request->boolean('counts_for_tax');
        }
        if ($validated['source'] === 'manual') {
            $validated['direction'] = 'expense';
            $meta['counts_for_tax'] = $request->boolean('counts_for_tax');
            if (empty($validated['category_id'])) {
                $other = FinanceExpenseCategory::query()->where('slug', 'other')->first();
                $validated['category_id'] = $other?->id;
            }
            $cat = FinanceExpenseCategory::query()->find($validated['category_id']);
            if ($cat && in_array($cat->slug, ['gift', 'kdv_manual'], true)) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Ödül otomatik (talep onayından) gelir; KDV için üstteki KDV sekmesini kullanın.');
            }
        }

        $label = $validated['label'] ?? null;
        if ($mode === 'percent' && $label === null) {
            $label = '%'.$meta['percent'].' × '.number_format($meta['base_amount_try'], 2, ',', '.').' ₺';
        } elseif ($mode === 'percent' && $label !== null && $label !== '') {
            $label = $label.' (%'.$meta['percent'].')';
        }

        FinanceLedgerEntry::query()->create([
            'direction' => $validated['direction'],
            'source' => $validated['source'],
            'category_id' => $validated['category_id'] ?? null,
            'entry_date' => $validated['entry_date'],
            'amount_try' => $amount,
            'currency' => 'TRY',
            'label' => $label,
            'note' => $validated['note'] ? trim(strip_tags($validated['note'])) : null,
            'payout_method' => $validated['payout_method'] ?? null,
            'meta' => $meta,
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
        if ($lockErr = FinanceService::monthLockMessage($entry->entry_date)) {
            return redirect()->route('admin.finance.index')->with('error', $lockErr);
        }
        $entry->delete();

        return redirect()
            ->route('admin.finance.index')
            ->with('success', 'Kayıt silindi.');
    }

    public function updateEntry(Request $request, int $id)
    {
        $entry = FinanceLedgerEntry::query()->findOrFail($id);
        if (!in_array($entry->source, [
            FinanceLedgerEntry::SOURCE_MANUAL,
            FinanceLedgerEntry::SOURCE_AD_REVENUE,
            FinanceLedgerEntry::SOURCE_OTHER_INCOME,
            FinanceLedgerEntry::SOURCE_KDV,
        ], true)) {
            return redirect()->back()->with('error', 'Bu kayıt türü düzenlenemez.');
        }

        foreach (['amount_try'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => self::normalizeNumber($request->input($field))]);
            }
        }

        $validated = $request->validate([
            'entry_date' => 'required|date',
            'amount_try' => 'required|numeric|min:0.01|max:99999999',
            'label' => 'nullable|string|max:200',
            'note' => 'nullable|string|max:2000',
            'category_id' => 'nullable|integer|exists:finance_expense_categories,id',
            'counts_for_tax' => 'nullable|boolean',
        ]);

        if ($lockErr = FinanceService::monthLockMessage($entry->entry_date)) {
            return redirect()->back()->with('error', $lockErr);
        }
        if ($lockErr = FinanceService::monthLockMessage($validated['entry_date'])) {
            return redirect()->back()->with('error', $lockErr);
        }

        $meta = is_array($entry->meta) ? $entry->meta : [];
        $meta['counts_for_tax'] = $request->boolean('counts_for_tax');

        $catId = $entry->category_id;
        if ($entry->source === FinanceLedgerEntry::SOURCE_MANUAL) {
            $catId = $validated['category_id'] ?? $catId;
        }

        $entry->update([
            'entry_date' => $validated['entry_date'],
            'amount_try' => round((float) $validated['amount_try'], 2),
            'label' => $validated['label'] ?? $entry->label,
            'note' => isset($validated['note']) ? trim(strip_tags((string) $validated['note'])) : $entry->note,
            'category_id' => $catId,
            'meta' => $meta,
        ]);

        return redirect()
            ->route('admin.finance.index', array_filter([
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'range' => $request->input('range'),
            ]))
            ->with('success', 'Kayıt güncellendi.');
    }

    public function export(Request $request)
    {
        $from = Carbon::parse($request->query('from', now()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        $type = $request->query('type', 'ledger'); // ledger | pnl
        $summary = FinanceService::summarize($from, $to);

        $filename = $type === 'pnl'
            ? 'finans-pnl-'.$from->toDateString().'_'.$to->toDateString().'.csv'
            : 'finans-ledger-'.$from->toDateString().'_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($type, $summary, $from, $to) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel UTF-8
            if ($type === 'pnl') {
                fputcsv($out, ['Kalem', 'Tutar_TRY'], ';');
                $rows = [
                    ['Donem', $summary['from'].' - '.$summary['to']],
                    ['IAP_brut', $summary['iap']['gross']],
                    ['IAP_magaza_kesinti', $summary['iap']['fee']],
                    ['IAP_net', $summary['iap']['net']],
                    ['IAP_iade_brut', $summary['iap']['refund_gross'] ?? 0],
                    ['IAP_iade_net', $summary['iap']['refund_net'] ?? 0],
                    ['IAP_net_iade_sonrasi', $summary['iap']['net_after_refunds'] ?? $summary['iap']['net']],
                    ['Reklam_geliri', $summary['ad_revenue']],
                    ['Diger_gelir', $summary['other_income']],
                    ['Toplam_gelir', $summary['income_total']],
                    ['Odul_gider', $summary['gift']['total']],
                    ['Manuel_gider', $summary['manual_other_expense']],
                    ['Fatura_KDV', $summary['kdv_invoice']],
                    ['Donem_KDV_PL', $summary['kdv_pl_expense'] ?? 0],
                    ['Toplam_gider', $summary['expense_total']],
                    ['Vergi_oncesi', $summary['pre_tax_profit']],
                    ['Vergi_tabani', $summary['tax_base']],
                    ['Gelir_vergisi', $summary['income_tax']],
                    ['Dip_kar', $summary['final_profit']],
                    ['KDV_pct', $summary['rates']['kdv_pct']],
                    ['KDV_to_PL', !empty($summary['rates']['kdv_to_pl']) ? 1 : 0],
                    ['Store_pct', $summary['rates']['store_fee_pct']],
                    ['GV_pct', $summary['rates']['income_tax_pct']],
                ];
                foreach ($rows as $r) {
                    fputcsv($out, $r, ';');
                }
            } else {
                fputcsv($out, ['id', 'tarih', 'yon', 'kaynak', 'kategori', 'tutar_try', 'etiket', 'not', 'vergi_dahil', 'odeme'], ';');
                $entries = FinanceLedgerEntry::query()
                    ->with('category:id,name')
                    ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
                    ->whereIn('source', [
                        FinanceLedgerEntry::SOURCE_MANUAL,
                        FinanceLedgerEntry::SOURCE_AD_REVENUE,
                        FinanceLedgerEntry::SOURCE_OTHER_INCOME,
                        FinanceLedgerEntry::SOURCE_KDV,
                        FinanceLedgerEntry::SOURCE_GIFT,
                    ])
                    ->orderBy('entry_date')
                    ->orderBy('id')
                    ->get();
                foreach ($entries as $e) {
                    $meta = is_array($e->meta) ? $e->meta : [];
                    $tax = array_key_exists('counts_for_tax', $meta) ? ((bool) $meta['counts_for_tax'] ? 1 : 0) : 1;
                    fputcsv($out, [
                        $e->id,
                        $e->entry_date?->toDateString(),
                        $e->direction,
                        $e->source,
                        $e->category?->name,
                        number_format((float) $e->amount_try, 2, '.', ''),
                        $e->label,
                        $e->note,
                        $tax,
                        $e->payout_method,
                    ], ';');
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function lockMonth(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'note' => 'nullable|string|max:255',
        ]);
        FinanceService::lockMonth((int) $validated['year'], (int) $validated['month'], Auth::id(), $validated['note'] ?? null);

        return redirect()->route('admin.finance.settings')->with('success', 'Ay kilitlendi.');
    }

    public function unlockMonth(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);
        FinanceService::unlockMonth((int) $validated['year'], (int) $validated['month']);

        return redirect()->route('admin.finance.settings')->with('success', 'Ay kilidi açıldı.');
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
        $monthLocks = \App\Models\FinanceMonthLock::query()->orderByDesc('year')->orderByDesc('month')->limit(24)->get();
        $lockYear = (int) now()->year;
        $lockMonth = (int) now()->month;

        return view('admin.finance.settings', compact(
            'periods',
            'categories',
            'currentClaimCoins',
            'currentPayoutTry',
            'monthLocks',
            'lockYear',
            'lockMonth'
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
            'kdv_to_pl' => 'nullable|boolean',
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
            'kdv_to_pl' => $request->boolean('kdv_to_pl'),
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

    public function updatePeriod(Request $request, int $id)
    {
        $period = FinanceRatePeriod::query()->findOrFail($id);

        foreach (['store_fee_pct', 'income_tax_pct', 'kdv_pct', 'gift_payout_try', 'coin_to_try', 'ad_click_floor_try'] as $field) {
            if ($request->filled($field)) {
                $request->merge([$field => self::normalizeNumber($request->input($field))]);
            }
        }

        $validated = $request->validate([
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'store_fee_pct' => 'required|numeric|min:0|max:100',
            'income_tax_pct' => 'required|numeric|min:0|max:100',
            'kdv_pct' => 'nullable|numeric|min:0|max:100',
            'kdv_to_pl' => 'nullable|boolean',
            'gift_payout_try' => 'required|numeric|min:0|max:999999',
            'coin_to_try' => 'required|numeric|min:0|max:100',
            'ad_click_floor_try' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string|max:255',
        ], [
            'store_fee_pct.numeric' => 'Store % sayı olmalı.',
            'income_tax_pct.numeric' => 'GV % sayı olmalı.',
            'gift_payout_try.numeric' => 'Ödül tutarı sayı olmalı.',
            'coin_to_try.numeric' => 'Coin→₺ sayı olmalı.',
            'effective_to.after_or_equal' => 'Bitiş, başlangıçtan önce olamaz.',
        ]);

        $from = Carbon::parse($validated['effective_from'])->startOfDay();
        $to = !empty($validated['effective_to'])
            ? Carbon::parse($validated['effective_to'])->startOfDay()
            : null;

        $period->update([
            'effective_from' => $from->toDateString(),
            'effective_to' => $to?->toDateString(),
            'store_fee_pct' => $validated['store_fee_pct'],
            'income_tax_pct' => $validated['income_tax_pct'],
            'kdv_pct' => $validated['kdv_pct'] ?? 0,
            'kdv_to_pl' => $request->boolean('kdv_to_pl'),
            'gift_payout_try' => $validated['gift_payout_try'],
            'coin_to_try' => $validated['coin_to_try'],
            'ad_click_floor_try' => $validated['ad_click_floor_try'] ?? $period->ad_click_floor_try,
            'note' => $validated['note'] ?? null,
        ]);

        return redirect()
            ->route('admin.finance.settings')
            ->with('success', 'Oran dönemi güncellendi.');
    }

    public function destroyPeriod(int $id)
    {
        $period = FinanceRatePeriod::query()->findOrFail($id);
        $total = FinanceRatePeriod::query()->count();
        if ($total <= 1) {
            return redirect()
                ->route('admin.finance.settings')
                ->with('error', 'Son oran dönemi silinemez.');
        }

        $wasOpen = $period->effective_to === null;
        $from = $period->effective_from?->toDateString();
        $period->delete();

        // Silinen açıktıysa bir önceki dönemi tekrar açık bırak
        if ($wasOpen && $from) {
            $prev = FinanceRatePeriod::query()
                ->where('effective_from', '<', $from)
                ->orderByDesc('effective_from')
                ->first();
            if ($prev) {
                $prev->update(['effective_to' => null]);
            }
        }

        return redirect()
            ->route('admin.finance.settings')
            ->with('success', 'Oran dönemi silindi.');
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
