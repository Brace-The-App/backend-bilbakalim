@extends('admin.layouts.app')

@section('title', 'Ödül Talepleri')

@section('content')
<div class="page-title" style="margin-top: 1rem;">
    <div class="row align-items-center">
        <div class="col-12">
            <h3 class="mb-1">Ödül Talepleri</h3>
            <p class="text-muted mb-0 small">Bekleyen talepler ve onay işlemleri</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card">
            <div class="card-header">
                <h5>Ödül Talepleri Listesi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kullanıcı</th>
                                <th>Seçilen Marka</th>
                                <th>Ödül Tipi</th>
                                <th>Talep edilen</th>
                                <th>Güncel düello coin</th>
                                <th>Tarih</th>
                                <th>Talep Tarihi</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($requests as $request)
                            @php
                                $minClaim = (int) config('app.gift_claim_min_coins', 100);
                                // Meydan okuma: her zaman eşik (claimed_amount); eski kayıtlarda wallet snapshot yazılmış olabilir
                                $claimedAmount = $request->reward_type === 'duel'
                                    ? (int) data_get($request->metadata, 'claimed_amount', $minClaim)
                                    : (int) $request->coins_earned;
                                // Canlı bakiye — talepte −100 / redde +100 burada yansır; onay anı snapshot gösterme
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
                                </td>
                                <td>
                                    <strong class="{{ $currentDuel <= 0 ? 'text-muted' : 'text-success' }}">{{ number_format($currentDuel, 0, ',', '.') }}</strong>
                                </td>
                                <td>{{ $request->reward_date ? \Carbon\Carbon::parse($request->reward_date)->format('d.m.Y') : '-' }}</td>
                                <td>{{ $request->requested_at ? $request->requested_at->format('d.m.Y H:i') : '-' }}</td>
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
    if (confirm('Bu ödül talebini onaylamak istediğinizden emin misiniz?')) {
        $.ajax({
            url: '/admin/reward-requests/' + id + '/approve',
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
                    toastr.error('Ödül talebi onaylanırken bir hata oluştu!');
                }
            }
        });
    }
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

