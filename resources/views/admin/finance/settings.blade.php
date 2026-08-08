@extends('admin.layouts.app')

@section('title', 'Finans ayarları')

@push('styles')
<style>
.fin-settings-hero {
    background: linear-gradient(135deg, #0b1220 0%, #1a2744 55%, #243b5c 100%);
    border-radius: 14px; color: #fff; padding: 1.25rem 1.5rem; margin-bottom: 1.25rem;
}
.fin-settings-hero h3 { color: #fff !important; margin: 0 0 .35rem; font-weight: 650; }
.fin-settings-hero p { margin: 0; color: rgba(255,255,255,.8); }
.fin-settings-hero a {
    color: #fff !important; border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.08);
    padding: .4rem .85rem; border-radius: 8px; text-decoration: none !important; font-size: .85rem; font-weight: 600;
}
.fin-settings-hero a:hover { background: rgba(255,255,255,.16); }
.fin-period-actions { white-space: nowrap; }
.fin-period-actions .btn { padding: .15rem .45rem; font-size: .75rem; }
</style>
@endpush

@section('content')
@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

<div class="fin-settings-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h3>Finans ayarları · TL</h3>
        <p>Oran dönemleri geçmişi bozmaz: her kayıt/satış, kendi tarihindeki dönem oranıyla hesaplanır. Yeni dönem ekleyince önceki açık uç kapanır.</p>
        <p class="mt-2 mb-0" style="color:rgba(255,255,255,.9);font-size:.9rem">
            Aktif talep eşiği: <strong>{{ number_format($currentClaimCoins) }} jeton</strong>
            · onayda varsayılan ödeme: <strong>{{ number_format($currentPayoutTry, 2, ',', '.') }} ₺</strong>
            (API buradan okur)
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-start">
        <a href="{{ route('admin.finance.coin.index') }}">Coin finans</a>
        <a href="{{ route('admin.finance.index') }}">TL finansa dön</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-header bg-white"><strong>Oran dönemleri</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Başlangıç</th>
                                <th>Bitiş</th>
                                <th>Store %</th>
                                <th>GV %</th>
                                <th>KDV %</th>
                                <th>KDV→P&L</th>
                                <th>Ödül / eşik ₺</th>
                                <th>Coin→₺</th>
                                <th>Not</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($periods as $p)
                                <tr>
                                    <td>{{ tr_time($p->effective_from, 'd.m.Y') }}</td>
                                    <td>{{ $p->effective_to ? tr_time($p->effective_to, 'd.m.Y') : '∞' }}</td>
                                    <td>{{ number_format($p->store_fee_pct, 1, ',', '.') }}</td>
                                    <td>{{ number_format($p->income_tax_pct, 1, ',', '.') }}</td>
                                    <td>{{ number_format($p->kdv_pct, 1, ',', '.') }}</td>
                                    <td>{{ !empty($p->kdv_to_pl) ? 'Evet' : 'Ref' }}</td>
                                    <td>{{ number_format($p->gift_payout_try, 2, ',', '.') }}</td>
                                    <td>{{ number_format($p->coin_to_try, 2, ',', '.') }}</td>
                                    <td class="small text-muted">{{ $p->note ?: '—' }}</td>
                                    <td class="fin-period-actions text-end">
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
                <div class="px-3 py-2 small text-muted border-top">
                    Tarihler önemli: eski dönemdeki IAP/ödüller eski oranlarla kalır. “Ödül / eşik ₺” aynı zamanda API talep eşiğidir (coin→₺ = 1,00 iken 250 ₺ = 250 jeton).
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3" style="border-radius:12px">
            <div class="card-header bg-white"><strong>Yeni dönem</strong></div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.finance.periods.store') }}" class="row g-2">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label small">Başlangıç</label>
                        <input type="date" name="effective_from" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Google kesinti %</label>
                        <input type="text" inputmode="decimal" name="store_fee_pct" class="form-control form-control-sm"
                               data-fin-num="pct" data-fin-suffix="%" value="40" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Gelir vergisi %</label>
                        <input type="text" inputmode="decimal" name="income_tax_pct" class="form-control form-control-sm"
                               data-fin-num="pct" data-fin-suffix="%" value="25" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Ödül / talep eşiği ₺</label>
                        <input type="text" inputmode="decimal" name="gift_payout_try" class="form-control form-control-sm"
                               data-fin-num="money" data-fin-suffix="₺" value="{{ number_format($currentPayoutTry, 2, ',', '.') }}" required>
                        <div class="form-text">API eşiği = bu / coin→₺</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">1 coin = ₺</label>
                        <input type="text" inputmode="decimal" name="coin_to_try" class="form-control form-control-sm"
                               data-fin-num="rate" data-fin-suffix="₺" value="1,00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">KDV % (referans / P&L)</label>
                        <input type="text" inputmode="decimal" name="kdv_pct" class="form-control form-control-sm"
                               data-fin-num="pct" data-fin-suffix="%" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">KDV satış etkisi</label>
                        <select name="kdv_to_pl" class="form-select form-select-sm">
                            <option value="0" selected>Sadece referans</option>
                            <option value="1">P&L’ye gider yaz</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small">Not</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="255">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Dönem ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-header bg-white"><strong>Ay kilidi</strong></div>
            <div class="card-body">
                <p class="small text-muted mb-2">Kilitli aya gelir/gider eklenemez, düzenlenemez, silinemez.</p>
                <form method="post" action="{{ route('admin.finance.locks.store') }}" class="row g-2 align-items-end mb-3">
                    @csrf
                    <div class="col-4">
                        <label class="form-label small">Yıl</label>
                        <input type="number" name="year" class="form-control form-control-sm" value="{{ $lockYear }}" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small">Ay</label>
                        <input type="number" name="month" min="1" max="12" class="form-control form-control-sm" value="{{ $lockMonth }}" required>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-sm btn-dark w-100">Kilitle</button>
                    </div>
                </form>
                <ul class="list-group list-group-flush">
                    @forelse($monthLocks as $lock)
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span>{{ sprintf('%02d.%04d', $lock->month, $lock->year) }}</span>
                            <form method="post" action="{{ route('admin.finance.locks.destroy') }}" onsubmit="return confirm('Kilidi aç?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="year" value="{{ $lock->year }}">
                                <input type="hidden" name="month" value="{{ $lock->month }}">
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Aç</button>
                            </form>
                        </li>
                    @empty
                        <li class="list-group-item px-0 text-muted small">Kilitli ay yok.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3" style="border-radius:12px">
            <div class="card-header bg-white"><strong>Gider kategorileri</strong></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($categories as $c)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $c->name }}</span>
                            <span class="small text-muted">{{ $c->is_system ? 'sistem' : 'özel' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body border-top">
                <form method="post" action="{{ route('admin.finance.categories.store') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Yeni kategori" required maxlength="120">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Ekle</button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Dönem düzenle modal --}}
