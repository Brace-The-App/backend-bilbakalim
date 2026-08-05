@extends('admin.layouts.app')

@section('title', 'Ödül Talepleri')

@section('content')
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12">
            <h3 class="mb-1">Ödül Talepleri</h3>
            <p class="text-muted mb-0 small">
                Hediye talepleri yalnızca <strong>düello jetonundan</strong> (duel_earned) düşülür.
            </p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Ödül Talepleri Listesi</h5>
                <div class="small text-muted">
                    Akış: talep anında −{{ \App\Services\FinanceService::giftClaimMinCoins() }} düello → panel → onayda hediye (jeton dokunulmaz) / redde +{{ \App\Services\FinanceService::giftClaimMinCoins() }} iade · günde max {{ (int) config('app.gift_claim_daily_limit', 1) }} talep
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kullanıcı</th>
                                <th>Marka</th>
                                <th>Tip</th>
                                <th>Talep</th>
                                <th>Düello hareketi</th>
                                <th>Güncel düello</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $request)
                            @php
                                $minClaim = \App\Services\FinanceService::giftClaimMinCoins();
                                $meta = is_array($request->metadata) ? $request->metadata : [];
                                $claimedAmount = $request->reward_type === 'duel'
                                    ? (int) ($meta['claimed_amount'] ?? $minClaim)
                                    : (int) $request->coins_earned;

                                $duelBefore = isset($meta['duel_earned_at_claim_before'])
                                    ? (int) $meta['duel_earned_at_claim_before']
                                    : (isset($meta['duel_earned_coins_before']) ? (int) $meta['duel_earned_coins_before'] : null);
                                $duelAfterClaim = isset($meta['duel_earned_at_claim_after'])
                                    ? (int) $meta['duel_earned_at_claim_after']
                                    : null;
                                $refunded = isset($meta['refunded_amount']) ? (int) $meta['refunded_amount'] : null;
                                $duelAfterRefund = isset($meta['duel_earned_after_refund'])
                                    ? (int) $meta['duel_earned_after_refund']
                                    : null;

                                $currentDuel = (int) ($request->user->duel_earned_coins ?? 0);
                            @endphp
                            <tr>
                                <td>{{ $request->id }}</td>
                                <td>
                                    {{ $request->user ? trim($request->user->name . ' ' . ($request->user->surname ?? '')) : 'Bilinmeyen' }}
                                    <br>
                                    <small class="text-muted">{{ $request->user->email ?? '' }}</small>
                                    @if($request->user && $request->user->phone)
                                        <br>
                                        <small class="text-muted">Tel: {{ $request->user->phone }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($request->gift_card_store_image_url || $request->gift_card_store_type)
                                        <div class="d-flex align-items-center gap-2">
                                            @if($request->gift_card_store_image_url)
                                                <img src="{{ $request->gift_card_store_image_url }}" alt="Marka" class="reward-store-thumb" style="width:64px;height:40px;object-fit:contain;background:#fff;border:1px solid #eee;border-radius:4px;padding:2px;">
                                            @endif
                                            @if($request->gift_card_store_type)
                                                <span class="text-muted text-capitalize">{{ $request->gift_card_store_type }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($request->reward_type === 'daily')
                                        <span class="badge bg-info">Günlük</span>
                                    @elseif($request->reward_type === 'weekly')
                                        <span class="badge bg-primary">Haftalık</span>
                                    @elseif($request->reward_type === 'duel')
                                        <span class="badge bg-success">Meydan Okuma</span>
                                    @else
                                        <span class="badge bg-warning">Turnuva</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ number_format($claimedAmount, 0, ',', '.') }}</strong>
                                    <i class="fa fa-coins text-warning"></i>
                                    <br><small class="text-muted">hediye eşiği</small>
                                </td>
                                <td class="small" style="min-width:140px;">
                                    @if($request->reward_type === 'duel' && $duelBefore !== null)
                                        <div><span class="text-muted">Önce:</span> <strong>{{ number_format($duelBefore, 0, ',', '.') }}</strong></div>
                                        @if($duelAfterClaim !== null)
                                            <div>
                                                <span class="text-muted">Kalan:</span>
                                                <strong class="text-primary">{{ number_format($duelAfterClaim, 0, ',', '.') }}</strong>
                                                <span class="text-muted">(−{{ number_format($claimedAmount, 0, ',', '.') }})</span>
                                            </div>
                                        @endif
                                        @if($request->status === 'rejected' && $refunded)
                                            <div class="text-danger mt-1">
                                                İade +{{ number_format($refunded, 0, ',', '.') }}
                                                @if($duelAfterRefund !== null)
                                                    → <strong>{{ number_format($duelAfterRefund, 0, ',', '.') }}</strong>
                                                @endif
                                            </div>
                                        @endif
                                    @elseif($request->reward_type === 'duel')
                                        <span class="text-muted">Eski kayıt</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <strong class="{{ $currentDuel <= 0 ? 'text-muted' : 'text-success' }}">{{ number_format($currentDuel, 0, ',', '.') }}</strong>
                                    <br><small class="text-muted">canlı bakiye</small>
                                </td>
                                <td class="small">
                                    <div>{{ $request->reward_date ? \Carbon\Carbon::parse($request->reward_date)->format('d.m.Y') : '-' }}</div>
                                    <div class="text-muted">{{ $request->requested_at ? $request->requested_at->format('d.m.Y H:i') : '-' }}</div>
                                </td>
                                <td>
                                    @if($request->status === 'pending')
                                        <span class="badge bg-warning">Beklemede</span>
                                    @elseif($request->status === 'approved')
                                        <span class="badge bg-success">Ödül Verildi</span>
                                    @else
                                        <span class="badge bg-danger">Reddedildi</span>
                                    @endif
                                </td>
                                <td>
                                    @if($request->status === 'pending')
                                        <button type="button" class="btn btn-sm btn-success" onclick="approveRequest({{ $request->id }})">
                                            Ödül Verildi
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="rejectRequest({{ $request->id }})">
                                            Reddet
                                        </button>
                                    @else
                                        <span class="text-muted">İşlem tamamlandı</span>
                                        @if($request->approver)
                                            <br><small class="text-muted">Onaylayan: {{ $request->approver->name }}</small>
                                        @endif
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1" onclick="deleteRequest({{ $request->id }})" title="Talebi sil">
                                        Sil
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-3">
                    {{ $requests->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.page-title {
    margin-top: 1rem !important;
}
.reward-store-thumb {
    transition: transform 0.15s ease;
    cursor: pointer;
}
.reward-store-thumb:hover {
    transform: scale(1.15);
    z-index: 2;
    position: relative;
}
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    toastr.options = {
        "closeButton": true,
        "debug": false,
        "newestOnTop": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "preventDuplicates": false,
        "onclick": null,
        "showDuration": "300",
        "hideDuration": "1000",
        "timeOut": "5000",
        "extendedTimeOut": "1000",
        "showEasing": "swing",
        "hideEasing": "linear",
        "showMethod": "fadeIn",
        "hideMethod": "fadeOut"
    };
});

