@extends('admin.layouts.app')

@section('title', 'Finans')

@push('styles')
<style>
.fin-wrap { max-width: 1400px; }
.fin-hero {
    background: linear-gradient(135deg, #0b1220 0%, #1a2744 55%, #243b5c 100%);
    border-radius: 14px; color: #fff; padding: 1.35rem 1.5rem; margin-bottom: 1rem;
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
.fin-chart { width: 100%; height: 260px; }
.fin-chart-sm { width: 100%; height: 220px; }
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
</style>
@endpush

@section('content')
@php
    $s = $summary;
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.') . ' ₺';
    $range = $range ?? 'all';
    $storePct = (float) ($s['active_rate']->store_fee_pct ?? 40);
    $taxPct = (float) ($s['income_tax_pct'] ?? 25);
    $agreedFrom = $agreedFrom ?? $from;
    $typeLabels = ['coin' => 'Jeton', 'premium' => 'Premium', 'joker' => 'Joker', 'diamond' => 'Elmas', 'other' => 'Diğer'];
@endphp
<div class="fin-wrap">
    <div class="fin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h3>Finans</h3>
            <p>Nakit bazlı gelir / gider · {{ tr_time($from, 'd.m.Y') }} – {{ tr_time($to, 'd.m.Y') }}
                · {{ (int) ($s['iap']['count'] ?? 0) }} paket satışı</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="post" action="{{ route('admin.finance.start-from-today') }}"
                  onsubmit="return confirm('Finans özeti bugün 00:00\'dan itibaren mi başlasın? Oranlar korunur, önceki dönem dün kapanır.');">
                @csrf
                <button type="submit" class="fin-link" style="cursor:pointer;background:rgba(255,255,255,.12)">Kararlaştırılan tarihi bugün yap</button>
            </form>
            <a href="{{ route('admin.finance.settings') }}" class="fin-link">Oranlar &amp; kategoriler</a>
        </div>
    </div>

    <div class="fin-entry">
        <div class="fin-entry-tabs" id="finTabs">
            <button type="button" class="is-on" data-tab="manual">Gider ekle</button>
            <button type="button" data-tab="ad_revenue">Reklam geliri</button>
            <button type="button" data-tab="kdv">KDV (fatura)</button>
        </div>
        <p class="fin-entry-hint" id="finEntryHint">
            Ödül / hediye burada yok — talep onaylanınca otomatik gider yazılır. Buradan reklam bütçesi, sunucu, ofis vb. manuel gider girin.
        </p>
        <form method="post" action="{{ route('admin.finance.entries.store') }}" id="finEntryForm">
            @csrf
            <input type="hidden" name="from" value="{{ $from->toDateString() }}">
            <input type="hidden" name="to" value="{{ $to->toDateString() }}">
            <input type="hidden" name="range" value="{{ $range }}">
            <input type="hidden" name="source" id="finSource" value="manual">
            <input type="hidden" name="direction" id="finDirection" value="expense">
            <div class="fin-entry-grid">
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
                <div>
                    <label>Tutar (₺)</label>
                    <input type="text" inputmode="decimal" name="amount_try" class="form-control form-control-sm"
                           data-fin-num="money" data-fin-suffix="₺" required placeholder="0,00">
                </div>
                <div class="fin-span2">
                    <label>Açıklama</label>
                    <input type="text" name="label" class="form-control form-control-sm" maxlength="200" placeholder="örn. Meta reklam / Multinet yükleme">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-sm btn-dark w-100" id="finSubmitBtn">Gider kaydet</button>
                </div>
            </div>
            <div class="mt-2">
                <input type="text" name="note" class="form-control form-control-sm" maxlength="2000" placeholder="Not (opsiyonel)">
            </div>
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
                <div class="s">Uygulama içi satış − mağaza payı + reklam</div>
            </div>
            <div class="hint">Detay için tıkla</div>
        </button>
        <button type="button" class="fin-kpi" data-detail="expense">
            <div>
                <div class="k">Gider</div>
                <div class="v fin-neg">{{ $fmt($s['expense_total']) }}</div>
                <div class="s">Ödül {{ $fmt($s['gift']['total']) }} + manuel {{ $fmt($s['manual_expense']) }}</div>
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
                <div class="s">Gelir vergisi %{{ rtrim(rtrim(number_format($taxPct, 2, '.', ''), '0'), '.') }} (−{{ $fmt($s['income_tax']) }})</div>
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
        <div class="fin-row"><span>Reklam geliri (manuel)</span><span class="amt fin-pos">{{ $fmt($s['ad_revenue']) }}</span></div>
        <div class="fin-row"><strong>Toplam gelir</strong><strong class="amt fin-pos">{{ $fmt($s['income_total']) }}</strong></div>
        <div class="formula">Formül: (uygulama içi brüt × (1 − mağaza%/100)) + reklam geliri. Her satış kendi tarihindeki oran dönemiyle hesaplanır.</div>
    </div>
    <div id="finDetailExpense" class="fin-detail" data-panel="expense">
        <h5>Gider nasıl hesaplanır?</h5>
        <div class="fin-row"><span>Ödül talepleri ({{ $s['gift']['count'] }}) — otomatik</span><span class="amt fin-neg">−{{ $fmt($s['gift']['total']) }}</span></div>
        @foreach($s['gift']['by_method'] as $method => $amt)
            <div class="fin-row">
                <span class="fin-muted">· {{ \App\Models\FinanceLedgerEntry::PAYOUT_METHODS[$method] ?? $method }}</span>
                <span class="amt">{{ $fmt($amt) }}</span>
            </div>
        @endforeach
        @foreach($s['manual_by_category'] as $row)
            <div class="fin-row"><span>{{ $row['category'] }}</span><span class="amt fin-neg">−{{ $fmt($row['total']) }}</span></div>
        @endforeach
        @if(empty($s['manual_by_category']))
            <div class="fin-row"><span class="fin-muted">Manuel gider</span><span class="amt">{{ $fmt($s['manual_expense']) }}</span></div>
        @endif
        <div class="fin-row"><strong>Toplam gider</strong><strong class="amt fin-neg">−{{ $fmt($s['expense_total']) }}</strong></div>
        <div class="formula">Ödül: talep onayında ledger’a yazılır (buradan tekrar girilmez). Manuel: üst formdan eklenen gider + KDV.</div>
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
        <div class="fin-row"><span>Vergi öncesi</span><span class="amt">{{ $fmt($s['pre_tax_profit']) }}</span></div>
        <div class="fin-row"><span>Gelir vergisi (%{{ number_format($taxPct, 1, ',', '.') }})</span><span class="amt fin-neg">−{{ $fmt($s['income_tax']) }}</span></div>
        <div class="fin-row"><strong>Dip kar</strong><strong class="amt {{ $s['final_profit'] >= 0 ? 'fin-pos' : 'fin-neg' }}">{{ $fmt($s['final_profit']) }}</strong></div>
        <div class="formula">max(0, vergi öncesi) × GV% = vergi. Dip kar = vergi öncesi − vergi. KDV otomatik 0; fatura KDV’si ayrıca gider.</div>
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

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="fin-card">
                <div class="hd">
                    <div>
                        <span>Günlük net</span>
                        <span class="fin-sub">Gelir / gider / net (₺)</span>
                    </div>
                </div>
                <div class="bd">
                    <canvas id="finChartDaily" class="fin-chart" height="260"></canvas>
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
                    <canvas id="finChartMix" class="fin-chart-sm" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">
                    <div>
                        <span>Gelir vs gider</span>
                        <span class="fin-sub">Dönem toplamı (₺)</span>
                    </div>
                </div>
                <div class="bd">
                    <canvas id="finChartBars" class="fin-chart-sm" height="220"></canvas>
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
                    <div class="fin-row"><strong>Toplam gelir</strong><strong class="amt fin-pos">{{ $fmt($s['income_total']) }}</strong></div>
                    <hr class="my-2">
                    <div class="fin-row"><span>Ödül ({{ $s['gift']['count'] }})</span><span class="amt fin-neg">−{{ $fmt($s['gift']['total']) }}</span></div>
                    <div class="fin-row"><span>Manuel gider</span><span class="amt fin-neg">−{{ $fmt($s['manual_expense']) }}</span></div>
                    <div class="fin-row"><strong>Toplam gider</strong><strong class="amt fin-neg">−{{ $fmt($s['expense_total']) }}</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="fin-info mb-3">
        Düello kesinti (bilgi): <strong>{{ number_format($s['duel_commission_info']['coins']) }} coin</strong>
        ≈ {{ $fmt($s['duel_commission_info']['try_equiv']) }} — nakit P&L dışı.
    </div>

    <div class="row g-3">
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
                <div class="hd">Manuel hareketler</div>
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
                                        </td>
                                        <td class="text-end small text-nowrap {{ $e->direction === 'income' ? 'fin-pos' : 'fin-neg' }}">
                                            {{ $e->direction === 'income' ? '+' : '−' }}{{ $fmt($e->amount_try) }}
                                        </td>
                                        <td class="text-end">
                                            <form method="post" action="{{ route('admin.finance.entries.destroy', $e->id) }}" onsubmit="return confirm('Silinsin mi?');">
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
@endsection

@push('scripts')
@include('admin.finance._number-format')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
    var hints = {
        manual: 'Ödül / hediye burada yok — talep onaylanınca otomatik gider yazılır. Buradan reklam bütçesi, sunucu, ofis vb. manuel gider girin.',
        ad_revenue: 'Mağaza / reklam ağından gelen nakit geliri elle girin (uygulama içi satışlar otomatik gelir).',
        kdv: 'Faturadaki KDV tutarını gider olarak kaydedin (otomatik KDV hesaplanmaz).'
    };

    function applyTab(tab) {
        tabs.forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-tab') === tab); });
        if (!src || !dir) return;
        src.value = tab;
        if (hint) hint.textContent = hints[tab] || '';
        if (tab === 'ad_revenue') {
            dir.value = 'income';
            if (cat) cat.style.display = 'none';
            if (btn) btn.textContent = 'Gelir kaydet';
        } else if (tab === 'kdv') {
            dir.value = 'expense';
            if (cat) cat.style.display = 'none';
            if (btn) btn.textContent = 'KDV kaydet';
        } else {
            dir.value = 'expense';
            if (cat) cat.style.display = '';
            if (btn) btn.textContent = 'Gider kaydet';
        }
    }
    tabs.forEach(function (b) {
        b.addEventListener('click', function () { applyTab(b.getAttribute('data-tab')); });
    });
    applyTab('manual');

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
                    { label: 'Gelir', data: income, borderColor: '#15803d', backgroundColor: 'rgba(21,128,61,.12)', tension: .3, fill: true, pointRadius: labels.length <= 14 ? 3 : 0 },
                    { label: 'Gider', data: expense, borderColor: '#b91c1c', backgroundColor: 'rgba(185,28,28,.08)', tension: .3, fill: true, pointRadius: labels.length <= 14 ? 3 : 0 },
                    { label: 'Net', data: net, borderColor: '#1d4ed8', borderWidth: 2, tension: .3, fill: false, pointRadius: labels.length <= 14 ? 3 : 0 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + money(c.parsed.y); } } }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 12, font: { size: 10 } } },
                    y: {
                        ticks: { font: { size: 10 }, callback: function (v) { return money(v); } },
                        grid: { color: '#f1f5f9' }
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
        var mixColors = ['#0f766e', '#1d4ed8', '#a16207', '#7c3aed', '#64748b'];
        var nameMap = { coin: 'Jeton', premium: 'Premium', joker: 'Joker', diamond: 'Elmas', other: 'Diğer' };
        Object.keys(byType).forEach(function (k) {
            if ((byType[k] || 0) <= 0) return;
            mixLabels.push(nameMap[k] || k);
            mixData.push(byType[k]);
        });
        if (mixData.length === 0) {
            mixLabels = ['Veri yok'];
            mixData = [1];
            mixColors = ['#e2e8f0'];
        }
        new Chart(mixCtx, {
            type: 'doughnut',
            data: {
                labels: mixLabels,
                datasets: [{ data: mixData, backgroundColor: mixColors, borderWidth: 0 }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
                    tooltip: { callbacks: { label: function (c) { return c.label + ': ' + money(c.parsed); } } }
                }
            }
        });
    }

    var barCtx = document.getElementById('finChartBars');
    if (barCtx) {
        new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: ['Uyg. içi net', 'Reklam', 'Ödül', 'Manuel'],
                datasets: [{
                    label: '₺',
                    data: [
                        {{ (float) ($s['iap']['net'] ?? 0) }},
                        {{ (float) ($s['ad_revenue'] ?? 0) }},
                        {{ (float) ($s['gift']['total'] ?? 0) }},
                        {{ (float) ($s['manual_expense'] ?? 0) }}
                    ],
                    backgroundColor: ['#15803d', '#0f766e', '#b91c1c', '#ea580c'],
                    borderRadius: 6,
                    maxBarThickness: 42
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (c) { return money(c.parsed.y); } } }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        ticks: { font: { size: 10 }, callback: function (v) { return money(v); } },
                        grid: { color: '#f1f5f9' }
                    }
                }
            }
        });
    }
})();
</script>
@endpush