<div class="modal fade" id="finPeriodModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px">
            <form method="post" id="finPeriodEditForm">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Oran dönemi düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small">Başlangıç</label>
                            <input type="date" name="effective_from" id="editFrom" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Bitiş (boş = ∞)</label>
                            <input type="date" name="effective_to" id="editTo" class="form-control form-control-sm">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Google kesinti %</label>
                            <input type="text" inputmode="decimal" name="store_fee_pct" id="editStore" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Gelir vergisi %</label>
                            <input type="text" inputmode="decimal" name="income_tax_pct" id="editTax" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Ödül / eşik ₺</label>
                            <input type="text" inputmode="decimal" name="gift_payout_try" id="editGift" class="form-control form-control-sm"
                                   data-fin-num="money" data-fin-suffix="₺" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">1 coin = ₺</label>
                            <input type="text" inputmode="decimal" name="coin_to_try" id="editCoin" class="form-control form-control-sm"
                                   data-fin-num="rate" data-fin-suffix="₺" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small">KDV %</label>
                            <input type="text" inputmode="decimal" name="kdv_pct" id="editKdv" class="form-control form-control-sm"
                                   data-fin-num="pct" data-fin-suffix="%">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">KDV satış etkisi</label>
                            <select name="kdv_to_pl" id="editKdvPl" class="form-select form-select-sm">
                                <option value="0">Sadece referans</option>
                                <option value="1">P&L’ye gider yaz</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small">Not</label>
                            <input type="text" name="note" id="editNote" class="form-control form-control-sm" maxlength="255">
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
