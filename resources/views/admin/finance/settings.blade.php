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
.fin-settings-hero a,
.fin-settings-hero-btn {
    color: #fff !important; border: 1px solid rgba(255,255,255,.45); background: rgba(255,255,255,.08);
    padding: .4rem .85rem; border-radius: 8px; text-decoration: none !important; font-size: .85rem; font-weight: 600;
    cursor: pointer; display: inline-block;
}
.fin-settings-hero-btn:hover { background: rgba(255,255,255,.16); }
</style>
@endpush

@section('content')
<div class="fin-settings-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <h3>Finans ayarları</h3>
        <p>Oran dönemleri geçmişi bozmaz: her kayıt/satış, kendi tarihindeki dönem oranıyla hesaplanır. Yeni dönem ekleyince önceki açık uç kapanır.</p>
        <p class="mt-2 mb-0" style="color:rgba(255,255,255,.9);font-size:.9rem">
            Aktif talep eşiği: <strong>{{ number_format($currentClaimCoins) }} jeton</strong>
            · onayda varsayılan ödeme: <strong>{{ number_format($currentPayoutTry, 2, ',', '.') }} ₺</strong>
            (API buradan okur)
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-start">
        <form method="post" action="{{ route('admin.finance.start-from-today') }}"
              onsubmit="return confirm('Aktif oran dönemi bugün 00:00\'dan başlasın mı? Önceki açık dönem dün kapanır, özet bugünden açılır.');">
            @csrf
            <button type="submit" class="fin-settings-hero-btn">Kararlaştırılan tarihi bugün yap</button>
        </form>
        <a href="{{ route('admin.finance.index') }}">Finansa dön</a>
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
                                <th>Ödül / eşik ₺</th>
                                <th>Coin→₺</th>
                                <th>Not</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($periods as $p)
                                <tr>
                                    <td>{{ tr_time($p->effective_from, 'd.m.Y') }}</td>
                                    <td>{{ $p->effective_to ? tr_time($p->effective_to, 'd.m.Y') : '∞' }}</td>
                                    <td>{{ number_format($p->store_fee_pct, 1, ',', '.') }}</td>
                                    <td>{{ number_format($p->income_tax_pct, 1, ',', '.') }}</td>
                                    <td>{{ number_format($p->gift_payout_try, 2, ',', '.') }}</td>
                                    <td>{{ number_format($p->coin_to_try, 2, ',', '.') }}</td>
                                    <td class="small text-muted">{{ $p->note ?: '—' }}</td>
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
                        <label class="form-label small">KDV % (otomatik, şimdilik 0)</label>
                        <input type="text" inputmode="decimal" name="kdv_pct" class="form-control form-control-sm"
                               data-fin-num="pct" data-fin-suffix="%" value="0">
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
@endsection

@push('scripts')
@include('admin.finance._number-format')
@endpush
