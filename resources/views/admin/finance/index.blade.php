@extends('admin.layouts.app')

@section('title', 'Finans')

@push('styles')
<style>
.fin-wrap { max-width: 1400px; }
.fin-hero {
    background: linear-gradient(135deg, #0c2340 0%, #12405a 45%, #0f5c56 100%);
    border-radius: 14px; color: #fff; padding: 1.35rem 1.5rem; margin-bottom: 1rem;
    border: 1px solid rgba(153,246,228,.12);
    box-shadow: 0 10px 28px rgba(12,35,64,.18);
}
.fin-hero h3 { color: #fff !important; margin: 0 0 .35rem; font-weight: 650; }
.fin-hero p { margin: 0; color: rgba(255,255,255,.8); font-size: .95rem; }
.fin-hero a.fin-link, .fin-hero button.fin-link {
    color: #fff !important; border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.08);
    padding: .4rem .85rem; border-radius: 8px; text-decoration: none !important; font-size: .85rem; font-weight: 600;
}
.fin-hero a.fin-link:hover, .fin-hero button.fin-link:hover { background: rgba(255,255,255,.16); color: #fff !important; }
.fin-entry {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem 1.15rem; margin-bottom: 1rem;
    box-shadow: 0 8px 24px rgba(15,23,42,.04);
}
.fin-entry-hint { font-size: .78rem; color: #64748b; margin: 0 0 .65rem; }
.fin-entry-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .85rem; }
.fin-entry-tabs button {
    border: 1px solid #e2e8f0; background: #f8fafc; color: #334155; border-radius: 999px;
    padding: .35rem .9rem; font-size: .82rem; font-weight: 600; cursor: pointer;
}
.fin-entry-tabs button.is-on { background: #0f172a; color: #fff; border-color: #0f172a; }
.fin-entry-grid { display: grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap: .55rem; align-items: end; }
@media (max-width: 1100px) { .fin-entry-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
@media (max-width: 640px) { .fin-entry-grid { grid-template-columns: 1fr 1fr; } }
.fin-entry-grid .fin-span2 { grid-column: span 2; }
.fin-entry label { display:block; font-size:.72rem; color:#64748b; margin-bottom:.2rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
.fin-presets { display:flex; flex-wrap:wrap; gap:.4rem; }
.fin-presets a {
    font-size:.8rem; padding:.4rem .75rem; border-radius:999px; border:1px solid #e2e8f0;
    color:#334155; text-decoration:none; background:#fff; font-weight:600;
    min-height: 2rem; display:inline-flex; align-items:center;
}
.fin-presets a.is-on { background:#0f172a; color:#fff; border-color:#0f172a; }
.fin-kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .75rem; margin-bottom: 1rem; }
@media (max-width: 992px) { .fin-kpis { grid-template-columns: repeat(2, minmax(0,1fr)); } }
.fin-kpi {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.1rem;
    cursor: pointer; transition: border-color .15s, box-shadow .15s; text-align: left; width: 100%;
    min-height: 7.25rem; display: flex; flex-direction: column; justify-content: space-between;
}
.fin-kpi:hover, .fin-kpi.is-open { border-color: #94a3b8; box-shadow: 0 6px 18px rgba(15,23,42,.06); }
.fin-kpi .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; line-height: 1.2; min-height: 1rem; }
.fin-kpi .v { font-size: 1.4rem; font-weight: 700; margin-top: .35rem; font-variant-numeric: tabular-nums; letter-spacing: -.02em; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fin-kpi .s { font-size: .75rem; color: #94a3b8; margin-top: .35rem; line-height: 1.35; min-height: 2.1rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.fin-kpi .hint { font-size: .68rem; color: #cbd5e1; margin-top: .25rem; }
.fin-sub { display:block; font-size:.68rem; font-weight:500; color:#94a3b8; text-transform:none; letter-spacing:0; margin-top:.15rem; line-height:1.3; }
.fin-detail {
    display: none; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 1rem 1.15rem; margin: -.35rem 0 1rem; box-shadow: 0 8px 20px rgba(15,23,42,.05);
}
.fin-detail.is-on { display: block; }
.fin-detail h5 { font-size: .95rem; font-weight: 650; margin: 0 0 .65rem; }
.fin-detail .fin-row { display: flex; justify-content: space-between; gap: 1rem; padding: .4rem 0; border-bottom: 1px dashed #f1f5f9; font-size: .9rem; }
.fin-detail .fin-row:last-child { border-bottom: 0; }
.fin-detail .formula { background: #f8fafc; border-radius: 8px; padding: .65rem .85rem; font-size: .82rem; color: #475569; margin-top: .5rem; }
.fin-pack { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:.75rem; margin-bottom:1rem; }
@media (max-width: 768px) { .fin-pack { grid-template-columns: 1fr; } }
.fin-pack .item {
    background: linear-gradient(180deg, #fff, #f8fafc); border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.1rem;
    cursor: pointer; transition: border-color .15s; text-align: left; width: 100%;
    min-height: 6.5rem; display: flex; flex-direction: column; justify-content: space-between;
}
.fin-pack .item:hover, .fin-pack .item.is-open { border-color: #94a3b8; }
.fin-pack .t { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#64748b; line-height:1.2; min-height:1rem; }
.fin-pack .n { font-size:1.35rem; font-weight:700; margin-top:.35rem; font-variant-numeric:tabular-nums; line-height:1.2; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.fin-pack .c { font-size:.75rem; color:#94a3b8; margin-top:.35rem; line-height:1.35; min-height:2.1rem; }
.fin-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1rem; overflow: hidden; height: 100%; }
.fin-card .hd { padding: .85rem 1.1rem; border-bottom: 1px solid #f1f5f9; font-weight: 650; font-size: .95rem; color:#0f172a; display:flex; justify-content:space-between; align-items:center; gap:.75rem; flex-wrap:wrap; min-height: 3rem; }
.fin-card .hd .fin-sub { margin-top: .1rem; }
.fin-card .bd { padding: 1.1rem; }
.fin-row { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px dashed #f1f5f9; font-size: .92rem; }
.fin-row:last-child { border-bottom: 0; }
.fin-row .amt { font-variant-numeric: tabular-nums; font-weight: 600; }
.fin-pos { color: #15803d; }
.fin-neg { color: #b91c1c; }
.fin-muted { color: #64748b; }
.fin-chart { width: 100%; height: 280px; }
.fin-chart-sm { width: 100%; height: 240px; }
.fin-charts-row { margin-top: 1.5rem; margin-bottom: 1.5rem; }
.fin-charts-row + .fin-charts-row { margin-top: 0.5rem; }
.fin-tables-row { margin-bottom: 1.5rem; }
.fin-chart-shell {
    background: linear-gradient(165deg, #0f2744 0%, #12324f 48%, #0d3b3a 100%);
    border-radius: 12px; padding: 1rem .85rem .65rem; min-height: 100%;
    border: 1px solid rgba(148,163,184,.18);
    box-shadow: inset 0 1px 0 rgba(255,255,255,.06);
}
.fin-chart-shell canvas { max-width: 100%; }
.fin-doughnut-wrap { position: relative; height: 240px; }
.fin-doughnut-center {
    position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;
    pointer-events: none; color: #ecfeff; text-align: center; padding-top: .25rem;
}
.fin-doughnut-center .v { font-size: 1.2rem; font-weight: 750; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
.fin-doughnut-center .l { font-size: .68rem; color: #99f6e4; text-transform: uppercase; letter-spacing: .05em; margin-top: .15rem; opacity:.9; }
.fin-card .hd { border-bottom-color: #eef2ff; }
.fin-info {
    background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 10px; padding: .75rem 1rem; font-size: .85rem; color: #475569;
}
.fin-sales-table { table-layout: fixed; width: 100%; }
.fin-sales-table th, .fin-sales-table td { vertical-align: middle; }
.fin-sales-table .col-date { width: 9.5rem; }
.fin-sales-table .col-type { width: 5.5rem; }
.fin-sales-table .col-pkg { width: auto; }
.fin-sales-table .col-num { width: 6.5rem; }
.fin-sales-table .pkg-cell {
    overflow: hidden; max-width: 100%;
}
.fin-sales-table .pkg-cell strong {
    display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.fin-pager { display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; }
.fin-pager a, .fin-pager span {
    font-size:.78rem; padding:.25rem .55rem; border-radius:6px; border:1px solid #e2e8f0;
    color:#334155; text-decoration:none; background:#fff; font-weight:600;
}
.fin-pager span.is-on { background:#0f172a; color:#fff; border-color:#0f172a; }
.fin-pager span.is-disabled { opacity:.4; }
.fin-track { display:grid; grid-template-columns: 1fr 1fr; gap:.75rem; margin-bottom:1rem; }
@media (max-width: 768px) { .fin-track { grid-template-columns: 1fr; } }
.fin-track .box {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.1rem;
}
.fin-track .box h5 { font-size:.9rem; font-weight:650; margin:0 0 .15rem; }
.fin-track .tag {
    display:inline-block; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
    padding:.15rem .45rem; border-radius:999px; margin-bottom:.55rem;
}
.fin-track .tag-auto { background:#ecfdf5; color:#047857; }
.fin-track .tag-man { background:#eff6ff; color:#1d4ed8; }
.fin-rates {
    display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem;
}
.fin-rates .chip {
    background:#fff; border:1px solid #e2e8f0; border-radius:999px; padding:.35rem .75rem;
    font-size:.78rem; color:#334155; font-weight:600;
}
.fin-rates .chip strong { color:#0f172a; }
</style>
@endpush

@section('content')
@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif
@php
    $s = $summary;
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.') . ' ₺';
    $range = $range ?? 'all';
    $storePct = (float) ($s['rates']['store_fee_pct'] ?? $s['active_rate']->store_fee_pct ?? 40);
    $taxPct = (float) ($s['income_tax_pct'] ?? 25);
    $kdvPct = (float) ($s['rates']['kdv_pct'] ?? 0);
    $tracks = $s['tracks'] ?? [];
    $agreedFrom = $agreedFrom ?? $from;
    $typeLabels = ['coin' => 'Jeton', 'premium' => 'Premium', 'joker' => 'Joker', 'diamond' => 'Elmas', 'other' => 'Diğer'];
@endphp
<div class="fin-wrap">
    <div class="fin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h3>Finans · TL (nakit)</h3>
            <p>Gelir / gider takibi · {{ tr_time($from, 'd.m.Y') }} – {{ tr_time($to, 'd.m.Y') }}
                · {{ (int) ($s['iap']['count'] ?? 0) }} paket satışı
                · sistematik + manuel</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            {{-- Geçici gizlendi: P&L / Ledger CSV
            <a href="{{ route('admin.finance.export', ['type' => 'pnl', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="fin-link">P&L CSV</a>
            <a href="{{ route('admin.finance.export', ['type' => 'ledger', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="fin-link">Ledger CSV</a>
            --}}
            <a href="{{ route('admin.finance.coin.index') }}" class="fin-link">Coin finans</a>
            <a href="{{ route('admin.finance.settings') }}" class="fin-link">Oranlar &amp; kategoriler</a>
        </div>
    </div>

    <div class="fin-rates">
        <span class="chip">Store <strong>%{{ number_format($storePct, 0) }}</strong> · her IAP satışında</span>
        <span class="chip">GV <strong>%{{ number_format($taxPct, 1, ',', '.') }}</strong> · vergi tabanı {{ $fmt($s['tax_base'] ?? 0) }}</span>
        <span class="chip">KDV dönem <strong>%{{ number_format($kdvPct, 1, ',', '.') }}</strong>
            · {{ !empty($s['rates']['kdv_to_pl']) ? 'P&L’ye yazılıyor' : 'sadece ref' }}
            @if($kdvPct > 0)
                · {{ $fmt($s['rates']['kdv_ref_on_iap_gross'] ?? 0) }}
            @endif
        </span>
        <span class="chip">Fatura KDV <strong>{{ $fmt($s['kdv_invoice'] ?? 0) }}</strong> · manuel</span>
    </div>
    <div class="fin-entry">
        <div class="fin-entry-tabs" id="finTabs">
            <button type="button" class="is-on" data-tab="manual">Gider ekle</button>
            <button type="button" data-tab="other_income">Gelir ekle</button>
            <button type="button" data-tab="ad_revenue">Reklam geliri</button>
            <button type="button" data-tab="kdv">KDV (fatura)</button>
        </div>
        <p class="fin-entry-hint" id="finEntryHint">
            Sistematik: IAP (store düşülür), ödül onayı, GV otomatik. Buradan manuel gider / gelir / reklam / fatura KDV girin.
        </p>
        <form method="post" action="{{ route('admin.finance.entries.store') }}" id="finEntryForm">
            @csrf
            <input type="hidden" name="from" value="{{ $from->toDateString() }}">
            <input type="hidden" name="to" value="{{ $to->toDateString() }}">
            <input type="hidden" name="range" value="{{ $range }}">
            <input type="hidden" name="source" id="finSource" value="manual">
            <input type="hidden" name="direction" id="finDirection" value="expense">
            <div class="fin-entry-grid" id="finEntryGrid">
                <div id="finCatWrap">
                    <label>Kategori</label>
                    <select name="category_id" class="form-select form-select-sm">
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Tarih</label>
                    <input type="date" name="entry_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                </div>
                <div id="finModeWrap">
                    <label>Giriş tipi</label>
                    <select name="amount_mode" id="finAmountMode" class="form-select form-select-sm">
                        <option value="fixed" selected>Tutar (₺)</option>
                        <option value="percent">Yüzde (%)</option>
                    </select>
                </div>
                <div id="finFixedWrap">
                    <label>Tutar (₺)</label>
                    <input type="text" inputmode="decimal" name="amount_try" id="finAmountTry" class="form-control form-control-sm"
                           data-fin-num="money" data-fin-suffix="₺" placeholder="0,00">
                </div>
                <div id="finBaseWrap" style="display:none">
                    <label>Matrah / baz (₺)</label>
                    <input type="text" inputmode="decimal" name="base_amount_try" id="finBaseAmount" class="form-control form-control-sm"
                           data-fin-num="money" data-fin-suffix="₺" placeholder="0,00">
                </div>
                <div id="finPctWrap" style="display:none">
                    <label>Yüzde (%)</label>
                    <input type="text" inputmode="decimal" name="percent" id="finPercent" class="form-control form-control-sm"
                           data-fin-num="pct" data-fin-suffix="%" placeholder="20"
                           value="{{ ($defaultKdvPct ?? 0) > 0 ? rtrim(rtrim(number_format($defaultKdvPct, 2, ',', ''), '0'), ',') : '' }}">
                </div>
                <div id="finPreviewWrap" style="display:none">
                    <label>Hesaplanan</label>
                    <div class="form-control form-control-sm bg-light" id="finAmountPreview" style="font-weight:600">0,00 ₺</div>
                </div>
                <div class="fin-span2">
                    <label>Açıklama</label>
                    <input type="text" name="label" class="form-control form-control-sm" maxlength="200" placeholder="örn. Meta reklam / Multinet yükleme">
                </div>
                <div id="finTaxWrap" style="display:none">
                    <label id="finTaxLabel">Vergi durumu</label>
                    <select name="counts_for_tax" id="finTaxMode" class="form-select form-select-sm">
                        <option value="0" id="finTaxOpt0">Net — GV hesabına katma</option>
                        <option value="1" id="finTaxOpt1">Brüt — vergi tabanına dahil</option>
                    </select>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-sm btn-dark w-100" id="finSubmitBtn">Gider kaydet</button>
                </div>
            </div>
            <div class="mt-2">
                <input type="text" name="note" class="form-control form-control-sm" maxlength="2000" placeholder="Not (opsiyonel)">
            </div>
            <p class="fin-entry-hint mt-2 mb-0" id="finTaxHint" style="display:none">
                GV satırdan kesilmez; dönem vergi tabanına göre hesaplanır. Net seçerseniz tutar gelire yazar ama GV’ye girmez.
            </p>
        </form>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="fin-presets">
            <a href="{{ route('admin.finance.index', ['range' => 'agreed']) }}" class="{{ $range === 'agreed' ? 'is-on' : '' }}" title="{{ tr_time($agreedFrom, 'd.m.Y') }} 00:00">Kararlaştırılan Tarihten itibaren</a>
            <a href="{{ route('admin.finance.index', ['range' => 'all']) }}" class="{{ $range === 'all' ? 'is-on' : '' }}">Tüm zamanlar</a>
            <a href="{{ route('admin.finance.index', ['range' => 'today']) }}" class="{{ $range === 'today' ? 'is-on' : '' }}">Sadece bugün</a>
            <a href="{{ route('admin.finance.index', ['range' => 'month']) }}" class="{{ $range === 'month' ? 'is-on' : '' }}">Bu ay</a>
            <a href="{{ route('admin.finance.index', ['range' => 'last_month']) }}" class="{{ $range === 'last_month' ? 'is-on' : '' }}">Geçen ay</a>
            <a href="{{ route('admin.finance.index', ['range' => '90d']) }}" class="{{ $range === '90d' ? 'is-on' : '' }}">Son 90 gün</a>
        </div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
            </div>
            <div>
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
            </div>
            <button class="btn btn-sm btn-outline-dark" type="submit">Tarih uygula</button>
        </form>
    </div>

    <div class="fin-kpis">
        <button type="button" class="fin-kpi" data-detail="income">
            <div>
                <div class="k">Gelir (net)</div>
                <div class="v fin-pos">{{ $fmt($s['income_total']) }}</div>
                <div class="s">IAP net + reklam + diğer manuel gelir</div>
            </div>
            <div class="hint">Detay için tıkla</div>
        </button>
        <button type="button" class="fin-kpi" data-detail="expense">
            <div>
                <div class="k">Gider</div>
                <div class="v fin-neg">{{ $fmt($s['expense_total']) }}</div>
                <div class="s">Ödül {{ $fmt($tracks['auto_expense'] ?? $s['gift']['total']) }} + manuel {{ $fmt($tracks['manual_expense'] ?? 0) }} + KDV {{ $fmt($tracks['kdv_expense'] ?? 0) }}</div>
            </div>
            <div class="hint">Detay için tıkla</div>
        </button>
        <button type="button" class="fin-kpi" data-detail="pretax">
            <div>
                <div class="k">Vergi öncesi</div>
                <div class="v">{{ $fmt($s['pre_tax_profit']) }}</div>
                <div class="s">Gelir − gider</div>
            </div>
            <div class="hint">Detay için tıkla</div>
        </button>
        <button type="button" class="fin-kpi" data-detail="final">
            <div>
                <div class="k">Dip kar</div>
                <div class="v {{ $s['final_profit'] >= 0 ? 'fin-pos' : 'fin-neg' }}">{{ $fmt($s['final_profit']) }}</div>
                <div class="s">Vergi tabanı {{ $fmt($s['tax_base'] ?? 0) }} · %{{ rtrim(rtrim(number_format($taxPct, 2, '.', ''), '0'), '.') }} (−{{ $fmt($s['income_tax']) }})</div>
            </div>
            <div class="hint">Detay için tıkla</div>
        </button>
    </div>

    <div id="finDetailIncome" class="fin-detail" data-panel="income">
        <h5>Gelir nasıl hesaplanır?</h5>
        <div class="fin-row">
            <span>Uygulama içi satış (brüt)<span class="fin-sub">In-App Purchase · IAP</span></span>
            <span class="amt">{{ $fmt($s['iap']['gross']) }}</span>
        </div>
        <div class="fin-row"><span>Mağaza kesintisi (%{{ number_format($storePct, 0) }})</span><span class="amt fin-neg">−{{ $fmt($s['iap']['fee']) }}</span></div>
        <div class="fin-row">
            <span>Uygulama içi satış (net)<span class="fin-sub">Mağaza payı düşülmüş</span></span>
            <span class="amt fin-pos">{{ $fmt($s['iap']['net']) }}</span>
        </div>
        @if(($s['iap']['refund_count'] ?? 0) > 0 || ($s['iap']['refund_net'] ?? 0) > 0)
            <div class="fin-row"><span>IAP iade ({{ (int) $s['iap']['refund_count'] }}) brüt</span><span class="amt fin-neg">−{{ $fmt($s['iap']['refund_gross']) }}</span></div>
            <div class="fin-row"><span>IAP iade net</span><span class="amt fin-neg">−{{ $fmt($s['iap']['refund_net']) }}</span></div>
            <div class="fin-row"><span>IAP net (iade sonrası)</span><span class="amt fin-pos">{{ $fmt($s['iap']['net_after_refunds']) }}</span></div>
        @else
            <div class="fin-row"><span class="fin-muted">IAP iade</span><span class="amt fin-muted">0 (refunded_at / status=refunded)</span></div>
        @endif
        <div class="fin-row"><span>Reklam geliri (manuel)</span><span class="amt fin-pos">{{ $fmt($s['ad_revenue']) }}</span></div>
        <div class="fin-row"><span>Diğer gelir (manuel)</span><span class="amt fin-pos">{{ $fmt($s['other_income'] ?? 0) }}</span></div>
        <div class="fin-row"><strong>Toplam gelir</strong><strong class="amt fin-pos">{{ $fmt($s['income_total']) }}</strong></div>
        <div class="formula">Formül: IAP net + reklam + diğer manuel gelir. Her IAP satışı kendi tarihindeki store oranıyla netlenir.</div>
    </div>
    <div id="finDetailExpense" class="fin-detail" data-panel="expense">
        <h5>Gider nasıl hesaplanır?</h5>
        <div class="fin-row"><span>Ödül talepleri ({{ $s['gift']['count'] }}) — sistematik</span><span class="amt fin-neg">−{{ $fmt($s['gift']['total']) }}</span></div>
        @foreach($s['gift']['by_method'] as $method => $amt)
            <div class="fin-row">
                <span class="fin-muted">· {{ \App\Models\FinanceLedgerEntry::PAYOUT_METHODS[$method] ?? $method }}</span>
                <span class="amt">{{ $fmt($amt) }}</span>
            </div>
        @endforeach
        @foreach($s['manual_by_category'] as $row)
            <div class="fin-row"><span>{{ $row['category'] }} — manuel</span><span class="amt fin-neg">−{{ $fmt($row['total']) }}</span></div>
        @endforeach
        <div class="fin-row"><span>Fatura KDV — manuel</span><span class="amt fin-neg">−{{ $fmt($s['kdv_invoice'] ?? 0) }}</span></div>
        @if(($s['kdv_pl_expense'] ?? 0) > 0)
            <div class="fin-row"><span>Dönem KDV (P&L)</span><span class="amt fin-neg">−{{ $fmt($s['kdv_pl_expense']) }}</span></div>
        @endif
        <div class="fin-row"><strong>Toplam gider</strong><strong class="amt fin-neg">−{{ $fmt($s['expense_total']) }}</strong></div>
        <div class="formula">Ödül otomatik. Manuel giderde “vergiye dahil” seçilebilir. Dönem KDV% ayarda P&L’ye yaz seçiliyse giderleşir. İade: payments.status=refunded / refunded_at.</div>
    </div>
    <div id="finDetailPretax" class="fin-detail" data-panel="pretax">
        <h5>Vergi öncesi</h5>
        <div class="fin-row"><span>Gelir</span><span class="amt fin-pos">{{ $fmt($s['income_total']) }}</span></div>
        <div class="fin-row"><span>Gider</span><span class="amt fin-neg">−{{ $fmt($s['expense_total']) }}</span></div>
        <div class="fin-row"><strong>Vergi öncesi</strong><strong class="amt">{{ $fmt($s['pre_tax_profit']) }}</strong></div>
        <div class="formula">Gelir − gider. Negatifse zarar; gelir vergisi tabanı 0 alınır.</div>
    </div>
    <div id="finDetailFinal" class="fin-detail" data-panel="final">
        <h5>Dip kar</h5>
        <div class="fin-row"><span>Nakit vergi öncesi (tüm gelir − gider)</span><span class="amt">{{ $fmt($s['pre_tax_profit']) }}</span></div>
        <div class="fin-row"><span>Vergiye dahil manuel gelir</span><span class="amt">{{ $fmt($s['taxable_manual_income'] ?? 0) }}</span></div>
        <div class="fin-row"><span>Net / GV dışı manuel gelir</span><span class="amt fin-muted">{{ $fmt($s['nontaxable_manual_income'] ?? 0) }}</span></div>
        <div class="fin-row"><span>Vergi tabanı</span><span class="amt">{{ $fmt($s['tax_base'] ?? 0) }}</span></div>
        <div class="fin-row"><span>Gelir vergisi (%{{ number_format($taxPct, 1, ',', '.') }})</span><span class="amt fin-neg">−{{ $fmt($s['income_tax']) }}</span></div>
        <div class="fin-row"><strong>Dip kar</strong><strong class="amt {{ $s['final_profit'] >= 0 ? 'fin-pos' : 'fin-neg' }}">{{ $fmt($s['final_profit']) }}</strong></div>
        <div class="formula">GV satırdan düşülmez. Taban = IAP net + brüt işaretli manuel gelir − giderler. Net işaretli gelir nakit özetine girer, GV’ye girmez.</div>
    </div>

    <div class="fin-track">
        <div class="box">
            <span class="tag tag-auto">Sistematik</span>
            <h5>Otomatik hesaplanan</h5>
            <div class="fin-row"><span>IAP net (store %{{ number_format($storePct, 0) }} düşülmüş)</span><span class="amt fin-pos">{{ $fmt($tracks['auto_income'] ?? $s['iap']['net']) }}</span></div>
            <div class="fin-row"><span>Ödül talepleri ({{ $s['gift']['count'] }})</span><span class="amt fin-neg">−{{ $fmt($tracks['auto_expense'] ?? $s['gift']['total']) }}</span></div>
            <div class="fin-row"><span>Gelir vergisi (%{{ number_format($taxPct, 1, ',', '.') }} · taban {{ $fmt($s['tax_base'] ?? 0) }})</span><span class="amt fin-neg">−{{ $fmt($s['income_tax']) }}</span></div>
            <div class="fin-row"><span class="fin-muted">Mağaza kesintisi (bilgi)</span><span class="amt fin-muted">−{{ $fmt($s['iap']['fee']) }}</span></div>
            @if($kdvPct > 0)
                <div class="fin-row"><span class="fin-muted">KDV ref. IAP × %{{ number_format($kdvPct, 1, ',', '.') }}</span><span class="amt fin-muted">{{ $fmt($s['rates']['kdv_ref_on_iap_gross'] ?? 0) }}</span></div>
            @endif
        </div>
        <div class="box">
            <span class="tag tag-man">Manuel</span>
            <h5>Sizin girdiğiniz</h5>
            <div class="fin-row"><span>Reklam geliri</span><span class="amt fin-pos">{{ $fmt($tracks['ad_revenue'] ?? $s['ad_revenue']) }}</span></div>
            <div class="fin-row"><span>Diğer gelir</span><span class="amt fin-pos">{{ $fmt($tracks['other_income'] ?? 0) }}</span></div>
            <div class="fin-row"><span>Giderler (kategori)</span><span class="amt fin-neg">−{{ $fmt($tracks['manual_expense'] ?? 0) }}</span></div>
            <div class="fin-row"><span>Fatura KDV</span><span class="amt fin-neg">−{{ $fmt($tracks['kdv_expense'] ?? 0) }}</span></div>
            @forelse(($s['manual_by_category'] ?? []) as $row)
                <div class="fin-row"><span class="fin-muted">· {{ $row['category'] }}</span><span class="amt">{{ $fmt($row['total']) }}</span></div>
            @empty
            @endforelse
        </div>
    </div>

    <div class="fin-pack">
        @foreach(['coin' => 'Jeton', 'premium' => 'Premium', 'joker' => 'Joker'] as $typeKey => $typeName)
            @php
                $typeNet = (float) ($s['iap']['by_type'][$typeKey] ?? 0);
                $typeCnt = (int) ($s['iap']['count_by_type'][$typeKey] ?? 0);
                $typeGrossApprox = $storePct < 100 ? round($typeNet / (1 - $storePct / 100), 2) : $typeNet;
                $typeFeeApprox = round($typeGrossApprox - $typeNet, 2);
            @endphp
            <button type="button" class="item" data-pack="{{ $typeKey }}">
                <div>
                    <div class="t">{{ $typeName }} satışı (net)</div>
                    <div class="n fin-pos">{{ $fmt($typeNet) }}</div>
                </div>
                <div class="c">{{ $typeCnt }} satış · mağaza payı düşülmüş</div>
            </button>
        @endforeach
    </div>
    @foreach(['coin' => 'Jeton', 'premium' => 'Premium', 'joker' => 'Joker'] as $typeKey => $typeName)
        @php
            $typeNet = (float) ($s['iap']['by_type'][$typeKey] ?? 0);
            $typeCnt = (int) ($s['iap']['count_by_type'][$typeKey] ?? 0);
            $typeGrossApprox = $storePct < 100 ? round($typeNet / (1 - $storePct / 100), 2) : $typeNet;
            $typeFeeApprox = round($typeGrossApprox - $typeNet, 2);
        @endphp
        <div class="fin-detail" data-pack-panel="{{ $typeKey }}">
            <h5>{{ $typeName }} paket kırılımı</h5>
            <div class="fin-row"><span>Satış adedi</span><span class="amt">{{ $typeCnt }}</span></div>
            <div class="fin-row"><span>Tahmini brüt (ters)</span><span class="amt">{{ $fmt($typeGrossApprox) }}</span></div>
            <div class="fin-row"><span>Mağaza kesintisi ≈ %{{ number_format($storePct, 0) }}<span class="fin-sub">Google / Apple mağaza payı</span></span><span class="amt fin-neg">−{{ $fmt($typeFeeApprox) }}</span></div>
            <div class="fin-row"><strong>Net</strong><strong class="amt fin-pos">{{ $fmt($typeNet) }}</strong></div>
            <div class="formula">Her satır kendi tarihindeki mağaza oranıyla netlenir. Kartta görünen net, dönem içi {{ strtolower($typeName) }} satışlarının toplamıdır.</div>
        </div>
    @endforeach

    <div class="row g-3 fin-charts-row">
        <div class="col-lg-8">
            <div class="fin-card">
                <div class="hd">
                    <div>
                        <span>Günlük net</span>
                        <span class="fin-sub">Gelir / gider / net (₺)</span>
                    </div>
                </div>
                <div class="bd">
                    <div class="fin-chart-shell">
                        <canvas id="finChartDaily" class="fin-chart" height="280"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="fin-card">
                <div class="hd">
                    <div>
                        <span>Paket dağılımı</span>
                        <span class="fin-sub">Uygulama içi satış net payı</span>
                    </div>
                </div>
                <div class="bd">
                    <div class="fin-chart-shell fin-doughnut-wrap">
                        <canvas id="finChartMix" class="fin-chart-sm" height="240"></canvas>
                        <div class="fin-doughnut-center">
                            <div class="v" id="finMixCenterVal">—</div>
                            <div class="l">net toplam</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 fin-charts-row">
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">
                    <div>
                        <span>Gelir vs gider</span>
                        <span class="fin-sub">Dönem toplamı (₺)</span>
                    </div>
                </div>
                <div class="bd">
                    <div class="fin-chart-shell">
                        <canvas id="finChartBars" class="fin-chart-sm" height="240"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">
                    <div>
                        <span>Gelir &amp; gider özeti</span>
                        <span class="fin-sub">Uygulama içi satış · reklam · ödül · manuel</span>
                    </div>
                </div>
                <div class="bd">
                    <div class="fin-row">
                        <span>Uygulama içi satış (brüt)<span class="fin-sub">In-App Purchase · IAP</span></span>
                        <span class="amt">{{ $fmt($s['iap']['gross']) }}</span>
                    </div>
                    <div class="fin-row"><span>Mağaza kesintisi (%{{ number_format($storePct, 0) }})</span><span class="amt fin-neg">−{{ $fmt($s['iap']['fee']) }}</span></div>
                    <div class="fin-row"><span>Uygulama içi satış (net)</span><span class="amt fin-pos">{{ $fmt($s['iap']['net']) }}</span></div>
                    <div class="fin-row"><span>Reklam geliri</span><span class="amt fin-pos">{{ $fmt($s['ad_revenue']) }}</span></div>
                    <div class="fin-row"><span>Diğer gelir</span><span class="amt fin-pos">{{ $fmt($s['other_income'] ?? 0) }}</span></div>
                    <div class="fin-row"><strong>Toplam gelir</strong><strong class="amt fin-pos">{{ $fmt($s['income_total']) }}</strong></div>
                    <hr class="my-2">
                    <div class="fin-row"><span>Ödül ({{ $s['gift']['count'] }}) — sistematik</span><span class="amt fin-neg">−{{ $fmt($s['gift']['total']) }}</span></div>
                    <div class="fin-row"><span>Manuel gider</span><span class="amt fin-neg">−{{ $fmt($s['manual_other_expense'] ?? 0) }}</span></div>
                    <div class="fin-row"><span>Fatura KDV</span><span class="amt fin-neg">−{{ $fmt($s['kdv_invoice'] ?? 0) }}</span></div>
                    <div class="fin-row"><strong>Toplam gider</strong><strong class="amt fin-neg">−{{ $fmt($s['expense_total']) }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="fin-info mb-3">
        <strong>Store</strong> = mağaza komisyonu (IAP’de otomatik).
        <strong>GV</strong> = dönem kârı üzerinden otomatik.
        <strong>Dönem KDV%</strong> = referans (IAP brüt × %{{ number_format($kdvPct, 1, ',', '.') }}
        @if($kdvPct > 0) → {{ $fmt($s['rates']['kdv_ref_on_iap_gross'] ?? 0) }}@endif);
        P&L’ye yazılmaz.
        <strong>Fatura KDV</strong> = manuel tek kayıtlı gider.
        Düello kesinti (bilgi): {{ number_format($s['duel_commission_info']['coins']) }} coin ≈ {{ $fmt($s['duel_commission_info']['try_equiv']) }}.
    </div>

    <div class="row g-3 fin-tables-row">
        <div class="col-xl-8">
            <div class="fin-card" id="fin-sales">
                <div class="hd">
                    <span>Paket satışları ({{ $iapSales->firstItem() ?? 0 }}–{{ $iapSales->lastItem() ?? 0 }} / {{ $iapSales->total() }})</span>
                    @if($iapSales->lastPage() > 1)
                        <div class="fin-pager">
                            @if($iapSales->onFirstPage())
                                <span class="is-disabled">‹</span>
                            @else
                                <a href="{{ $iapSales->previousPageUrl() }}">‹</a>
                            @endif
                            @foreach($iapSales->getUrlRange(max(1, $iapSales->currentPage() - 2), min($iapSales->lastPage(), $iapSales->currentPage() + 2)) as $page => $url)
                                @if($page == $iapSales->currentPage())
                                    <span class="is-on">{{ $page }}</span>
                                @else
                                    <a href="{{ $url }}">{{ $page }}</a>
                                @endif
                            @endforeach
                            @if($iapSales->hasMorePages())
                                <a href="{{ $iapSales->nextPageUrl() }}">›</a>
                            @else
                                <span class="is-disabled">›</span>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="bd p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 fin-sales-table">
                            <thead>
                                <tr>
                                    <th class="col-date">Tarih</th>
                                    <th class="col-type">Tür</th>
                                    <th class="col-pkg">Paket</th>
                                    <th class="col-num text-end">Brüt</th>
                                    <th class="col-num text-end">Kesinti</th>
                                    <th class="col-num text-end">Net</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($iapSales as $sale)
                                    <tr title="Mağaza %{{ number_format($sale['fee_pct'], 0) }} · u#{{ $sale['user_id'] }} · #{{ $sale['id'] }}">
                                        <td class="small text-nowrap">{{ tr_time($sale['date'], 'd.m.Y H:i') }}</td>
                                        <td><span class="badge bg-secondary">{{ $typeLabels[$sale['type']] ?? $sale['type'] }}</span></td>
                                        <td class="small">
                                            <div class="pkg-cell" title="{{ $sale['package'] }}{{ !empty($sale['package_detail']) ? ' · '.$sale['package_detail'] : '' }}">
                                                <strong style="font-weight:600">{{ $sale['package'] }}</strong>
                                                @if(!empty($sale['package_detail']))
                                                    <div class="text-muted" style="font-size:.72rem;line-height:1.3;margin-top:.1rem">{{ $sale['package_detail'] }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-end small text-nowrap">{{ $fmt($sale['gross']) }}</td>
                                        <td class="text-end small text-nowrap fin-neg">−{{ $fmt($sale['fee']) }}</td>
                                        <td class="text-end small text-nowrap fin-pos fw-semibold">{{ $fmt($sale['net']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">Bu dönemde paket satışı yok. “Tüm zamanlar”ı dene.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="fin-card">
                <div class="hd">Manuel hareketler (gider / gelir / reklam / KDV)</div>
                <div class="bd p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @forelse($recentEntries as $e)
                                    <tr>
                                        <td class="small text-nowrap">{{ tr_time($e->entry_date, 'd.m.Y') }}</td>
                                        <td class="small">
                                            @if($e->direction === 'income')
                                                <span class="badge bg-success">Gelir</span>
                                            @else
                                                <span class="badge bg-danger">Gider</span>
                                            @endif
                                            {{ $e->label ?: ($e->category?->name ?: $e->source) }}
                                            @if($e->direction === 'income')
                                                @php
                                                    $meta = is_array($e->meta) ? $e->meta : [];
                                                    $taxOn = array_key_exists('counts_for_tax', $meta) ? (bool) $meta['counts_for_tax'] : true;
                                                @endphp
                                                <span class="badge {{ $taxOn ? 'bg-warning text-dark' : 'bg-light text-muted' }}" style="font-size:.65rem">{{ $taxOn ? 'brüt/GV' : 'net' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end small text-nowrap {{ $e->direction === 'income' ? 'fin-pos' : 'fin-neg' }}">
                                            {{ $e->direction === 'income' ? '+' : '−' }}{{ $fmt($e->amount_try) }}
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @php
                                                $em = is_array($e->meta) ? $e->meta : [];
                                                $eTax = array_key_exists('counts_for_tax', $em) ? (bool) $em['counts_for_tax'] : ($e->direction === 'income' ? false : true);
                                            @endphp
                                            <button type="button" class="btn btn-sm btn-link p-0 me-1 btn-edit-entry"
                                                    data-bs-toggle="modal" data-bs-target="#finEntryEditModal"
                                                    data-id="{{ $e->id }}"
                                                    data-date="{{ $e->entry_date?->toDateString() }}"
                                                    data-amount="{{ number_format((float)$e->amount_try, 2, ',', '.') }}"
                                                    data-label="{{ $e->label }}"
                                                    data-note="{{ $e->note }}"
                                                    data-category="{{ $e->category_id }}"
                                                    data-source="{{ $e->source }}"
                                                    data-tax="{{ $eTax ? '1' : '0' }}">Düzenle</button>
                                            <form method="post" action="{{ route('admin.finance.entries.destroy', $e->id) }}" class="d-inline" onsubmit="return confirm('Silinsin mi?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-link text-danger p-0" type="submit">Sil</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-muted py-3">Henüz manuel kayıt yok.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="finEntryEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px">
            <form method="post" id="finEntryEditForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                <input type="hidden" name="range" value="{{ $range }}">
                <div class="modal-header">
                    <h5 class="modal-title">Kayıt düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Tarih</label>
                            <input type="date" name="entry_date" id="editEntryDate" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Tutar (₺)</label>
                            <input type="text" name="amount_try" id="editEntryAmount" class="form-control form-control-sm" data-fin-num="money" data-fin-suffix="₺" required>
                        </div>
                        <div class="col-12" id="editEntryCatWrap">
                            <label class="form-label small">Kategori</label>
                            <select name="category_id" id="editEntryCat" class="form-select form-select-sm">
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Vergi</label>
                            <select name="counts_for_tax" id="editEntryTax" class="form-select form-select-sm">
                                <option value="0">Net / vergi dışı</option>
                                <option value="1">Brüt / vergiye dahil</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Açıklama</label>
                            <input type="text" name="label" id="editEntryLabel" class="form-control form-control-sm" maxlength="200">
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Not</label>
                            <input type="text" name="note" id="editEntryNote" class="form-control form-control-sm" maxlength="2000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-sm btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('admin.finance._number-format')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    var editModal = document.getElementById('finEntryEditModal');
    var editForm = document.getElementById('finEntryEditForm');
    if (editModal && editForm) {
        var updateTpl = @json(url('admin/finance/entries/__ID__'));
        editModal.addEventListener('show.bs.modal', function (ev) {
            var btn = ev.relatedTarget;
            if (!btn || !btn.classList.contains('btn-edit-entry')) return;
            editForm.action = updateTpl.replace('__ID__', btn.getAttribute('data-id'));
            document.getElementById('editEntryDate').value = btn.getAttribute('data-date') || '';
            document.getElementById('editEntryAmount').value = btn.getAttribute('data-amount') || '';
            document.getElementById('editEntryLabel').value = btn.getAttribute('data-label') || '';
            document.getElementById('editEntryNote').value = btn.getAttribute('data-note') || '';
            document.getElementById('editEntryTax').value = btn.getAttribute('data-tax') || '0';
            var src = btn.getAttribute('data-source');
            var catWrap = document.getElementById('editEntryCatWrap');
            if (catWrap) catWrap.style.display = src === 'manual' ? '' : 'none';
            var cat = document.getElementById('editEntryCat');
            if (cat && btn.getAttribute('data-category')) cat.value = btn.getAttribute('data-category');
            if (window.FinNumber) window.FinNumber.init(editModal);
        });
    }
})();
</script>
<script>
(function () {
    if (location.hash === '#fin-sales') {
        var el = document.getElementById('fin-sales');
        if (el) {
            requestAnimationFrame(function () {
                el.scrollIntoView({ behavior: 'auto', block: 'start' });
            });
        }
    }

    var src = document.getElementById('finSource');
    var dir = document.getElementById('finDirection');
    var cat = document.getElementById('finCatWrap');
    var btn = document.getElementById('finSubmitBtn');
    var hint = document.getElementById('finEntryHint');
    var tabs = document.querySelectorAll('#finTabs button');
    var modeSel = document.getElementById('finAmountMode');
    var modeWrap = document.getElementById('finModeWrap');
    var fixedWrap = document.getElementById('finFixedWrap');
    var baseWrap = document.getElementById('finBaseWrap');
    var pctWrap = document.getElementById('finPctWrap');
    var previewWrap = document.getElementById('finPreviewWrap');
    var amountTry = document.getElementById('finAmountTry');
    var baseAmount = document.getElementById('finBaseAmount');
    var percentInp = document.getElementById('finPercent');
    var previewEl = document.getElementById('finAmountPreview');
    var defaultKdvPct = {{ json_encode((float) ($defaultKdvPct ?? 0)) }};
    var taxWrap = document.getElementById('finTaxWrap');
    var taxHint = document.getElementById('finTaxHint');
    var taxMode = document.getElementById('finTaxMode');
    var taxLabel = document.getElementById('finTaxLabel');
    var taxOpt0 = document.getElementById('finTaxOpt0');
    var taxOpt1 = document.getElementById('finTaxOpt1');
    var hints = {
        manual: 'Manuel gider. Varsayılan vergiye dahil (indirilebilir). Kilitli aya yazılamaz.',
        other_income: 'Manuel gelir. Varsayılan Net = GV’ye katılmaz. Brüt = vergi tabanına dahil.',
        ad_revenue: 'Reklam geliri. Net/Brüt seçin.',
        kdv: 'Fatura KDV. Vergiye dahil seçilebilir. Dönem KDV% ayarda ref veya P&L.'
    };
    function setTaxOptions(kind) {
        if (!taxOpt0 || !taxOpt1) return;
        if (kind === 'income') {
            if (taxLabel) taxLabel.textContent = 'Vergi durumu';
            taxOpt0.textContent = 'Net — GV hesabına katma';
            taxOpt1.textContent = 'Brüt — vergi tabanına dahil';
            if (taxMode) taxMode.value = '0';
        } else {
            if (taxLabel) taxLabel.textContent = 'Vergi indirimi';
            taxOpt0.textContent = 'Vergi dışı (matrahtan düşme)';
            taxOpt1.textContent = 'Vergiye dahil (indirilebilir)';
            if (taxMode) taxMode.value = '1';
        }
    }

    function parseLocalNum(raw) {
        if (window.finParseNumber) return window.finParseNumber(raw);
        var s = String(raw || '').trim().replace(/[\s\u00a0]/g, '').replace(/%/g, '');
        if (!s) return NaN;
        if (s.indexOf(',') >= 0) s = s.replace(/\./g, '').replace(',', '.');
        var n = parseFloat(s.replace(/[^0-9.\-]/g, ''));
        return isFinite(n) ? n : NaN;
    }

    function fmtMoney(n) {
        if (!isFinite(n)) return '0,00 ₺';
        return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    }

    function syncAmountMode() {
        var isPct = modeSel && modeSel.value === 'percent';
        if (fixedWrap) fixedWrap.style.display = isPct ? 'none' : '';
        if (baseWrap) baseWrap.style.display = isPct ? '' : 'none';
        if (pctWrap) pctWrap.style.display = isPct ? '' : 'none';
        if (previewWrap) previewWrap.style.display = isPct ? '' : 'none';
        if (amountTry) amountTry.required = !isPct;
        if (baseAmount) baseAmount.required = !!isPct;
        if (percentInp) percentInp.required = !!isPct;
        updatePctPreview();
    }

    function updatePctPreview() {
        if (!previewEl) return;
        var base = parseLocalNum(baseAmount && baseAmount.value);
        var pct = parseLocalNum(percentInp && percentInp.value);
        var calc = (isFinite(base) && isFinite(pct)) ? (base * pct / 100) : 0;
        previewEl.textContent = fmtMoney(calc);
        if (amountTry && modeSel && modeSel.value === 'percent') {
            amountTry.value = isFinite(calc) ? calc.toFixed(2).replace('.', ',') : '';
        }
    }

    if (modeSel) {
        modeSel.addEventListener('change', syncAmountMode);
    }
    [baseAmount, percentInp].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', updatePctPreview);
        el.addEventListener('blur', updatePctPreview);
    });

    function applyTab(tab) {
        tabs.forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-tab') === tab); });
        if (!src || !dir) return;
        src.value = tab;
        if (hint) hint.textContent = hints[tab] || '';
        if (tab === 'ad_revenue' || tab === 'other_income') {
            dir.value = 'income';
            if (cat) cat.style.display = 'none';
            if (modeWrap) modeWrap.style.display = tab === 'other_income' ? '' : 'none';
            if (tab === 'ad_revenue' && modeSel) modeSel.value = 'fixed';
            if (taxWrap) taxWrap.style.display = '';
            if (taxHint) taxHint.style.display = '';
            setTaxOptions('income');
            if (btn) btn.textContent = tab === 'ad_revenue' ? 'Reklam geliri kaydet' : 'Gelir kaydet';
        } else if (tab === 'kdv') {
            dir.value = 'expense';
            if (cat) cat.style.display = 'none';
            if (modeWrap) modeWrap.style.display = '';
            if (taxWrap) taxWrap.style.display = '';
            if (taxHint) taxHint.style.display = '';
            setTaxOptions('expense');
            if (percentInp && defaultKdvPct > 0 && (!percentInp.value || parseLocalNum(percentInp.value) === 0)) {
                percentInp.value = String(defaultKdvPct).replace('.', ',');
            }
            if (btn) btn.textContent = 'KDV kaydet';
        } else {
            dir.value = 'expense';
            if (cat) cat.style.display = '';
            if (modeWrap) modeWrap.style.display = '';
            if (taxWrap) taxWrap.style.display = '';
            if (taxHint) taxHint.style.display = '';
            setTaxOptions('expense');
            if (btn) btn.textContent = 'Gider kaydet';
        }
        syncAmountMode();
    }
    tabs.forEach(function (b) {
        b.addEventListener('click', function () { applyTab(b.getAttribute('data-tab')); });
    });
    applyTab('manual');

    var form = document.getElementById('finEntryForm');
    if (form) {
        form.addEventListener('submit', function () {
            if (modeSel && modeSel.value === 'percent') {
                updatePctPreview();
            }
        });
    }
    function closeAllDetails() {
        document.querySelectorAll('.fin-detail').forEach(function (el) { el.classList.remove('is-on'); });
        document.querySelectorAll('.fin-kpi, .fin-pack .item').forEach(function (el) { el.classList.remove('is-open'); });
    }
    document.querySelectorAll('.fin-kpi[data-detail]').forEach(function (btnEl) {
        btnEl.addEventListener('click', function () {
            var key = btnEl.getAttribute('data-detail');
            var panel = document.querySelector('.fin-detail[data-panel="' + key + '"]');
            var wasOpen = panel && panel.classList.contains('is-on');
            closeAllDetails();
            if (!wasOpen && panel) {
                panel.classList.add('is-on');
                btnEl.classList.add('is-open');
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });
    document.querySelectorAll('.fin-pack .item[data-pack]').forEach(function (btnEl) {
        btnEl.addEventListener('click', function () {
            var key = btnEl.getAttribute('data-pack');
            var panel = document.querySelector('.fin-detail[data-pack-panel="' + key + '"]');
            var wasOpen = panel && panel.classList.contains('is-on');
            closeAllDetails();
            if (!wasOpen && panel) {
                panel.classList.add('is-on');
                btnEl.classList.add('is-open');
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });

    if (!window.Chart) return;
    var money = function (v) {
        return (Number(v) || 0).toLocaleString('tr-TR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' ₺';
    };
    var moneyShort = function (v) {
        var n = Number(v) || 0;
        var abs = Math.abs(n);
        if (abs >= 1000000) return (n / 1000000).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + 'M ₺';
        if (abs >= 1000) return (n / 1000).toLocaleString('tr-TR', { maximumFractionDigits: 1 }) + 'B ₺';
        return money(n);
    };
    var finDefaults = {
        color: '#94a3b8',
        borderColor: 'rgba(148,163,184,.25)',
        font: { family: 'ui-sans-serif, system-ui, -apple-system, Segoe UI, sans-serif', size: 11 }
    };
    Chart.defaults.color = finDefaults.color;
    Chart.defaults.font.family = finDefaults.font.family;
    Chart.defaults.font.size = finDefaults.font.size;

    function gradientFill(ctx, colorTop, colorBottom) {
        var chart = ctx.chart;
        var area = chart.chartArea;
        if (!area) return colorTop;
        var g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
        g.addColorStop(0, colorTop);
        g.addColorStop(1, colorBottom);
        return g;
    }

    var daily = @json($s['daily']);
    var dailyCtx = document.getElementById('finChartDaily');
    if (dailyCtx) {
        var step = daily.length > 90 ? Math.ceil(daily.length / 60) : 1;
        var labels = [], income = [], expense = [], net = [];
        daily.forEach(function (d, i) {
            if (i % step !== 0 && i !== daily.length - 1) return;
            labels.push(d.date.slice(5).replace('-', '.'));
            income.push(d.income);
            expense.push(d.expense);
            net.push(d.net);
        });
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Gelir',
                        data: income,
                        borderColor: '#5eead4',
                        borderWidth: 1.75,
                        backgroundColor: function (c) { return gradientFill(c, 'rgba(94,234,212,.32)', 'rgba(94,234,212,0)'); },
                        tension: .35,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        order: 3
                    },
                    {
                        label: 'Gider',
                        data: expense,
                        borderColor: '#fb7185',
                        borderWidth: 1.75,
                        backgroundColor: function (c) { return gradientFill(c, 'rgba(251,113,133,.24)', 'rgba(251,113,133,0)'); },
                        tension: .35,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                        order: 2
                    },
                    {
                        label: 'Net',
                        data: net,
                        borderColor: '#fde68a',
                        borderWidth: 2.6,
                        backgroundColor: 'transparent',
                        tension: .25,
                        fill: false,
                        pointRadius: labels.length <= 16 ? 2.5 : 0,
                        pointHoverRadius: 4,
                        pointBackgroundColor: '#fde68a',
                        order: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#cbd5e1',
                            font: { size: 11, weight: '600' },
                            padding: 16
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,.94)',
                        titleColor: '#f8fafc',
                        bodyColor: '#e2e8f0',
                        borderColor: 'rgba(148,163,184,.35)',
                        borderWidth: 1,
                        padding: 10,
                        displayColors: true,
                        callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + money(c.parsed.y); } }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(148,163,184,.08)', drawBorder: false },
                        ticks: { maxTicksLimit: 10, color: '#94a3b8', font: { size: 10 } }
                    },
                    y: {
                        grid: { color: 'rgba(148,163,184,.12)', drawBorder: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10, family: 'ui-monospace, SFMono-Regular, Menlo, monospace' },
                            callback: function (v) { return moneyShort(v); }
                        }
                    }
                }
            }
        });
    }

    var mixCtx = document.getElementById('finChartMix');
    if (mixCtx) {
        var byType = @json($s['iap']['by_type'] ?? []);
        var mixLabels = [];
        var mixData = [];
        var mixColors = ['#5eead4', '#93c5fd', '#fde68a', '#d8b4fe', '#94a3b8'];
        var nameMap = { coin: 'Jeton', premium: 'Premium', joker: 'Joker', diamond: 'Elmas', other: 'Diğer' };
        var mixTotal = 0;
        Object.keys(byType).forEach(function (k) {
            if ((byType[k] || 0) <= 0) return;
            mixLabels.push(nameMap[k] || k);
            mixData.push(byType[k]);
            mixTotal += Number(byType[k]) || 0;
        });
        if (mixData.length === 0) {
            mixLabels = ['Veri yok'];
            mixData = [1];
            mixColors = ['#334155'];
            mixTotal = 0;
        }
        var centerEl = document.getElementById('finMixCenterVal');
        if (centerEl) centerEl.textContent = moneyShort(mixTotal);
        new Chart(mixCtx, {
            type: 'doughnut',
            data: {
                labels: mixLabels,
                datasets: [{
                    data: mixData,
                    backgroundColor: mixColors,
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 8,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            color: '#cbd5e1',
                            font: { size: 10, weight: '600' },
                            padding: 12
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,.94)',
                        borderColor: 'rgba(148,163,184,.35)',
                        borderWidth: 1,
                        callbacks: {
                            label: function (c) {
                                var sum = c.dataset.data.reduce(function (a, b) { return a + (Number(b) || 0); }, 0) || 1;
                                var pct = ((c.parsed / sum) * 100).toFixed(1);
                                return ' ' + c.label + ': ' + money(c.parsed) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    var barCtx = document.getElementById('finChartBars');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Uyg. içi net', 'Reklam', 'Diğer gelir', 'Ödül', 'Manuel'],
                datasets: [{
                    label: '₺',
                    data: [
                        {{ (float) ($s['iap']['net'] ?? 0) }},
                        {{ (float) ($s['ad_revenue'] ?? 0) }},
                        {{ (float) ($s['other_income'] ?? 0) }},
                        {{ (float) ($s['gift']['total'] ?? 0) }},
                        {{ (float) ($s['manual_expense'] ?? 0) }}
                    ],
                    backgroundColor: [
                        'rgba(94,234,212,.9)',
                        'rgba(125,211,252,.88)',
                        'rgba(253,230,138,.88)',
                        'rgba(251,113,133,.88)',
                        'rgba(251,146,60,.88)'
                    ],
                    borderRadius: 4,
                    borderSkipped: false,
                    maxBarThickness: 28,
                    barPercentage: 0.7,
                    categoryPercentage: 0.75
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,.94)',
                        borderColor: 'rgba(148,163,184,.35)',
                        borderWidth: 1,
                        callbacks: { label: function (c) { return ' ' + money(c.parsed.x); } }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(148,163,184,.12)', drawBorder: false },
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 10, family: 'ui-monospace, SFMono-Regular, Menlo, monospace' },
                            callback: function (v) { return moneyShort(v); }
                        }
                    },
                    y: {
                        grid: { display: false, drawBorder: false },
                        ticks: { color: '#cbd5e1', font: { size: 11, weight: '600' } }
                    }
                }
            }
        });
    }
})();
</script>
@endpush
