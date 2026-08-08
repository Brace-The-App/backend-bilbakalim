@extends('admin.layouts.app')

@section('title', 'Finans ayarları')

@push('styles')
<style>
.fin-set-wrap { max-width: 1400px; }
.fin-set-hero {
    background: linear-gradient(135deg, #0c2340 0%, #12405a 45%, #0f5c56 100%);
    border-radius: 14px; color: #fff; padding: 1.35rem 1.5rem; margin-bottom: 1rem;
    border: 1px solid rgba(153,246,228,.12);
    box-shadow: 0 10px 28px rgba(12,35,64,.18);
}
.fin-set-hero h3 { color: #fff !important; margin: 0 0 .35rem; font-weight: 650; }
.fin-set-hero p { margin: 0; color: rgba(255,255,255,.82); font-size: .92rem; line-height: 1.45; }
.fin-set-hero a {
    color: #fff !important; border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.08);
    padding: .4rem .85rem; border-radius: 8px; text-decoration: none !important; font-size: .85rem; font-weight: 600;
}
.fin-set-hero a:hover { background: rgba(255,255,255,.16); }
.fin-set-kpis {
    display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: .75rem; margin-bottom: 1rem;
}
@media (max-width: 768px) { .fin-set-kpis { grid-template-columns: 1fr; } }
.fin-set-kpi {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem 1.1rem;
    min-height: 5.5rem;
}
.fin-set-kpi .k { font-size: .7rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 700; }
.fin-set-kpi .v { font-size: 1.35rem; font-weight: 750; margin-top: .3rem; font-variant-numeric: tabular-nums; color: #0f172a; }
.fin-set-kpi .s { font-size: .75rem; color: #94a3b8; margin-top: .25rem; }
.fin-set-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 1rem;
}
.fin-set-card .hd {
    padding: .85rem 1.1rem; border-bottom: 1px solid #f1f5f9; font-weight: 650; font-size: .95rem; color: #0f172a;
    display: flex; justify-content: space-between; align-items: center; gap: .5rem; flex-wrap: wrap;
}
.fin-set-card .hd .sub { font-size: .78rem; color: #64748b; font-weight: 500; }
.fin-set-card .bd { padding: 1.1rem; }
.fin-set-card .ft {
    padding: .7rem 1.1rem; border-top: 1px solid #f1f5f9; font-size: .8rem; color: #64748b; background: #f8fafc;
}
.fin-set-table { margin: 0; }
.fin-set-table thead th {
    font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; font-weight: 700;
    border-bottom-color: #e2e8f0; background: #f8fafc; white-space: nowrap;
}
.fin-set-table td { font-size: .88rem; vertical-align: middle; font-variant-numeric: tabular-nums; }
.fin-set-table tbody tr:hover { background: #f8fafc; }
.fin-badge {
    display: inline-flex; align-items: center; font-size: .68rem; font-weight: 700; padding: .15rem .45rem;
    border-radius: 999px; letter-spacing: .02em;
}
.fin-badge-live { background: #ecfdf5; color: #047857; }
.fin-badge-ref { background: #f1f5f9; color: #475569; }
.fin-badge-pl { background: #ecfeff; color: #0f766e; }
.fin-badge-sys { background: #eff6ff; color: #1d4ed8; }
.fin-badge-custom { background: #fef3c7; color: #92400e; }
.fin-period-actions { white-space: nowrap; }
.fin-period-actions .btn { padding: .2rem .55rem; font-size: .75rem; font-weight: 600; border-radius: 7px; }
.fin-set-label {
    display: block; font-size: .7rem; color: #64748b; margin-bottom: .25rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .03em;
}
.fin-set-form .form-control, .fin-set-form .form-select {
    border-radius: 8px; border-color: #e2e8f0;
}
.fin-set-form .form-control:focus, .fin-set-form .form-select:focus {
    border-color: #5eead4; box-shadow: 0 0 0 .2rem rgba(94,234,212,.2);
}
.fin-set-btn-primary {
    background: linear-gradient(135deg, #0f766e, #0d9488); border: 0; color: #fff; font-weight: 650;
    border-radius: 8px; padding: .4rem .9rem;
}
.fin-set-btn-primary:hover { color: #fff; filter: brightness(1.05); }
.fin-set-btn-dark {
    background: #0f172a; border: 0; color: #fff; font-weight: 650; border-radius: 8px; padding: .4rem .9rem;
}
.fin-set-btn-dark:hover { color: #fff; filter: brightness(1.08); }
.fin-lock-item {
    display: flex; justify-content: space-between; align-items: center; gap: .75rem;
    padding: .65rem 0; border-bottom: 1px dashed #f1f5f9; font-size: .9rem;
}
.fin-lock-item:last-child { border-bottom: 0; padding-bottom: 0; }
.fin-lock-item .ym { font-weight: 650; font-variant-numeric: tabular-nums; color: #0f172a; }
.fin-cat-item {
    display: flex; justify-content: space-between; align-items: center; gap: .75rem;
    padding: .6rem 1.1rem; border-bottom: 1px solid #f1f5f9; font-size: .9rem;
}
.fin-cat-item:last-child { border-bottom: 0; }
.fin-info-soft {
    background: #f0fdfa; border: 1px dashed #99f6e4; border-radius: 10px;
    padding: .75rem 1rem; font-size: .84rem; color: #115e59; margin-bottom: 1rem;
}
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
    $activePeriod = $periods->first(function ($p) {
        return $p->effective_to === null || $p->effective_to->isFuture() || $p->effective_to->isToday();
    }) ?? $periods->first();
@endphp

<div class="fin-set-wrap">
    <div class="fin-set-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h3>Finans ayarları · TL</h3>
            <p>Oran dönemleri geçmişi bozmaz: her kayıt/satış kendi tarihindeki dönem oranıyla hesaplanır. Yeni dönem ekleyince önceki açık uç kapanır.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-start">
            <a href="{{ route('admin.finance.coin.index') }}">Coin finans</a>
            <a href="{{ route('admin.finance.index') }}">TL finansa dön</a>
        </div>
    </div>

    <div class="fin-set-kpis">
        <div class="fin-set-kpi">
            <div class="k">Aktif talep eşiği</div>
            <div class="v">{{ number_format($currentClaimCoins) }} <span style="font-size:.85rem;font-weight:600;color:#64748b">jeton</span></div>
            <div class="s">API buradan okur</div>
        </div>
        <div class="fin-set-kpi">
            <div class="k">Onay varsayılan ödeme</div>
            <div class="v">{{ number_format($currentPayoutTry, 2, ',', '.') }} <span style="font-size:.85rem;font-weight:600;color:#64748b">₺</span></div>
            <div class="s">Ödül / eşik ₺ alanı</div>
        </div>
        <div class="fin-set-kpi">
            <div class="k">Aktif dönem</div>
            <div class="v" style="font-size:1.05rem">
                @if($activePeriod)
                    {{ tr_time($activePeriod->effective_from, 'd.m.Y') }}
                    → {{ $activePeriod->effective_to ? tr_time($activePeriod->effective_to, 'd.m.Y') : '∞' }}
                @else
                    —
                @endif
            </div>
            <div class="s">
                @if($activePeriod)
                    Store %{{ number_format($activePeriod->store_fee_pct, 1, ',', '.') }}
                    · GV %{{ number_format($activePeriod->income_tax_pct, 1, ',', '.') }}
                    · KDV %{{ number_format($activePeriod->kdv_pct, 1, ',', '.') }}
                @else
                    Dönem yok
                @endif
            </div>
        </div>
    </div>

    <div class="fin-info-soft">
        Tarihler kritik: eski dönemdeki IAP/ödüller eski oranlarla kalır.
        “Ödül / eşik ₺” aynı zamanda API talep eşiğidir (coin→₺ = 1,00 iken 250 ₺ = 250 jeton).
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="fin-set-card">
                <div class="hd">
                    <span>Oran dönemleri</span>
                    <span class="sub">{{ $periods->count() }} kayıt</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 fin-set-table">
                        <thead>
                            <tr>
                                <th class="ps-3">Başlangıç</th>
                                <th>Bitiş</th>
                                <th>Store</th>
                                <th>GV</th>
                                <th>KDV</th>
                                <th>KDV→P&L</th>
                                <th>Ödül ₺</th>
                                <th>Coin→₺</th>
                                <th>Not</th>
                                <th class="pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($periods as $p)
                                @php
                                    $isLive = $activePeriod && (int) $activePeriod->id === (int) $p->id;
                                @endphp
                                <tr>
                                    <td class="ps-3">
                                        {{ tr_time($p->effective_from, 'd.m.Y') }}
                                        @if($isLive)
                                            <span class="fin-badge fin-badge-live ms-1">aktif</span>
                                        @endif
                                    </td>
                                    <td>{{ $p->effective_to ? tr_time($p->effective_to, 'd.m.Y') : '∞' }}</td>
                                    <td>%{{ number_format($p->store_fee_pct, 1, ',', '.') }}</td>
                                    <td>%{{ number_format($p->income_tax_pct, 1, ',', '.') }}</td>
                                    <td>%{{ number_format($p->kdv_pct, 1, ',', '.') }}</td>
                                    <td>
                                        @if(!empty($p->kdv_to_pl))
                                            <span class="fin-badge fin-badge-pl">P&L</span>
                                        @else
                                            <span class="fin-badge fin-badge-ref">Ref</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($p->gift_payout_try, 2, ',', '.') }}</td>
                                    <td>{{ number_format($p->coin_to_try, 2, ',', '.') }}</td>
                                    <td class="small text-muted">{{ $p->note ?: '—' }}</td>
                                    <td class="fin-period-actions text-end pe-3">
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-edit-period"
                                                data-bs-toggle="modal"
                                                data-bs-target="#finPeriodModal"
                                                data-id="{{ $p->id }}"
                                                data-from="{{ $p->effective_from?->toDateString() }}"
                                                data-to="{{ $p->effective_to?->toDateString() }}"
                                                data-store="{{ number_format($p->store_fee_pct, 1, ',', '.') }}"
                                                data-tax="{{ number_format($p->income_tax_pct, 1, ',', '.') }}"
                                                data-kdv="{{ number_format($p->kdv_pct, 1, ',', '.') }}"
                                                data-kdv-pl="{{ !empty($p->kdv_to_pl) ? '1' : '0' }}"
                                                data-gift="{{ number_format($p->gift_payout_try, 2, ',', '.') }}"
                                                data-coin="{{ number_format($p->coin_to_try, 2, ',', '.') }}"
                                                data-note="{{ $p->note ?? '' }}">
                                            Düzenle
                                        </button>
                                        <form method="post" action="{{ route('admin.finance.periods.destroy', $p->id) }}" class="d-inline"
                                              onsubmit="return confirm('Bu oran dönemini silmek istediğinize emin misiniz?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger">Sil</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="fin-set-card">
                <div class="hd">
                    <span>Yeni dönem</span>
                    <span class="sub">Önceki açık uç otomatik kapanır</span>
                </div>
                <div class="bd fin-set-form">
                    <form method="post" action="{{ route('admin.finance.periods.store') }}" class="row g-2">
                        @csrf
                        <div class="col-md-4">
                            <label class="fin-set-label">Başlangıç</label>
                            <input type="date" name="effective_from" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fin-set-label">Google kesinti %</label>
                            <input type="text" inputmode="decimal" name="store_fee_pct" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%" value="40" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fin-set-label">Gelir vergisi %</label>
                            <input type="text" inputmode="decimal" name="income_tax_pct" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%" value="25" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fin-set-label">Ödül / talep eşiği ₺</label>
                            <input type="text" inputmode="decimal" name="gift_payout_try" class="form-control form-control-sm"
                                   data-fin-num="money" data-fin-suffix="₺" value="{{ number_format($currentPayoutTry, 2, ',', '.') }}" required>
                            <div class="form-text">API eşiği = bu / coin→₺</div>
                        </div>
                        <div class="col-md-4">
                            <label class="fin-set-label">1 coin = ₺</label>
                            <input type="text" inputmode="decimal" name="coin_to_try" class="form-control form-control-sm"
                                   data-fin-num="rate" data-fin-suffix="₺" value="1,00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="fin-set-label">KDV % (referans / P&L)</label>
                            <input type="text" inputmode="decimal" name="kdv_pct" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="fin-set-label">KDV satış etkisi</label>
                            <select name="kdv_to_pl" class="form-select form-select-sm">
                                <option value="0" selected>Sadece referans</option>
                                <option value="1">P&L’ye gider yaz</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="fin-set-label">Not</label>
                            <input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="opsyonel">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn fin-set-btn-primary w-100">Dönem ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="fin-set-card">
                <div class="hd">
                    <span>Ay kilidi</span>
                    <span class="sub">Kilitli aya dokunulamaz</span>
                </div>
                <div class="bd fin-set-form">
                    <p class="small text-muted mb-3">Kilitli aya gelir/gider eklenemez, düzenlenemez, silinemez.</p>
                    <form method="post" action="{{ route('admin.finance.locks.store') }}" class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-4">
                            <label class="fin-set-label">Yıl</label>
                            <input type="number" name="year" class="form-control form-control-sm" value="{{ $lockYear }}" required>
                        </div>
                        <div class="col-4">
                            <label class="fin-set-label">Ay</label>
                            <input type="number" name="month" min="1" max="12" class="form-control form-control-sm" value="{{ $lockMonth }}" required>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn fin-set-btn-dark w-100">Kilitle</button>
                        </div>
                    </form>
                    @forelse($monthLocks as $lock)
                        <div class="fin-lock-item">
                            <span class="ym">{{ sprintf('%02d.%04d', $lock->month, $lock->year) }}</span>
                            <form method="post" action="{{ route('admin.finance.locks.destroy') }}" onsubmit="return confirm('Kilidi aç?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="year" value="{{ $lock->year }}">
                                <input type="hidden" name="month" value="{{ $lock->month }}">
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Aç</button>
                            </form>
                        </div>
                    @empty
                        <div class="small text-muted">Kilitli ay yok.</div>
                    @endforelse
                </div>
            </div>

            <div class="fin-set-card">
                <div class="hd">
                    <span>Gider kategorileri</span>
                    <span class="sub">{{ $categories->count() }} adet</span>
                </div>
                <div>
                    @foreach($categories as $c)
                        <div class="fin-cat-item">
                            <span>{{ $c->name }}</span>
                            @if($c->is_system)
                                <span class="fin-badge fin-badge-sys">sistem</span>
                            @else
                                <span class="fin-badge fin-badge-custom">özel</span>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="bd border-top fin-set-form">
                    <form method="post" action="{{ route('admin.finance.categories.store') }}" class="d-flex gap-2">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="Yeni kategori" required maxlength="120">
                        <button type="submit" class="btn btn-sm btn-outline-primary" style="border-radius:8px;font-weight:650">Ekle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Dönem düzenle modal --}}
<div class="modal fade" id="finPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:0;overflow:hidden">
            <form method="post" id="finPeriodEditForm" class="fin-set-form">
                @csrf
                @method('PUT')
                <div class="modal-header" style="background:linear-gradient(135deg,#0c2340,#0f5c56);color:#fff">
                    <h5 class="modal-title" style="color:#fff">Oran dönemi düzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="fin-set-label">Başlangıç</label>
                            <input type="date" name="effective_from" id="editFrom" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="fin-set-label">Bitiş (boş = ∞)</label>
                            <input type="date" name="effective_to" id="editTo" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="fin-set-label">Google kesinti %</label>
                            <input type="text" inputmode="decimal" name="store_fee_pct" id="editStore" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%" required>
                        </div>
                        <div class="col-6">
                            <label class="fin-set-label">Gelir vergisi %</label>
                            <input type="text" inputmode="decimal" name="income_tax_pct" id="editTax" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%" required>
                        </div>
                        <div class="col-6">
                            <label class="fin-set-label">Ödül / eşik ₺</label>
                            <input type="text" inputmode="decimal" name="gift_payout_try" id="editGift" class="form-control form-control-sm"
                                   data-fin-num="money" data-fin-suffix="₺" required>
                        </div>
                        <div class="col-6">
                            <label class="fin-set-label">1 coin = ₺</label>
                            <input type="text" inputmode="decimal" name="coin_to_try" id="editCoin" class="form-control form-control-sm"
                                   data-fin-num="rate" data-fin-suffix="₺" required>
                        </div>
                        <div class="col-6">
                            <label class="fin-set-label">KDV %</label>
                            <input type="text" inputmode="decimal" name="kdv_pct" id="editKdv" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%">
                        </div>
                        <div class="col-6">
                            <label class="fin-set-label">KDV satış etkisi</label>
                            <select name="kdv_to_pl" id="editKdvPl" class="form-select form-select-sm">
                                <option value="0">Sadece referans</option>
                                <option value="1">P&L’ye gider yaz</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="fin-set-label">Not</label>
                            <input type="text" name="note" id="editNote" class="form-control form-control-sm" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-sm fin-set-btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('admin.finance._number-format')
<script>
(function () {
    var modal = document.getElementById('finPeriodModal');
    var form = document.getElementById('finPeriodEditForm');
    if (!modal || !form) return;

    var updateUrlTpl = @json(url('admin/finance/periods/__ID__'));

    modal.addEventListener('show.bs.modal', function (ev) {
        var btn = ev.relatedTarget;
        if (!btn || !btn.classList.contains('btn-edit-period')) return;
        var id = btn.getAttribute('data-id');
        form.action = updateUrlTpl.replace('__ID__', id);
        document.getElementById('editFrom').value = btn.getAttribute('data-from') || '';
        document.getElementById('editTo').value = btn.getAttribute('data-to') || '';
        document.getElementById('editStore').value = btn.getAttribute('data-store') || '';
        document.getElementById('editTax').value = btn.getAttribute('data-tax') || '';
        document.getElementById('editKdv').value = btn.getAttribute('data-kdv') || '0';
        document.getElementById('editKdvPl').value = btn.getAttribute('data-kdv-pl') || '0';
        document.getElementById('editGift').value = btn.getAttribute('data-gift') || '';
        document.getElementById('editCoin').value = btn.getAttribute('data-coin') || '';
        document.getElementById('editNote').value = btn.getAttribute('data-note') || '';
        if (window.FinNumber && window.FinNumber.init) {
            window.FinNumber.init(modal);
        }
    });
})();
</script>
@endpush
