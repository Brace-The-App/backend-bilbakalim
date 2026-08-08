@extends('admin.layouts.app')

@section('title', 'Finans · Coin')

@push('styles')
<style>
.fin-coin-wrap { max-width: 1400px; }
.fin-coin-hero {
    background: linear-gradient(135deg, #0c1a14 0%, #14352a 55%, #1a4d3a 100%);
    border-radius: 14px; color: #fff; padding: 1.35rem 1.5rem; margin-bottom: 1rem;
}
.fin-coin-hero h3 { color: #fff !important; margin: 0 0 .35rem; font-weight: 650; }
.fin-coin-hero p { margin: 0; color: rgba(255,255,255,.82); font-size: .95rem; }
.fin-coin-hero a {
    color: #fff !important; border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.08);
    padding: .4rem .85rem; border-radius: 8px; text-decoration: none !important; font-size: .85rem; font-weight: 600;
}
.fin-coin-hero a:hover { background: rgba(255,255,255,.16); }
.fin-presets { display:flex; flex-wrap:wrap; gap:.4rem; }
.fin-presets a {
    font-size:.8rem; padding:.4rem .75rem; border-radius:999px; border:1px solid #e2e8f0;
    color:#334155; text-decoration:none; background:#fff; font-weight:600;
}
.fin-presets a.is-on { background:#0f172a; color:#fff; border-color:#0f172a; }
.fin-kpis { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: .75rem; margin-bottom: 1rem; }
@media (max-width: 992px) { .fin-kpis { grid-template-columns: repeat(2, minmax(0,1fr)); } }
.fin-kpi {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.1rem;
    min-height: 6.75rem; display: flex; flex-direction: column; justify-content: space-between;
}
.fin-kpi .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
.fin-kpi .v { font-size: 1.35rem; font-weight: 700; margin-top: .35rem; font-variant-numeric: tabular-nums; }
.fin-kpi .s { font-size: .75rem; color: #94a3b8; margin-top: .35rem; line-height: 1.35; min-height: 2.1rem; }
.fin-pos { color: #15803d; }
.fin-neg { color: #b91c1c; }
.fin-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 1rem; overflow: hidden; height: 100%; }
.fin-card .hd { padding: .85rem 1.1rem; border-bottom: 1px solid #f1f5f9; font-weight: 650; font-size: .95rem; }
.fin-card .bd { padding: 1.1rem; }
.fin-row { display: flex; justify-content: space-between; gap: 1rem; padding: .45rem 0; border-bottom: 1px dashed #f1f5f9; font-size: .92rem; }
.fin-row:last-child { border-bottom: 0; }
.fin-info { background: #f0fdf4; border: 1px dashed #86efac; border-radius: 10px; padding: .75rem 1rem; font-size: .85rem; color: #166534; margin-bottom: 1rem; }
.fin-bar { height: 10px; border-radius: 999px; background: #e2e8f0; overflow: hidden; display: flex; margin: .75rem 0 .35rem; }
.fin-bar > span { display: block; height: 100%; }
.fin-bar .inc { background: #15803d; }
.fin-bar .exp { background: #b91c1c; }
.fin-muted { color: #64748b; font-size: .82rem; }
.fin-match {
    padding: .65rem 0; border-bottom: 1px dashed #f1f5f9; font-size: .88rem;
}
.fin-match:last-child { border-bottom: 0; padding-bottom: 0; }
.fin-match .vs { font-weight: 600; color: #0f172a; }
.fin-match .meta { color: #64748b; font-size: .78rem; margin-top: .2rem; }
.fin-delta-pos { color: #15803d; font-weight: 600; }
.fin-delta-neg { color: #b91c1c; font-weight: 600; }
</style>
@endpush

@section('content')
@php
    $s = $summary;
    $p = $s['pools'];
    $fmt = fn ($n) => number_format((int) $n, 0, ',', '.') . ' coin';
    $deltaFmt = function (int $n) {
        $sign = $n > 0 ? '+' : '';
        return $sign . number_format($n, 0, ',', '.');
    };
    $renderMatches = function (array $matches) use ($deltaFmt) {
        if ($matches === []) {
            echo '<div class="fin-muted">Bu dönemde maç yok.</div>';
            return;
        }
        foreach ($matches as $m) {
            $cCls = $m['challenger_delta'] >= 0 ? 'fin-delta-pos' : 'fin-delta-neg';
            $oCls = $m['opponent_delta'] >= 0 ? 'fin-delta-pos' : 'fin-delta-neg';
            $when = !empty($m['finished_at']) ? tr_time($m['finished_at'], 'd.m.Y H:i') : '—';
            echo '<div class="fin-match">';
            echo '<div class="vs">'.e($m['challenger']).' <span class="'.$cCls.'">('.$deltaFmt((int)$m['challenger_delta']).')</span>';
            echo ' · '.e($m['opponent']).' <span class="'.$oCls.'">('.$deltaFmt((int)$m['opponent_delta']).')</span></div>';
            echo '<div class="meta">#'.$m['id'].' · '.$when;
            if (!empty($m['winner'])) {
                echo ' · kazanan: '.e($m['winner']);
            }
            if ((int)($m['commission'] ?? 0) > 0) {
                echo ' · kesinti '.number_format((int)$m['commission'], 0, ',', '.');
            }
            echo '</div></div>';
        }
    };
@endphp
<div class="fin-coin-wrap">
    <div class="fin-coin-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h3>Finans · Coin</h3>
            <p>Jeton ekonomisi · {{ tr_time($from, 'd.m.Y') }} – {{ tr_time($to, 'd.m.Y') }}</p>
        </div>
        <a href="{{ route('admin.finance.index') }}">TL (nakit) finans</a>
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
            <div>
                <div class="k">Coin gelir</div>
                <div class="v fin-pos">{{ $fmt($s['income']['total']) }}</div>
            </div>
            <div class="s">Paket {{ $fmt($s['income']['iap']) }} + kesinti {{ $fmt($s['income']['commission']) }}</div>
        </div>
        <div class="fin-kpi">
            <div>
                <div class="k">Coin gider</div>
                <div class="v fin-neg">{{ $fmt($s['expense']['total']) }}</div>
            </div>
            <div class="s">Hediye talep düşümü</div>
        </div>
        <div class="fin-kpi">
            <div>
                <div class="k">Net</div>
                <div class="v {{ $s['net'] >= 0 ? 'fin-pos' : 'fin-neg' }}">{{ $fmt($s['net']) }}</div>
            </div>
            <div class="s">Gelir {{ $s['balance_pct']['income'] }}% · gider {{ $s['balance_pct']['expense'] }}%</div>
        </div>
        <div class="fin-kpi">
            <div>
                <div class="k">İnsan düello</div>
                <div class="v">{{ $fmt($p['human_duel']) }}</div>
            </div>
            <div class="s">Cüzdan {{ $fmt($p['human_wallet']) }} · {{ (int) $p['human_count'] }} kullanıcı</div>
        </div>
    </div>

    <div class="fin-card mb-3">
        <div class="hd">Gelir / gider dengesi</div>
        <div class="bd">
            <div class="fin-bar">
                <span class="inc" style="width: {{ $s['balance_pct']['income'] }}%"></span>
                <span class="exp" style="width: {{ $s['balance_pct']['expense'] }}%"></span>
            </div>
            <div class="d-flex flex-wrap justify-content-between gap-2 fin-muted">
                <span>Gelir payı %{{ number_format($s['balance_pct']['income'], 1, ',', '.') }} (paket %{{ number_format($s['balance_pct']['iap_of_income'], 1, ',', '.') }} · kesinti %{{ number_format($s['balance_pct']['commission_of_income'], 1, ',', '.') }})</span>
                <span>Gider payı %{{ number_format($s['balance_pct']['expense'], 1, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
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
                        <span class="text-muted">+ düello kesintisi</span>
                        <span class="fin-pos">{{ $fmt($s['commission']['total']) }}</span>
                    </div>
                    <div class="fin-row">
                        <strong>Toplam gelir</strong>
                        <strong class="fin-pos">{{ $fmt($s['income']['total']) }}</strong>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="fin-card">
                <div class="hd">Gider kırılımı</div>
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
                        <span class="text-muted">· beklemede</span>
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
        <div class="col-lg-4">
            <div class="fin-card">
                <div class="hd">Düello kesintisi (%10)</div>
                <div class="bd">
                    <div class="fin-row">
                        <span>Uygulamaya kalan</span>
                        <strong class="fin-pos">{{ $fmt($s['commission']['total']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <span>Kesinti alınan maç</span>
                        <span>{{ (int) $s['commission']['with_fee'] }} / {{ (int) $s['commission']['duels'] }}</span>
                    </div>
                    <div class="fin-muted mt-3 mb-1">Son kesintili maçlar</div>
                    @php
                        $cm = $s['commission_matches'] ?? [];
                        if ($cm === []) {
                            echo '<div class="fin-muted">Bu dönemde kesinti yok.</div>';
                        } else {
                            foreach ($cm as $m) {
                                $when = !empty($m['finished_at']) ? tr_time($m['finished_at'], 'd.m.Y H:i') : '—';
                                echo '<div class="fin-match">';
                                echo '<div class="vs">'.e($m['challenger']).' · '.e($m['opponent']).'</div>';
                                echo '<div class="meta">#'.$m['id'].' · '.$when;
                                if (!empty($m['winner'])) {
                                    echo ' · kazanan: '.e($m['winner']);
                                }
                                echo ' · <span class="fin-delta-pos">+'.number_format((int)$m['commission'], 0, ',', '.').' kesinti</span>';
                                echo '</div></div>';
                            }
                        }
                    @endphp
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">Havuz</div>
                <div class="bd">
                    <div class="fin-row">
                        <span>İnsan cüzdan</span>
                        <strong>{{ $fmt($p['human_wallet']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <span>İnsan düello</span>
                        <strong>{{ $fmt($p['human_duel']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <span class="text-muted">Bot cüzdan ({{ (int) $p['bot_count'] }} bot)</span>
                        <span class="text-muted">{{ $fmt($p['bot_wallet']) }}</span>
                    </div>
                    <div class="fin-row">
                        <span class="text-muted">Bot düello bakiyesi</span>
                        <span class="text-muted">{{ $fmt($p['bot_duel']) }}</span>
                    </div>
                    <div class="fin-muted mt-3 mb-1">Son 3 insan–insan maç (seçili dönem)</div>
                    @php $renderMatches($s['recent_matches'] ?? []); @endphp
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="fin-card">
                <div class="hd">Bot düello (cüzdan hareketi)</div>
                <div class="bd">
                    <div class="fin-muted mb-2">Botların kendi bakiyesi.</div>
                    <div class="fin-row">
                        <span>Maç</span>
                        <strong>{{ number_format((int) $s['bot_period']['matches']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <span>Galibiyet / mağlubiyet</span>
                        <span>{{ (int) $s['bot_period']['wins'] }} / {{ (int) $s['bot_period']['losses'] }}</span>
                    </div>
                    <div class="fin-row">
                        <span>Bot cüzdanına giren</span>
                        <strong class="fin-pos">{{ $fmt($s['bot_period']['won']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <span>Bot cüzdanından çıkan</span>
                        <strong class="fin-neg">−{{ $fmt($s['bot_period']['lost']) }}</strong>
                    </div>
                    <div class="fin-row">
                        <strong>Bot net (cüzdan)</strong>
                        <strong class="{{ $s['bot_period']['net'] >= 0 ? 'fin-pos' : 'fin-neg' }}">{{ $fmt($s['bot_period']['net']) }}</strong>
                    </div>
                    @if(($s['bot_period']['draws_or_cancel'] ?? 0) > 0)
                        <div class="fin-muted mt-1">Kazananı olmayan / iptal: {{ (int) $s['bot_period']['draws_or_cancel'] }}</div>
                    @endif
                    <div class="fin-muted mt-3 mb-1">Son 3 bot maçı (seçili dönem)</div>
                    @php $renderMatches($s['bot_recent_matches'] ?? []); @endphp
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