function approveRequest(id) {
    var method = prompt('Ödeme yöntemi yazın: multinet / papara / havale / parsela / other', 'multinet');
    if (method === null) return;
    method = String(method).trim().toLowerCase();
    var allowed = ['multinet', 'papara', 'havale', 'parsela', 'other'];
    if (allowed.indexOf(method) === -1) {
        toastr.error('Geçersiz yöntem. multinet, papara, havale, parsela veya other yazın.');
        return;
    }
    var amountRaw = prompt('Ödenen tutar (₺). Boş bırakırsan dönem ayarı kullanılır.', '');
    if (amountRaw === null) return;
    var payload = {
        _token: '{{ csrf_token() }}',
        payout_method: method
    };
    if (String(amountRaw).trim() !== '') {
        var raw = String(amountRaw).trim().replace(/\s/g, '');
        if (raw.indexOf(',') >= 0) {
            raw = raw.replace(/\./g, '').replace(',', '.');
        }
        payload.payout_amount = raw;
    }
    if (!confirm('Onaylansın mı? (' + method + ')')) return;

    $.ajax({
        url: '/admin/reward-requests/' + id + '/approve',
        type: 'POST',
        data: payload,
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                location.reload();
            } else {
                toastr.error(response.message);
            }
        },
        error: function(xhr) {
            if (xhr.status === 422) {
                var response = xhr.responseJSON;
                var msg = (response && response.message) ? response.message : 'Doğrulama hatası';
                if (response && response.errors) {
                    var first = Object.values(response.errors).flat()[0];
                    if (first) msg = first;
                }
                toastr.error(msg);
            } else {
                toastr.error('Ödül talebi onaylanırken bir hata oluştu!');
            }
        }
    });
}

function rejectRequest(id) {
    if (confirm('Bu ödül talebini reddetmek istediğinizden emin misiniz?')) {
        $.ajax({
            url: '/admin/reward-requests/' + id + '/reject',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    location.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422) {
                    var response = xhr.responseJSON;
                    if (response.message) {
                        toastr.error(response.message);
                    }
                } else {
                    toastr.error('Ödül talebi reddedilirken bir hata oluştu!');
                }
            }
        });
    }
}

function deleteRequest(id) {
    if (!confirm('Bu ödül talebini kalıcı olarak silmek istediğinizden emin misiniz?')) {
        return;
    }

    $.ajax({
        url: '/admin/reward-requests/' + id,
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            _method: 'DELETE'
        },
        success: function(response) {
            if (response.success) {
                toastr.success(response.message);
                location.reload();
            } else {
                toastr.error(response.message || 'Silinemedi.');
            }
        },
        error: function() {
            toastr.error('Ödül talebi silinirken bir hata oluştu!');
        }
    });
}
</script>
@endpush

@endsection

@include('admin.layouts.footer')

