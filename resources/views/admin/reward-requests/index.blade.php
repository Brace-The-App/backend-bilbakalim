@extends('admin.layouts.app')

@section('title', 'Ödül Talepleri')

@section('content')
@php
    $defaultGiftPayoutTry = round((float) (\App\Services\FinanceService::giftPayoutTry() ?: 250), 2);
    if ($defaultGiftPayoutTry <= 0) {
        $defaultGiftPayoutTry = 250;
    }
@endphp
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12">
            <h3 class="mb-1">Ödül Talepleri</h3>
            <p class="text-muted mb-0 small">
                Hediye talepleri yalnızca <strong>düello jetonundan</strong> düşülür.
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
                <form method="get" class="row g-2 align-items-end mb-3">
                    <div class="col-auto">
                        <label class="form-label small mb-1">Durum</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Tümü ({{ number_format($counts['all'] ?? 0) }})</option>
                            <option value="pending" @selected(($status ?? '') === 'pending')>Beklemede ({{ number_format($counts['pending'] ?? 0) }})</option>
                            <option value="approved" @selected(($status ?? '') === 'approved')>Ödül verildi ({{ number_format($counts['approved'] ?? 0) }})</option>
                            <option value="rejected" @selected(($status ?? '') === 'rejected')>Reddedildi ({{ number_format($counts['rejected'] ?? 0) }})</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Ara</label>
                        <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control form-control-sm" placeholder="ID, kullanıcı, e-posta, telefon">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-dark">Filtrele</button>
                        <a href="{{ route('admin.reward-requests.index') }}" class="btn btn-sm btn-outline-secondary">Temizle</a>
                    </div>
                </form>

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
                                <th>Talep tarihi</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($requests as $request)
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
                                    <div>{{ tr_time($request->created_at, 'd.m.Y H:i') }}</div>
                                    @if($request->requested_at && $request->created_at && !$request->requested_at->eq($request->created_at))
                                        <div class="text-muted">istenme: {{ tr_time($request->requested_at, 'd.m.Y H:i') }}</div>
                                    @endif
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
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-1"
                                            onclick="deleteRequest({{ (int) $request->id }}, '{{ $request->status }}')"
                                            title="Talebi sil">
                                        Sil
                                    </button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">Kayıt yok.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <!-- Pagination: 10'arlı, filtre korunur -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                    <div class="small text-muted">
                        @if($requests->total() > 0)
                            {{ $requests->firstItem() }}–{{ $requests->lastItem() }} / {{ $requests->total() }}
                        @endif
                        · en yeni talep önce (oluşturma tarihi)
                    </div>
                    <div>
                        {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Ödül onay modal --}}
<div class="modal fade" id="rewardApproveModal" tabindex="-1" aria-labelledby="rewardApproveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:0;border-radius:14px;overflow:hidden">
            <div class="modal-header border-0 pb-0" style="background:linear-gradient(135deg,#0f172a,#1e293b);color:#fff">
                <div>
                    <h5 class="modal-title mb-1" id="rewardApproveModalLabel" style="color:#fff !important">Ödül ver</h5>
                    <div class="small" style="color:#fff !important;opacity:.85">Talep #<span id="approveRequestIdLabel">—</span></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="rewardApproveForm">
                <div class="modal-body pt-3">
                    <input type="hidden" id="approveRequestId" value="">
                    <div class="mb-3">
                        <label for="approvePayoutMethod" class="form-label">Ödeme yöntemi</label>
                        <select id="approvePayoutMethod" class="form-select" required>
                            <option value="multinet" selected>Multinet</option>
                            <option value="papara">Papara</option>
                            <option value="havale">Havale</option>
                            <option value="other">Diğer</option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label for="approvePayoutAmount" class="form-label">Ödenen tutar (₺)</label>
                        <div class="input-group">
                            <input type="number" id="approvePayoutAmount" class="form-control" min="0" max="999999" step="0.01"
                                   value="{{ $defaultGiftPayoutTry }}" inputmode="decimal">
                            <span class="input-group-text">₺</span>
                        </div>
                        <div class="form-text">Varsayılan {{ number_format($defaultGiftPayoutTry, 0, ',', '.') }} ₺ — gerekirse elle değiştirilebilir.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                    <button type="submit" class="btn btn-success" id="approveSubmitBtn">
                        Ödülü onayla
                    </button>
                </div>
            </form>
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
@php
    $defaultGiftPayoutTryJs = (float) $defaultGiftPayoutTry;
@endphp
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

    $('#rewardApproveForm').on('submit', function (e) {
        e.preventDefault();
        submitApproveRequest();
    });
});

var defaultGiftPayoutTry = {{ json_encode($defaultGiftPayoutTryJs) }};
var approveModal = null;

function getApproveModal() {
    if (!approveModal) {
        var el = document.getElementById('rewardApproveModal');
        approveModal = bootstrap.Modal.getOrCreateInstance(el);
    }
    return approveModal;
}

function approveRequest(id) {
    $('#approveRequestId').val(id);
    $('#approveRequestIdLabel').text(id);
    $('#approvePayoutMethod').val('multinet');
    $('#approvePayoutAmount').val(defaultGiftPayoutTry);
    getApproveModal().show();
    setTimeout(function () { $('#approvePayoutMethod').trigger('focus'); }, 250);
}

function submitApproveRequest() {
    var id = $('#approveRequestId').val();
    var method = String($('#approvePayoutMethod').val() || '').trim().toLowerCase();
    var amountRaw = String($('#approvePayoutAmount').val() || '').trim();
    var allowed = ['multinet', 'papara', 'havale', 'other'];

    if (!id) {
        toastr.error('Talep bulunamadı.');
        return;
    }
    if (allowed.indexOf(method) === -1) {
        toastr.error('Geçersiz ödeme yöntemi.');
        return;
    }

    var payload = {
        _token: '{{ csrf_token() }}',
        payout_method: method
    };

    if (amountRaw !== '') {
        var raw = amountRaw.replace(/\s/g, '');
        if (raw.indexOf(',') >= 0) {
            raw = raw.replace(/\./g, '').replace(',', '.');
        }
        var num = parseFloat(raw);
        if (isNaN(num) || num < 0) {
            toastr.error('Geçerli bir tutar girin.');
            return;
        }
        payload.payout_amount = raw;
    } else {
        toastr.error('Ödenen tutarı girin.');
        return;
    }

    var $btn = $('#approveSubmitBtn');
    $btn.prop('disabled', true).text('Onaylanıyor…');

    $.ajax({
        url: '/admin/reward-requests/' + id + '/approve',
        type: 'POST',
        data: payload,
        success: function(response) {
            if (response.success) {
                getApproveModal().hide();
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
        },
        complete: function () {
            $btn.prop('disabled', false).text('Ödülü onayla');
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

function deleteRequest(id, status) {
    status = status || '';
    var msg = 'Bu ödül talebini kalıcı olarak silmek istediğinizden emin misiniz?';
    if (status === 'approved') {
        msg = 'Onaylı talep silinecek.\n\n• Jeton iade EDİLMEZ\n• Finans gider kaydı kaldırılır\n\nDevam edilsin mi?';
    } else if (status === 'pending') {
        msg = 'Bekleyen talep silinecek.\n\n• Talepte düşülen düello jetonları iade edilir\n\nDevam edilsin mi?';
    } else if (status === 'rejected') {
        msg = 'Reddedilmiş talep silinecek.\n\n• Jeton zaten iade edilmiş olmalı; ekstra iade yok\n\nDevam edilsin mi?';
    }
    if (!confirm(msg)) {
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

