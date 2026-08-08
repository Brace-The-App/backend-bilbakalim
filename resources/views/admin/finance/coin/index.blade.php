@extends('admin.layouts.app')

@section('title', 'Finans · Coin')

@push('styles')
<style>
.fin-coin-wrap { max-width: 1400px; position: relative; }
.fin-coin-hero {
    background: linear-gradient(135deg, #0c1a14 0%, #14352a 55%, #1a4d3a 100%);
    border-radius: 14px; color: #fff; padding: 1.35rem 1.5rem; margin-bottom: 1rem;
    position: relative; overflow: visible;
}
.fin-coin-hero-main { display:flex; align-items:center; gap:.85rem; min-width:0; }
.fin-coin-hero-icon { width:28px; height:28px; flex-shrink:0; object-fit:contain; filter: drop-shadow(0 1px 2px rgba(0,0,0,.25)); }
.fin-coin-hero h3 { color: #fff !important; margin: 0 0 .25rem; font-weight: 650; }
.fin-coin-hero p { margin: 0; color: rgba(255,255,255,.82); font-size: .95rem; }
.fin-coin-hero a.fin-link-hero {
    color: #fff !important; border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.08);
    padding: .4rem .85rem; border-radius: 8px; text-decoration: none !important; font-size: .85rem; font-weight: 600;
}
.fin-coin-hero a.fin-link-hero:hover { background: rgba(255,255,255,.16); }
.fin-coin-spin-wrap {
    width: 73px; height: 73px; flex-shrink: 0;
    perspective: 280px; display:flex; align-items:center; justify-content:center;
    cursor: pointer; z-index: 3;
    transition: transform .28s ease;
    transform-origin: center center;
    filter: drop-shadow(0 4px 10px rgba(0,0,0,.28));
}
.fin-coin-spin-wrap:hover {
    transform: scale(2);
}
.fin-coin-spin {
    position: relative;
    width: 73px; height: 73px;
    transform-style: preserve-3d;
    animation: fin-coin-y-spin 2.4s ease-in-out infinite;
    pointer-events: none;
}
.fin-coin-spin-face {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}
.fin-coin-spin-face.is-back {
    transform: rotateY(180deg);
}
@keyframes fin-coin-y-spin {
    0%   { transform: rotateY(0deg); }
    50%  { transform: rotateY(180deg); }
    100% { transform: rotateY(0deg); }
}
.fin-presets { display:flex; flex-wrap:wrap; gap:.4rem; }
.fin-presets a {
    font-size:.8rem; padding:.4rem .75rem; border-radius:999px; border:1px solid #e2e8f0;
    color:#334155; text-decoration:none; background:#fff; font-weight:600;
}
.fin-presets a.is-on { background:#0f172a; color:#fff; border-color:#0f172a; }
.fin-alert {
    display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem;
    background:#fffbeb; border:1px solid #fcd34d; border-radius:10px; padding:.7rem 1rem;
    font-size:.88rem; color:#92400e; margin-bottom:1rem;
}
.fin-alert-left { display:flex; align-items:center; gap:.55rem; min-width:0; }
.fin-alert-ico { width:20px; height:20px; flex-shrink:0; }
.fin-alert a { color:#92400e; font-weight:700; text-decoration:underline; }
.fin-alert.is-ok {
    background:#f0fdf4; border-color:#86efac; color:#166534;
}
.fin-alert.is-ok a { color:#166534; }
.fin-kpis { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: .75rem; }
@media (max-width: 768px) { .fin-kpis { grid-template-columns: 1fr; } }
.fin-kpi {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.15rem 1.25rem;
    min-height: 7rem; display: flex; flex-direction: column; justify-content: space-between;
}
.fin-kpi-top { display:flex; align-items:flex-start; justify-content:space-between; gap:.5rem; }
.fin-kpi .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
.fin-kpi .v { font-size: 1.55rem; font-weight: 700; margin-top: .4rem; font-variant-numeric: tabular-nums; }
.fin-kpi .s { font-size: .75rem; color: #94a3b8; margin-top: .35rem; line-height: 1.35; }
.fin-disk { width:22px; height:22px; flex-shrink:0; object-fit:contain; }
.fin-disk.is-pos { filter: hue-rotate(75deg) saturate(1.1); }
.fin-disk.is-neg { filter: hue-rotate(-45deg) saturate(1.2) brightness(.95); }
.fin-disk.is-net-pos { filter: hue-rotate(75deg) saturate(1.05); }
.fin-disk.is-net-neg { filter: hue-rotate(-45deg) saturate(1.15); }
.fin-story {
    background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:.85rem 1.1rem 1rem;
    margin-top:.75rem; margin-bottom:1rem;
}
.fin-story-top { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:.75rem; margin-bottom:.55rem; }
.fin-story-label { font-size:.78rem; font-weight:650; color:#475569; }
.fin-story-meta { font-size:.75rem; color:#94a3b8; }
.fin-bar { height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; display: flex; }
.fin-bar > span { display: block; height: 100%; }
.fin-bar .inc { background: #15803d; }
.fin-bar .exp { background: #b91c1c; }
.fin-spark { width:100%; height:44px; display:block; margin-top:.65rem; }
.fin-pos { color: #15803d; }
.fin-neg { color: #b91c1c; }
.fin-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1rem; overflow: hidden; height: 100%; }
.fin-card .hd { padding: .85rem 1.1rem; border-bottom: 1px solid #f1f5f9; font-weight: 650; font-size: .95rem; }
.fin-card .bd { padding: 1.1rem; }
.fin-card--muted { background:#f8fafc; border-color:#e2e8f0; }
.fin-card--muted .hd { background:#f1f5f9; color:#64748b; border-bottom-color:#e2e8f0; }
.fin-row { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px dashed #f1f5f9; font-size: .92rem; }
.fin-row:last-child { border-bottom: 0; }
.fin-info { background: #f0fdf4; border: 1px dashed #86efac; border-radius: 10px; padding: .75rem 1rem; font-size: .85rem; color: #166534; margin-bottom: 1rem; }
.fin-muted { color: #64748b; font-size: .82rem; }
.fin-note {
    margin-top:.85rem; padding:.7rem .85rem; border-radius:8px; background:#f8fafc;
    border:1px dashed #cbd5e1; font-size:.82rem; color:#475569;
}
.fin-note strong { color:#0f172a; }
.fin-pool-hero {
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;
    padding: .85rem 1rem; margin-bottom: .85rem;
    position: relative; overflow: hidden;
}
.fin-pool-stack {
    position:absolute; right:8px; top:50%; transform:translateY(-50%);
    width:54px; height:54px; opacity:.35; pointer-events:none;
}
.fin-pool-stack img { width:100%; height:100%; object-fit:contain; filter: drop-shadow(0 2px 4px rgba(0,0,0,.15)); }
.fin-pool-hero .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #166534; }
.fin-pool-hero .v { font-size: 1.35rem; font-weight: 700; margin-top: .25rem; font-variant-numeric: tabular-nums; color: #14532d; position:relative; z-index:1; }
.fin-pool-hero .s { font-size: .78rem; color: #166534; margin-top: .25rem; opacity:.85; position:relative; z-index:1; }
.fin-empty {
    display:flex; align-items:flex-start; gap:.65rem; padding:.55rem 0; color:#64748b; font-size:.86rem;
}
.fin-empty i { margin-top:.1rem; opacity:.7; }
.fin-chips { display:flex; flex-direction:column; gap:.45rem; margin-top:.35rem; }
.fin-chip {
    display:flex; flex-wrap:wrap; align-items:center; gap:.35rem .55rem;
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:999px;
    padding:.35rem .75rem; font-size:.8rem; color:#0f172a;
}
.fin-card--muted .fin-chip { background:#fff; }
.fin-chip .sep { color:#cbd5e1; }
.fin-delta-pos { color: #15803d; font-weight: 600; }
.fin-delta-neg { color: #b91c1c; font-weight: 600; }
.fin-viz-row { margin-bottom: 1rem; }
.fin-flow {
    display:flex; flex-wrap:wrap; align-items:stretch; gap:.45rem; justify-content:space-between;
}
.fin-flow-node {
    flex:1 1 110px; min-width:108px; background:#fff; border:1px solid #e2e8f0; border-radius:12px;
    padding:.75rem .8rem; position:relative;
}
.fin-flow-node .t { font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; color:#64748b; font-weight:700; }
.fin-flow-node .n { font-size:1.05rem; font-weight:700; margin-top:.3rem; font-variant-numeric:tabular-nums; color:#0f172a; }
.fin-flow-node .h { font-size:.72rem; color:#94a3b8; margin-top:.2rem; line-height:1.3; }
.fin-flow-node.is-out { border-color:#fecaca; background:#fff7f7; }
.fin-flow-node.is-out .n { color:#b91c1c; }
.fin-flow-node.is-in { border-color:#bbf7d0; background:#f0fdf4; }
.fin-flow-node.is-in .n { color:#15803d; }
.fin-flow-arrow {
    align-self:center; color:#94a3b8; font-weight:700; font-size:1.1rem; flex:0 0 auto; padding:0 .1rem;
}
@media (max-width: 768px) {
    .fin-flow-arrow { display:none; }
}
.fin-gauge-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:180px; }
.fin-gauge-svg { width:100%; max-width:220px; height:auto; }
.fin-gauge-cap { font-size:1.6rem; font-weight:750; font-variant-numeric:tabular-nums; color:#14532d; margin-top:-.35rem; }
.fin-gauge-sub { font-size:.78rem; color:#64748b; text-align:center; margin-top:.15rem; }
.fin-heat {
    display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); gap:4px;
}
.fin-heat-cell {
    aspect-ratio:1; border-radius:4px; background:#e2e8f0;
    position:relative; cursor:default;
}
.fin-heat-cell[data-tip]:hover::after {
    content: attr(data-tip);
    position:absolute; left:50%; bottom:calc(100% + 6px); transform:translateX(-50%);
    background:#0f172a; color:#fff; font-size:.68rem; padding:.25rem .4rem; border-radius:4px;
    white-space:nowrap; z-index:5; pointer-events:none;
}
.fin-heat-legend { display:flex; align-items:center; gap:.35rem; margin-top:.65rem; font-size:.72rem; color:#64748b; }
.fin-heat-legend span.sw { width:12px; height:12px; border-radius:3px; display:inline-block; }
</style>
@endpush

@section('content')
{{-- Tek SVG sprite: coin disk varyantları --}}
<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;overflow:hidden" aria-hidden="true">
    <symbol id="fin-gift" viewBox="0 0 24 24">
        <rect x="4" y="10" width="16" height="10" rx="1.5" fill="currentColor"/>
        <path d="M3 10h18v-2.5A1.5 1.5 0 0 0 19.5 6H4.5A1.5 1.5 0 0 0 3 7.5V10z" fill="currentColor" opacity=".85"/>
        <rect x="11" y="6" width="2" height="14" fill="#fff" fill-opacity=".35"/>
        <path d="M12 6c-2.2 0-3.5-1.4-3.5-2.6S9.6 1.2 12 3.2c2.4-2 3.5-.6 3.5.2S14.2 6 12 6z" fill="currentColor"/>
    </symbol>
    <symbol id="fin-check" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" fill="currentColor"/>
        <path d="M7.5 12.2l2.8 2.8 6.2-6.2" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
</svg>

@php
    $coinImg = asset('assets/images/finance/coin.png') . '?v=20260808b';
    $s = $summary;
    $p = $s['pools'];
    $alert = $s['gift_alert'] ?? ['pending_count' => 0, 'min_coins' => 250];
    $dailyNet = $s['daily_net'] ?? [];
    $pressure = $s['gift_pressure'] ?? [
        'human_duel' => (int) ($p['human_duel'] ?? 0),
        'min_coins' => (int) ($alert['min_coins'] ?? 250),
        'capacity' => 0,
        'toward_next' => 0,
        'progress_pct' => 0,
    ];
    $heat = $s['heat_calendar'] ?? $dailyNet;
    $pendingOpen = (int) ($alert['pending_count'] ?? 0);
    $fmt = fn ($n) => number_format((int) $n, 0, ',', '.') . ' coin';
    $deltaFmt = function (int $n) {
        $sign = $n > 0 ? '+' : '';
        return $sign . number_format($n, 0, ',', '.');
    };
    $renderMatchChips = function (array $matches, string $emptyMsg, bool $showCommission = true) use ($deltaFmt) {
        if ($matches === []) {
            echo '<div class="fin-empty"><i data-feather="inbox" style="width:16px;height:16px"></i><span>'.e($emptyMsg).'</span></div>';
            return;
        }
        echo '<div class="fin-chips">';
        foreach ($matches as $m) {
            $cCls = $m['challenger_delta'] >= 0 ? 'fin-delta-pos' : 'fin-delta-neg';
            $oCls = $m['opponent_delta'] >= 0 ? 'fin-delta-pos' : 'fin-delta-neg';
            echo '<div class="fin-chip">';
            echo '<span>'.e($m['challenger']).'</span>';
            echo '<span class="'.$cCls.'">'.$deltaFmt((int)$m['challenger_delta']).'</span>';
            echo '<span class="sep">·</span>';
            echo '<span>'.e($m['opponent']).'</span>';
            echo '<span class="'.$oCls.'">'.$deltaFmt((int)$m['opponent_delta']).'</span>';
            if ($showCommission && (int)($m['commission'] ?? 0) > 0) {
                echo '<span class="sep">·</span>';
                echo '<span class="fin-delta-pos">kesinti +'.number_format((int)$m['commission'], 0, ',', '.').'</span>';
            }
            echo '</div>';
        }
        echo '</div>';
    };
    $sparkMax = 1;
    foreach ($dailyNet as $pt) {
        $sparkMax = max($sparkMax, abs((int) ($pt['net'] ?? 0)));
    }
@endphp
<div class="fin-coin-wrap">
    <div class="fin-coin-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="fin-coin-hero-main">
            <img class="fin-coin-hero-icon" src="{{ $coinImg }}" alt="" width="28" height="28">
            <div>
                <h3>Finans · Coin</h3>
                <p>Jeton ekonomisi · {{ tr_time($from, 'd.m.Y') }} – {{ tr_time($to, 'd.m.Y') }}</p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="fin-coin-spin-wrap" title="Jeton">
                <div class="fin-coin-spin" aria-hidden="true">
                    <img class="fin-coin-spin-face is-front" src="{{ $coinImg }}" alt="" width="73" height="73">
                    <img class="fin-coin-spin-face is-back" src="{{ $coinImg }}" alt="" width="73" height="73">
                </div>
            </div>
            <a class="fin-link-hero" href="{{ route('admin.finance.index') }}">TL (nakit) finans</a>
        </div>
    </div>

    <div class="fin-alert {{ $pendingOpen > 0 ? '' : 'is-ok' }}">
        <div class="fin-alert-left">
            @if($pendingOpen > 0)
                <svg class="fin-alert-ico" style="color:#d97706" aria-hidden="true"><use href="#fin-gift"></use></svg>
                <div>
                    <strong>{{ $pendingOpen }}</strong> talep beklemede
                    · eşik <strong>{{ number_format((int) $alert['min_coins'], 0, ',', '.') }}</strong> düello
                </div>
            @else
                <svg class="fin-alert-ico" style="color:#16a34a" aria-hidden="true"><use href="#fin-check"></use></svg>
                <div>
                    Bekleyen hediye talebi yok
                    · eşik <strong>{{ number_format((int) $alert['min_coins'], 0, ',', '.') }}</strong> düello
                </div>
            @endif
        </div>
        <a href="{{ route('admin.reward-requests.index') }}">Ödül paneli →</a>
    </div>

    <div class="fin-info">
        Rakamlar seçili tarihe göre hesaplanır. Havuz bot hariç.
        <strong>Düello kesintisi</strong> uygulamaya kalan %10’dur (kazananın cüzdanından).
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="fin-presets">
            <a href="{{ route('admin.finance.coin.index', ['range' => 'agreed']) }}" class="{{ $range === 'agreed' ? 'is-on' : '' }}">Kararlaştırılan Tarihten itibaren</a>
            <a href="{{ route('admin.finance.coin.index', ['range' => 'all']) }}" class="{{ $range === 'all' ? 'is-on' : '' }}">Tüm zamanlar</a>
            <a href="{{ route('admin.finance.coin.index', ['range' => 'today']) }}" class="{{ $range === 'today' ? 'is-on' : '' }}">Sadece bugün</a>
            <a href="{{ route('admin.finance.coin.index', ['range' => 'month']) }}" class="{{ $range === 'month' ? 'is-on' : '' }}">Bu ay</a>
            <a href="{{ route('admin.finance.coin.index', ['range' => 'last_month']) }}" class="{{ $range === 'last_month' ? 'is-on' : '' }}">Geçen ay</a>
            <a href="{{ route('admin.finance.coin.index', ['range' => '90d']) }}" class="{{ $range === '90d' ? 'is-on' : '' }}">Son 90 gün</a>
        </div>
        <form method="get" class="d-flex flex-wrap gap-2 align-items-end">
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm">
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-dark" type="submit">Tarih uygula</button>
        </form>
    </div>

    <div class="fin-kpis">
        <div class="fin-kpi">
            <div class="fin-kpi-top">
                <div>
                    <div class="k">Gelir</div>
                    <div class="v fin-pos">{{ $fmt($s['income']['total']) }}</div>
                </div>
                <img class="fin-disk is-pos" src="{{ $coinImg }}" alt="" width="22" height="22">
            </div>
            <div class="s">Paket {{ $fmt($s['income']['iap']) }} + kesinti {{ $fmt($s['income']['commission']) }}</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-top">
                <div>
                    <div class="k">Gider</div>
                    <div class="v fin-neg">{{ $fmt($s['expense']['total']) }}</div>
                </div>
                <img class="fin-disk is-neg" src="{{ $coinImg }}" alt="" width="22" height="22">
            </div>
            <div class="s">Hediye talep düşümü</div>
        </div>
        <div class="fin-kpi">
            <div class="fin-kpi-top">
                <div>
                    <div class="k">Net</div>
                    <div class="v {{ $s['net'] >= 0 ? 'fin-pos' : 'fin-neg' }}">{{ $fmt($s['net']) }}</div>
                </div>
                <img class="fin-disk {{ $s['net'] >= 0 ? 'is-net-pos' : 'is-net-neg' }}" src="{{ $coinImg }}" alt="" width="22" height="22">
            </div>
            <div class="s">Gelir {{ $s['balance_pct']['income'] }}% · gider {{ $s['balance_pct']['expense'] }}%</div>
        </div>
    </div>

    <div class="fin-story">
        <div class="fin-story-top">
            <div class="fin-story-label">Denge · gelir / gider</div>
            <div class="fin-story-meta">
                Gelir %{{ number_format($s['balance_pct']['income'], 1, ',', '.') }}
                · gider %{{ number_format($s['balance_pct']['expense'], 1, ',', '.') }}
                · paket %{{ number_format($s['balance_pct']['iap_of_income'], 1, ',', '.') }}
                · kesinti %{{ number_format($s['balance_pct']['commission_of_income'], 1, ',', '.') }}
            </div>
        </div>
        <div class="fin-bar">
            <span class="inc" style="width: {{ $s['balance_pct']['income'] }}%"></span>
            <span class="exp" style="width: {{ $s['balance_pct']['expense'] }}%"></span>
        </div>
        @if(count($dailyNet) > 1)
            @php
                $w = 240; $h = 44; $pad = 2;
                $n = count($dailyNet);
                $points = [];
                foreach ($dailyNet as $i => $pt) {
                    $x = $pad + ($n <= 1 ? 0 : ($i / ($n - 1)) * ($w - 2 * $pad));
                    $y = $h / 2 - (((int) $pt['net']) / $sparkMax) * (($h / 2) - $pad);
                    $points[] = round($x, 1) . ',' . round($y, 1);
                }
                $poly = implode(' ', $points);
            @endphp
            <svg class="fin-spark" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" aria-label="Günlük net coin">
                <line x1="0" y1="{{ $h/2 }}" x2="{{ $w }}" y2="{{ $h/2 }}" stroke="#e2e8f0" stroke-width="1"/>
                <polyline fill="none" stroke="#15803d" stroke-width="2" points="{{ $poly }}"/>
            </svg>
            <div class="fin-story-meta mt-1">Günlük net (son {{ count($dailyNet) }} gün)</div>
        @endif
    </div>

    @php
        $heatMax = 1;
        foreach ($heat as $hp) {
            $heatMax = max($heatMax, abs((int) ($hp['net'] ?? 0)));
        }
        $prog = max(0, min(100, (float) ($pressure['progress_pct'] ?? 0)));
        // Semicircle gauge: path from left to right, stroke-dashoffset
        $gaugeLen = 126; // approx π*r for r≈40
        $gaugeOffset = $gaugeLen * (1 - $prog / 100);
        $needNext = max(0, (int) $pressure['min_coins'] - (int) $pressure['toward_next']);
    @endphp

    <div class="fin-card mb-3">
        <div class="hd">Jeton akışı</div>
        <div class="bd">
            <div class="fin-flow">
                <div class="fin-flow-node is-in">
                    <div class="t">Paket</div>
                    <div class="n">{{ number_format((int) $s['income']['iap'], 0, ',', '.') }}</div>
                    <div class="h">dönem IAP girişi</div>
                </div>
                <div class="fin-flow-arrow">→</div>
                <div class="fin-flow-node">
                    <div class="t">Cüzdan</div>
                    <div class="n">{{ number_format((int) $p['human_wallet'], 0, ',', '.') }}</div>
                    <div class="h">insan canlı bakiye</div>
                </div>
                <div class="fin-flow-arrow">→</div>
                <div class="fin-flow-node">
                    <div class="t">Düello</div>
                    <div class="n">{{ number_format((int) $p['human_duel'], 0, ',', '.') }}</div>
                    <div class="h">hediye havuzu</div>
                </div>
                <div class="fin-flow-arrow">→</div>
                <div class="fin-flow-node is-in">
                    <div class="t">Kesinti</div>
                    <div class="n">{{ number_format((int) $s['income']['commission'], 0, ',', '.') }}</div>
                    <div class="h">uygulamaya kalan %10</div>
                </div>
                <div class="fin-flow-arrow">→</div>
                <div class="fin-flow-node is-out">
                    <div class="t">Hediye</div>
                    <div class="n">−{{ number_format((int) $s['expense']['gifts'], 0, ',', '.') }}</div>
                    <div class="h">dönem talep düşümü</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 fin-viz-row">
        <div class="col-lg-5">
            <div class="fin-card">
                <div class="hd">Hediye basıncı <span class="fin-muted" style="font-weight:500">(yalnızca insan)</span></div>
                <div class="bd">
                    <div class="fin-gauge-wrap">
                        <svg class="fin-gauge-svg" viewBox="0 0 120 78" aria-label="Hediye kapasitesi">
                            <path d="M 18 62 A 42 42 0 0 1 102 62" fill="none" stroke="#e2e8f0" stroke-width="10" stroke-linecap="round"/>
                            <path d="M 18 62 A 42 42 0 0 1 102 62" fill="none" stroke="#15803d" stroke-width="10" stroke-linecap="round"
                                  stroke-dasharray="{{ $gaugeLen }}" stroke-dashoffset="{{ $gaugeOffset }}"/>
                            <text x="60" y="64" text-anchor="middle" fill="#14532d" font-size="20" font-weight="700">{{ (int) $pressure['capacity'] }}</text>
                        </svg>
                        <div class="fin-gauge-sub">
                            İnsan düello havuzu · sonraki hediyeye <strong>{{ number_format($needNext, 0, ',', '.') }}</strong>
                            · eşik {{ number_format((int) $pressure['min_coins'], 0, ',', '.') }}
                            · ilerleme %{{ number_format($prog, 0) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="fin-card">
                <div class="hd">Isı takvimi · son 30 gün net coin</div>
                <div class="bd">
                    <div class="fin-heat" aria-label="Günlük net ısı haritası">
                        @php
                            $padStart = 0;
                            if ($heat !== []) {
                                $padStart = max(0, (int) \Carbon\Carbon::parse($heat[0]['date'])->dayOfWeekIso - 1);
                            }
                        @endphp
                        @for($i = 0; $i < $padStart; $i++)
                            <div class="fin-heat-cell" style="background:transparent; border:1px dashed #f1f5f9"></div>
                        @endfor
                        @foreach($heat as $cell)
                            @php
                                $netVal = (int) ($cell['net'] ?? 0);
                                $intensity = $heatMax > 0 ? abs($netVal) / $heatMax : 0;
                                if ($netVal > 0) {
                                    $alpha = 0.18 + 0.82 * $intensity;
                                    $bg = "rgba(21,128,61,{$alpha})";
                                } elseif ($netVal < 0) {
                                    $alpha = 0.18 + 0.82 * $intensity;
                                    $bg = "rgba(185,28,28,{$alpha})";
                                } else {
                                    $bg = '#e2e8f0';
                                }
                                $tip = ($cell['label'] ?? $cell['date'] ?? '') . ' · net ' . ($netVal > 0 ? '+' : '') . number_format($netVal, 0, ',', '.');
                            @endphp
                            <div class="fin-heat-cell" style="background:{{ $bg }}" data-tip="{{ $tip }}"></div>
                        @endforeach
                    </div>
                    <div class="fin-heat-legend">
                        <span class="sw" style="background:#fecaca"></span> zarar
                        <span class="sw" style="background:#e2e8f0"></span> nötr
                        <span class="sw" style="background:#86efac"></span> kâr
                        <span class="ms-auto">Pzt → Paz</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">Gelir · jeton paketi</div>
                <div class="bd">
                    <div class="fin-row">
                        <span>Jeton paketi ({{ (int) $s['iap']['count'] }} satış)</span>
                        <strong class="fin-pos">{{ $fmt($s['iap']['total_coins']) }}</strong>
                    </div>
                    @foreach(array_slice($s['iap']['by_package'] ?? [], 0, 6) as $pkg)
                        <div class="fin-row">
                            <span class="text-muted">{{ $pkg['name'] }} · {{ $pkg['count'] }}</span>
                            <span>{{ $fmt($pkg['coins']) }}</span>
                        </div>
                    @endforeach
                    <div class="fin-row">
                        <strong>Toplam gelir</strong>
                        <strong class="fin-pos">{{ $fmt($s['income']['total']) }}</strong>
                    </div>
                    <div class="fin-note">
                        Gelir içinde %10 düello kesintisi:
                        <strong class="fin-pos">{{ $fmt($s['commission']['total']) }}</strong>
                        · {{ (int) $s['commission']['with_fee'] }} / {{ (int) $s['commission']['duels'] }} maç
                    </div>
                    <div class="fin-muted mt-3 mb-1">Son kesintili maçlar</div>
                    @php
                        $cm = $s['commission_matches'] ?? [];
                        if ($cm === []) {
                            echo '<div class="fin-empty"><i data-feather="slash" style="width:16px;height:16px"></i><span>Bu dönemde kesinti yok.</span></div>';
                        } else {
                            echo '<div class="fin-chips">';
                            foreach ($cm as $m) {
                                echo '<div class="fin-chip">';
                                echo '<span>'.e($m['challenger']).'</span><span class="sep">·</span><span>'.e($m['opponent']).'</span>';
                                echo '<span class="sep">·</span>';
                                echo '<span class="fin-delta-pos">+'.number_format((int)$m['commission'], 0, ',', '.').'</span>';
                                echo '</div>';
                            }
                            echo '</div>';
                        }
                    @endphp
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">Gider · hediye talepleri</div>
                <div class="bd">
                    <div class="fin-row">
                        <span>Hediye talebi ({{ (int) $s['gift_claims']['count'] }})</span>
                        <strong class="fin-neg">−{{ $fmt($s['gift_claims']['total_coins']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <span class="text-muted">· onaylanan</span>
                        <span>−{{ $fmt($s['gift_claims']['approved']) }}</span>
                    </div>
                    <div class="fin-row">
                        <span class="text-muted">· beklemede (dönem)</span>
                        <span>−{{ $fmt($s['gift_claims']['pending']) }}</span>
                    </div>
                    @if(($s['gift_claims']['rejected'] ?? 0) > 0)
                        <div class="fin-row">
                            <span class="text-muted">· red (iade, net’e girmez)</span>
                            <span>{{ $fmt($s['gift_claims']['rejected']) }}</span>
                        </div>
                    @endif
                    <div class="fin-row">
                        <strong>Toplam gider</strong>
                        <strong class="fin-neg">−{{ $fmt($s['expense']['total']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">Havuz · insan</div>
                <div class="bd">
                    <div class="fin-pool-hero">
                        <div class="fin-pool-stack" aria-hidden="true">
                            <img src="{{ $coinImg }}" alt="">
                        </div>
                        <div class="k">İnsan düello</div>
                        <div class="v">{{ $fmt($p['human_duel']) }}</div>
                        <div class="s">Cüzdan {{ $fmt($p['human_wallet']) }} · {{ (int) $p['human_count'] }} kullanıcı</div>
                    </div>
                    <div class="fin-row">
                        <span>İnsan cüzdan</span>
                        <strong>{{ $fmt($p['human_wallet']) }}</strong>
                    </div>
                    <div class="fin-muted mt-3 mb-1">Son insan–insan maçlar</div>
                    @php $renderMatchChips($s['recent_matches'] ?? [], 'Bu dönemde insan–insan maç yok.'); @endphp
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="fin-card fin-card--muted">
                <div class="hd">Bot · referans (P&L dışı)</div>
                <div class="bd">
                    <div class="fin-row">
                        <span class="text-muted">Bot cüzdan ({{ (int) $p['bot_count'] }} bot)</span>
                        <span class="text-muted">{{ $fmt($p['bot_wallet']) }}</span>
                    </div>
                    <div class="fin-row">
                        <span class="text-muted">Bot düello bakiyesi</span>
                        <span class="text-muted">{{ $fmt($p['bot_duel']) }}</span>
                    </div>
                    <div class="fin-row">
                        <span>Maç</span>
                        <strong>{{ number_format((int) $s['bot_period']['matches']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <span>G / M</span>
                        <span>{{ (int) $s['bot_period']['wins'] }} / {{ (int) $s['bot_period']['losses'] }}</span>
                    </div>
                    <div class="fin-row">
                        <span>Bot net (cüzdan)</span>
                        <strong class="{{ $s['bot_period']['net'] >= 0 ? 'fin-pos' : 'fin-neg' }}">{{ $fmt($s['bot_period']['net']) }}</strong>
                    </div>
                    <div class="fin-muted mt-3 mb-1">Son bot maçları</div>
                    @php $renderMatchChips($s['bot_recent_matches'] ?? [], 'Bu dönemde bot maçı yok.', false); @endphp
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.feather) feather.replace();
});
</script>
@endpush
