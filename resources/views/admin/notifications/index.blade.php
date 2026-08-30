@extends('admin.layouts.app')

@section('title', 'Bildirim Yönetimi')

@push('styles')
<style>
.notif-wrap { max-width: 100%; }
.notif-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #334155 100%);
    border-radius: 16px;
    color: #fff;
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.25rem;
}
.notif-hero h3 { color: #fff !important; margin: 0 0 .4rem; font-weight: 650; font-size: 1.55rem; }
.notif-hero p { margin: 0; color: rgba(255,255,255,.85); font-size: .98rem; }
.notif-hero-meta {
    display: inline-flex; align-items: center; gap: .45rem;
    margin-top: .85rem; padding: .45rem .9rem; border-radius: 999px;
    background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
    font-size: .88rem; font-weight: 600;
}
.notif-stat {
    border: 0; border-radius: 14px; box-shadow: 0 6px 18px rgba(15,23,42,.06);
    height: 100%; transition: box-shadow .15s ease, transform .15s ease;
}
.notif-stat .card-body { padding: 1rem 1.1rem; min-height: 5.5rem; }
.notif-stat .label { font-size: .78rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
.notif-stat .value { font-size: 1.75rem; font-weight: 700; color: #0f172a; margin-top: .35rem; font-variant-numeric: tabular-nums; }
.notif-stat-btn:hover .notif-stat { box-shadow: 0 10px 24px rgba(15,23,42,.12); transform: translateY(-1px); }
.notif-stat.is-active { outline: 2px solid #0f172a; }
.notif-card { border: 0; border-radius: 16px; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
.notif-filter-bar { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; margin-bottom: 1rem; }
.notif-filter-bar .input-group-text { background: #fff; border-right: 0; color: #64748b; }
.notif-filter-bar .form-control, .notif-filter-bar .form-select { border-color: #e2e8f0; }
.notif-filter-bar .input-group .form-control { border-left: 0; }
.notif-chips { display: flex; flex-wrap: wrap; gap: .45rem; min-height: 1.5rem; }
.notif-chip {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .25rem .65rem; border-radius: 999px; font-size: .82rem;
    background: #e2e8f0; color: #334155;
}
.notif-chip button {
    border: 0; background: transparent; color: #64748b; line-height: 1; padding: 0; cursor: pointer;
}
.notif-table th {
    white-space: nowrap; font-size: .78rem; color: #64748b;
    text-transform: uppercase; letter-spacing: .02em; padding: .9rem 1rem; border-bottom: 1px solid #e2e8f0;
}
.notif-table td { padding: .95rem 1rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
.notif-table tbody tr:hover { background: #f8fafc; }
.notif-title-cell { color: #0f172a; max-width: 220px; }
.notif-content-cell {
    max-width: 320px; color: #475569; font-size: .92rem; line-height: 1.45;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.notif-type-pill {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .35rem .65rem; border-radius: 999px; font-size: .82rem; font-weight: 600;
}
.notif-type-pill svg { width: 14px; height: 14px; }
.notif-type-fcm { background: #ede9fe; color: #5b21b6; }
.notif-type-sms { background: #fef3c7; color: #92400e; }
.notif-type-email { background: #dbeafe; color: #1d4ed8; }
.notif-type-info, .notif-type-success, .notif-type-warning, .notif-type-error { background: #f1f5f9; color: #475569; }
.notif-creator { display: inline-flex; align-items: center; gap: .5rem; font-size: .92rem; }
.notif-creator-avatar {
    width: 28px; height: 28px; border-radius: 50%; background: #e2e8f0; color: #334155;
    display: inline-flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700;
}
.notif-date-main { font-size: .92rem; color: #0f172a; }
.notif-date-sub { font-size: .78rem; color: #94a3b8; }
.notif-actions { gap: .35rem; align-items: center; justify-content: flex-end; }
.notif-row-btn { display: inline-flex; align-items: center; white-space: nowrap; }
.notif-action-btn {
    width: 34px; height: 34px; padding: 0; display: inline-flex;
    align-items: center; justify-content: center; line-height: 1;
}
.notif-dots { font-size: 1.35rem; line-height: 1; font-weight: 700; color: #475569; margin-top: -2px; }
.notif-send-btn {
    background: #2563eb; border-color: #2563eb; color: #fff !important; font-weight: 600;
}
.notif-live-btn {
    background: #16a34a; border-color: #16a34a; color: #fff !important; font-weight: 600;
}
.notif-live-btn:hover, .notif-live-btn:focus {
    background: #15803d; border-color: #15803d; color: #fff !important;
}
.notif-hero-actions { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; }
.notif-send-btn:hover, .notif-send-btn:focus {
    background: #1d4ed8; border-color: #1d4ed8; color: #fff !important;
}
.notif-status-active { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.notif-status-inactive { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.notif-status-sent { background: #dbeafe; color: #1d4ed8; border: 1px solid #bfdbfe; }
.notif-empty { text-align: center; padding: 3rem 1.5rem; }
.notif-empty-icon { color: #94a3b8; margin-bottom: .75rem; }
.notif-empty-icon svg { width: 42px; height: 42px; }
.notif-loading { opacity: .55; pointer-events: none; transition: opacity .15s ease; }
.notif-wizard-steps { display: flex; gap: .5rem; margin-bottom: 1.25rem; }
.notif-wizard-step {
    flex: 1; text-align: center; padding: .55rem .35rem; border-radius: 10px;
    background: #f1f5f9; color: #64748b; font-size: .82rem; font-weight: 600;
}
.notif-wizard-step.is-active { background: #0f172a; color: #fff; }
.notif-wizard-step.is-done { background: #dcfce7; color: #166534; }
.notif-channel-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; }
@media (max-width: 767px) { .notif-channel-grid { grid-template-columns: 1fr; } }
.notif-channel-card {
    border: 2px solid #e2e8f0; border-radius: 14px; padding: 1rem; cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease; background: #fff; text-align: center;
}
.notif-channel-card:hover { border-color: #94a3b8; }
.notif-channel-card.is-selected { border-color: #0f172a; box-shadow: 0 0 0 3px rgba(15,23,42,.08); }
.notif-channel-card .icon-wrap {
    width: 44px; height: 44px; border-radius: 12px; margin: 0 auto .65rem;
    display: flex; align-items: center; justify-content: center;
}
.notif-channel-card[data-channel="fcm"] .icon-wrap { background: #ede9fe; color: #5b21b6; }
.notif-channel-card[data-channel="sms"] .icon-wrap { background: #fef3c7; color: #92400e; }
.notif-channel-card[data-channel="email"] .icon-wrap { background: #dbeafe; color: #1d4ed8; }
.notif-preview-wrap { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; }
.notif-phone-preview {
    max-width: 280px; margin: 0 auto; background: #0f172a; border-radius: 24px; padding: 12px;
    box-shadow: 0 12px 30px rgba(15,23,42,.18);
}
.notif-phone-screen { background: #f8fafc; border-radius: 16px; padding: 14px; min-height: 160px; }
.notif-push-card {
    background: #fff; border-radius: 12px; padding: .75rem; box-shadow: 0 4px 14px rgba(15,23,42,.08);
}
.notif-push-app { font-size: .72rem; color: #64748b; margin-bottom: .25rem; }
.notif-push-title { font-weight: 700; font-size: .88rem; color: #0f172a; margin-bottom: .2rem; }
.notif-push-body { font-size: .82rem; color: #475569; line-height: 1.4; }
.notif-email-preview, .notif-sms-preview {
    max-width: 360px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0;
    border-radius: 12px; overflow: hidden;
}
.notif-email-head { background: #0f172a; color: #fff; padding: .75rem 1rem; font-size: .85rem; font-weight: 600; }
.notif-email-body { padding: 1rem; }
.notif-sms-bubble {
    display: inline-block; background: #dbeafe; color: #1e3a8a; border-radius: 16px 16px 16px 4px;
    padding: .75rem 1rem; max-width: 100%; font-size: .88rem; line-height: 1.45;
}
.notif-detail-hero {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem 1.1rem; margin-bottom: 1rem;
}
.notif-detail-meta { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
@media (max-width: 575px) { .notif-detail-meta { grid-template-columns: 1fr; } }
.notif-detail-item label { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; margin-bottom: .15rem; }
.notif-detail-item p { margin: 0; color: #0f172a; font-weight: 600; }
.notif-detail-content {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; line-height: 1.6; color: #334155;
}
.notif-show-modal .modal-content { border: 0; border-radius: 16px; overflow: hidden; }
.notif-show-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #fff; padding: 1.25rem 1.5rem;
}
.notif-show-header .modal-title { color: #fff; font-weight: 650; }
.notif-show-header .btn-close { filter: invert(1); opacity: .85; }
.notif-show-body { padding: 1.25rem 1.5rem; }
.notif-show-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 767px) { .notif-show-layout { grid-template-columns: 1fr; } }
.notif-show-preview-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; min-height: 220px;
}
.notif-show-meta-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: .75rem;
}
@media (max-width: 575px) { .notif-show-meta-grid { grid-template-columns: 1fr; } }
.notif-show-note {
    margin-top: 1rem; padding: .75rem .9rem; border-radius: 10px;
    background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; font-size: .88rem;
}
.notif-audience-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-bottom: 1rem; }
@media (max-width: 575px) { .notif-audience-grid { grid-template-columns: 1fr; } }
.notif-audience-card {
    border: 2px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem; cursor: pointer;
    background: #fff; transition: border-color .15s ease, box-shadow .15s ease;
}
.notif-audience-card:hover { border-color: #94a3b8; }
.notif-audience-card.is-selected { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
.notif-audience-card .title { font-weight: 650; color: #0f172a; }
.notif-audience-card .desc { font-size: .82rem; color: #64748b; margin-top: .15rem; }
.notif-template-pick-hint { font-size: .78rem; color: #64748b; margin-top: .35rem; }
.notif-user-search-wrap { position: relative; }
.notif-user-search-results {
    margin-top: .5rem;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    box-shadow: 0 8px 20px rgba(15,23,42,.08);
    max-height: 280px; overflow-y: auto;
}
.notif-user-search-foot {
    padding: .55rem .85rem; font-size: .78rem; color: #64748b;
    background: #f8fafc; border-top: 1px solid #e2e8f0;
    position: sticky; bottom: 0;
}
/* Adıma göre modal boyutu */
.notif-send-modal .modal-dialog {
    transition: max-width .2s ease, min-height .2s ease;
    margin-top: 1.75rem;
}
.notif-send-modal .modal-body { min-height: 0; padding-bottom: 1.25rem; }
.notif-send-modal.notif-step-1 .modal-dialog { max-width: 520px; }
.notif-send-modal.notif-step-2 .modal-dialog { max-width: 680px; }
.notif-send-modal.notif-step-2.notif-audience-specific .modal-dialog { max-width: 760px; }
.notif-send-modal.notif-step-2.notif-has-search-results .modal-dialog { max-width: 820px; }
.notif-send-modal.notif-step-2.notif-has-search-results .notif-user-search-results { max-height: 340px; }
.notif-send-modal.notif-step-3 .modal-dialog { max-width: 900px; }
@media (max-width: 767px) {
    .notif-send-modal .modal-dialog { max-width: calc(100% - 1rem) !important; margin: .5rem auto; }
    .notif-user-search-results { max-height: 240px; }
}
.notif-user-search-item {
    width: 100%; border: 0; background: transparent; text-align: left; padding: .7rem .85rem;
    border-bottom: 1px solid #f1f5f9; cursor: pointer;
}
.notif-user-search-item:last-child { border-bottom: 0; }
.notif-user-search-item:hover { background: #f8fafc; }
.notif-user-search-item.is-disabled { opacity: .55; cursor: not-allowed; }
.notif-user-search-item .name { font-weight: 600; color: #0f172a; font-size: .92rem; }
.notif-user-search-item .meta { font-size: .78rem; color: #64748b; margin-top: .1rem; }
.notif-user-search-item .badge-eligible {
    display: inline-block; margin-top: .25rem; font-size: .72rem; padding: .15rem .45rem;
    border-radius: 999px; background: #dcfce7; color: #166534;
}
.notif-user-search-item .badge-ineligible {
    display: inline-block; margin-top: .25rem; font-size: .72rem; padding: .15rem .45rem;
    border-radius: 999px; background: #fee2e2; color: #991b1b;
}
.notif-selected-users { display: flex; flex-wrap: wrap; gap: .45rem; min-height: 2rem; margin-top: .65rem; }
.notif-selected-head {
    display: flex; align-items: center; justify-content: space-between; gap: .5rem;
    margin-top: .75rem; margin-bottom: .35rem;
}
.notif-selected-count {
    font-size: .82rem; font-weight: 650; color: #1e40af;
    background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 999px; padding: .2rem .65rem;
}
.notif-selected-user {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .3rem .55rem; border-radius: 999px; background: #eff6ff; border: 1px solid #bfdbfe;
    color: #1e40af; font-size: .82rem; font-weight: 600;
}
.notif-selected-user button {
    border: 0; background: transparent; color: #64748b; line-height: 1; padding: 0; cursor: pointer; font-size: 1rem;
}
.notif-user-phone {
    font-weight: 650; color: #0f172a; font-variant-numeric: tabular-nums;
}
.notif-user-contact {
    font-size: .82rem; color: #475569; font-variant-numeric: tabular-nums;
}
.notif-target-panel.d-none { display: none !important; }
.notif-summary-box { color: #0f172a; }
.notif-summary-box dt { margin-bottom: .15rem; }
.notif-summary-box dd { margin-bottom: .75rem; color: #0f172a; }
.notif-summary-box dd:last-child { margin-bottom: 0; }
#summary-target .notif-selected-users { margin-top: .35rem; }
.notif-sent-count-badge {
    display: inline-block; font-size: .78rem; font-weight: 650; color: #1e40af;
    background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 999px; padding: .15rem .55rem;
}
</style>
@endpush

@section('content')
<div class="notif-wrap">
    <div class="notif-hero">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h3>Bildirim Yönetimi</h3>
                <p>Push, SMS ve e-posta gönderim geçmişi</p>
                <div class="notif-hero-meta">
                    <i data-feather="activity"></i>
                    Son 30 günde {{ number_format($stats['recent_30']) }} gönderim · 3 kanal
        </div>
            </div>
            <div class="col-md-4 text-md-end">
            @can('create notifications')
                <div class="notif-hero-actions">
                    @if(!empty($canLiveFlow))
                        <a href="{{ route('admin.notifications.live-flow') }}" class="btn notif-live-btn">
                            <i data-feather="zap" class="me-1"></i> Canlı Bildirim Akışı
                        </a>
                    @endif
                    <button type="button" class="btn notif-send-btn" data-bs-toggle="modal" data-bs-target="#notificationSendModal">
                        <i data-feather="send" class="me-1"></i> Bildirim Gönder
                    </button>
                </div>
            @endcan
        </div>
    </div>
</div>

    <div id="notificationsStats">
        @include('admin.notifications._stats', ['stats' => $stats])
    </div>

    <div class="card notif-card">
                <div class="card-body">
            <div class="notif-filter-bar">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-4">
                        <div class="input-group">
                            <span class="input-group-text"><i data-feather="search"></i></span>
                            <input type="text" class="form-control" id="searchInput" placeholder="Başlık veya mesaj ara..." value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <select class="form-select" id="typeFilter">
                            <option value="">Tüm tipler</option>
                            <option value="email" @selected(request('type') === 'email')>E-posta</option>
                            <option value="sms" @selected(request('type') === 'sms')>SMS</option>
                            <option value="fcm" @selected(request('type') === 'fcm')>Push (FCM)</option>
                            </select>
                        </div>
                    <div class="col-lg-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">Tüm durumlar</option>
                            <option value="active" @selected(request('status') === 'active')>Aktif</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Pasif</option>
                            </select>
                        </div>
                    <div class="col-lg-2">
                        <button type="button" class="btn btn-outline-secondary w-100" id="clearFiltersBtn">Temizle</button>
                        </div>
                </div>
                <div class="notif-chips mt-2" id="filterChips"></div>
                    </div>

            <div class="table-responsive" id="notificationsTableWrap">
                <table class="table notif-table mb-0">
                            <thead>
                                <tr>
                                    <th>Başlık</th>
                            <th>İçerik</th>
                            <th>Tip</th>
                            <th>Durum</th>
                            <th class="d-none d-lg-table-cell">Oluşturan</th>
                            <th>Gönderim</th>
                            <th class="text-end">İşlem</th>
                                </tr>
                            </thead>
                            <tbody id="notificationsTableBody">
                        @include('admin.notifications._rows', ['notifications' => $notifications])
                            </tbody>
                        </table>
                    </div>

                    <div id="notificationsPagination" class="d-flex justify-content-center mt-3">
                        {{ $notifications->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>

{{-- Gönder modal (adım adım) --}}
<div class="modal fade notif-send-modal notif-step-1" id="notificationSendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bildirim Gönder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <form id="notificationSendForm">
                <div class="modal-body">
                    <div class="notif-wizard-steps">
                        <div class="notif-wizard-step is-active" data-step-indicator="1">1. Kanal</div>
                        <div class="notif-wizard-step" data-step-indicator="2">2. İçerik</div>
                        <div class="notif-wizard-step" data-step-indicator="3">3. Önizleme</div>
                    </div>

                    <div class="notif-wizard-panel" data-step="1">
                        <p class="text-muted mb-3">Gönderim kanalını seçin.</p>
                        <div class="notif-channel-grid">
                            <div class="notif-channel-card" data-channel="fcm">
                                <div class="icon-wrap"><i data-feather="smartphone"></i></div>
                                <div class="fw-semibold">Push (FCM)</div>
                                <div class="small text-muted">Mobil bildirim</div>
                            </div>
                            <div class="notif-channel-card" data-channel="sms">
                                <div class="icon-wrap"><i data-feather="message-circle"></i></div>
                                <div class="fw-semibold">SMS</div>
                                <div class="small text-muted">Telefon numarası olanlar</div>
                            </div>
                            <div class="notif-channel-card" data-channel="email">
                                <div class="icon-wrap"><i data-feather="mail"></i></div>
                                <div class="fw-semibold">E-posta</div>
                                <div class="small text-muted">Kayıtlı e-posta adresleri</div>
                            </div>
                        </div>
                        <input type="hidden" id="send-type" name="type" value="">
                    </div>

                    <div class="notif-wizard-panel d-none" data-step="2">
                    <div class="mb-3">
                        <label for="send-template-pick" class="form-label">Kayıtlı şablon <span class="text-muted fw-normal">(isteğe bağlı)</span></label>
                        <select class="form-select" id="send-template-pick">
                            <option value="">— Manuel yaz veya şablon seç —</option>
                        </select>
                        <div class="notif-template-pick-hint">Önce kanal seçin; şablon sadece başlık ve içeriği doldurur. Anlık gönderilir, zamanlama yok.</div>
                        <input type="hidden" id="send-template-id" name="template_id" value="">
                    </div>
                    <div class="mb-3">
                        <label for="send-title" class="form-label">Başlık <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="send-title" name="title" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label for="send-content" class="form-label">İçerik <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="send-content" name="content" rows="3"></textarea>
                    </div>
                        <div class="mb-0">
                            <label class="form-label">Hedef kitle</label>
                            <div class="notif-audience-grid">
                                <div class="notif-audience-card is-selected" data-audience="all">
                                    <div class="title">Tüm kullanıcılar</div>
                                    <div class="desc">Seçilen kanala uygun tüm uygulama kullanıcıları</div>
                                </div>
                                <div class="notif-audience-card" data-audience="specific">
                                    <div class="title">Belirli kullanıcılar</div>
                                    <div class="desc">Birden fazla kullanıcı seçebilirsiniz</div>
                            </div>
                        </div>

                            <div id="send-target-panel" class="notif-target-panel d-none">
                                <label for="send-user-search" class="form-label">Kullanıcı ara ve ekle</label>
                                <div class="form-text mb-2">Listeden bir kullanıcıya tıklayın; aramaya devam edip istediğiniz kadar ekleyebilirsiniz.</div>
                                <div class="notif-user-search-wrap">
                                    <div class="input-group">
                                        <span class="input-group-text"><i data-feather="search"></i></span>
                                        <input type="text" class="form-control" id="send-user-search"
                                               placeholder="Ad, e-posta, telefon veya kullanıcı ID..." autocomplete="off">
                            </div>
                                    <div id="send-user-search-results" class="notif-user-search-results d-none"></div>
                        </div>
                                <div class="notif-selected-head d-none" id="send-selected-head">
                                    <span class="small fw-semibold text-muted">Seçilen kullanıcılar</span>
                                    <span class="notif-selected-count" id="send-selected-count">0 kişi</span>
                    </div>
                                <div class="notif-selected-users" id="send-selected-users">
                                    <span class="text-muted small" id="send-selected-empty">Henüz kullanıcı seçilmedi.</span>
                </div>
                </div>

                            <input type="hidden" id="send-target-users" name="target_users" value="">
                            <div class="form-text mt-2" id="send-type-help">Tüm uygulama kullanıcılarına gönderilir.</div>
    </div>
</div>

                    <div class="notif-wizard-panel d-none" data-step="3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="notif-preview-wrap h-100">
                                    <div class="small text-muted mb-2 fw-semibold text-uppercase">Önizleme</div>
                                    <div id="preview-fcm" class="notif-preview-type d-none">
                                        <div class="notif-phone-preview">
                                            <div class="notif-phone-screen">
                                                <div class="notif-push-card">
                                                    <div class="notif-push-app">Bil Bakalım</div>
                                                    <div class="notif-push-title" id="preview-title-fcm">Başlık</div>
                                                    <div class="notif-push-body" id="preview-body-fcm">İçerik</div>
            </div>
                    </div>
                    </div>
                            </div>
                                    <div id="preview-email" class="notif-preview-type d-none">
                                        <div class="notif-email-preview">
                                            <div class="notif-email-head">Bil Bakalım</div>
                                            <div class="notif-email-body">
                                                <div class="fw-bold mb-2" id="preview-title-email">Başlık</div>
                                                <div class="text-muted" id="preview-body-email">İçerik</div>
                        </div>
                            </div>
                        </div>
                                    <div id="preview-sms" class="notif-preview-type d-none">
                                        <div class="notif-sms-preview p-3">
                                            <div class="notif-sms-bubble">
                                                <div class="fw-semibold mb-1" id="preview-title-sms">Başlık</div>
                                                <div id="preview-body-sms">İçerik</div>
                    </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="border rounded-3 p-3 h-100 bg-light notif-summary-box">
                                    <div class="small text-muted mb-2 fw-semibold text-uppercase">Gönderim özeti</div>
                                    <dl class="mb-0">
                                        <dt class="small text-muted">Kanal</dt>
                                        <dd class="mb-2" id="summary-type">—</dd>
                                        <dt class="small text-muted">Başlık</dt>
                                        <dd class="mb-2" id="summary-title">—</dd>
                                        <dt class="small text-muted">Hedef</dt>
                                        <dd class="mb-0" id="summary-target"><span class="text-muted">Yükleniyor…</span></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-outline-secondary d-none" id="sendWizardPrev">Geri</button>
                    <button type="button" class="btn btn-primary" id="sendWizardNext">İleri</button>
                    <button type="submit" class="btn btn-success d-none" id="sendWizardSubmit">
                        <i data-feather="send" class="me-1"></i> Gönder
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Detay modal --}}
<div class="modal fade notif-show-modal" id="notificationShowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header notif-show-header border-0">
                <div>
                    <div class="small text-white-50 mb-1">Bildirim kaydı</div>
                    <h5 class="modal-title mb-1" id="show-title"></h5>
                    <div id="show-type" class="mt-2"></div>
            </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                    </div>
            <div class="modal-body notif-show-body">
                <div class="notif-show-layout">
                    <div>
                        <div class="small text-muted mb-2 fw-semibold text-uppercase">Kullanıcıya giden görünüm</div>
                        <div class="notif-show-preview-box">
                            <div id="show-preview-fcm" class="notif-preview-type d-none">
                                <div class="notif-phone-preview">
                                    <div class="notif-phone-screen">
                                        <div class="notif-push-card">
                                            <div class="notif-push-app">Bil Bakalım</div>
                                            <div class="notif-push-title" id="show-preview-title-fcm"></div>
                                            <div class="notif-push-body" id="show-preview-body-fcm"></div>
                    </div>
                            </div>
                        </div>
                            </div>
                            <div id="show-preview-email" class="notif-preview-type d-none">
                                <div class="notif-email-preview">
                                    <div class="notif-email-head">Bil Bakalım</div>
                                    <div class="notif-email-body">
                                        <div class="fw-bold mb-2" id="show-preview-title-email"></div>
                                        <div class="text-muted" id="show-preview-body-email"></div>
                        </div>
                    </div>
                        </div>
                            <div id="show-preview-sms" class="notif-preview-type d-none">
                                <div class="notif-sms-preview p-3">
                                    <div class="notif-sms-bubble">
                                        <div class="fw-semibold mb-1" id="show-preview-title-sms"></div>
                                        <div id="show-preview-body-sms"></div>
                    </div>
                </div>
                </div>
        </div>
    </div>
                    <div>
                        <div class="small text-muted mb-2 fw-semibold text-uppercase">Gönderim bilgileri</div>
                        <div class="notif-show-meta-grid">
                            <div class="notif-detail-item">
                                <label>Durum</label>
                                <p id="show-status">—</p>
</div>
                            <div class="notif-detail-item">
                                <label>Gönderilen kişi</label>
                                <p id="show-sent-count">—</p>
            </div>
                            <div class="notif-detail-item">
                                <label>Hedef kitle</label>
                                <p id="show-target">—</p>
                        </div>
                            <div class="notif-detail-item">
                                <label>Gönderim tarihi</label>
                                <p id="show-send-at">—</p>
                    </div>
                            <div class="notif-detail-item">
                                <label>Oluşturan</label>
                                <p id="show-creator">—</p>
                        </div>
                    </div>
                        <div class="mt-3">
                            <label class="form-label fw-semibold">Tam içerik</label>
                            <div class="notif-detail-content" id="show-content"></div>
                        </div>
                    </div>
                        </div>
                <div class="notif-show-note" id="show-sent-note">
                    Bu bildirim gönderildi. Geçmiş kayıtlar yalnızca görüntülenebilir veya silinebilir; düzenleme yapılmaz.
                    </div>
                        </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    </div>
                </div>
            </div>
            </div>

{{-- Silme onay modal --}}
<div class="modal fade" id="notificationDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger">Bildirimi sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
        </div>
            <div class="modal-body">
                <p class="mb-2">Bu bildirimi silmek istediğinize emin misiniz?</p>
                <p class="fw-semibold mb-0" id="delete-notification-title"></p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Evet, sil</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var sendWizardStep = 1;
    var deleteNotificationId = null;
    var searchTimer = null;
    var userSearchTimer = null;
    var audienceMode = 'all';
    var selectedUsers = {};
    var userSearchUrl = @json(route('admin.notifications.users.search'));
    var templatePickerUrl = @json(route('admin.notifications.templates.picker'));
    var sendTemplatesCache = {};

    var typeLabels = { email: 'E-posta', sms: 'SMS', fcm: 'Push (FCM)' };
    var typeHelp = {
        all: {
            email: 'Kayıtlı e-posta adresi olan tüm uygulama kullanıcılarına gönderilir.',
            sms: 'Telefon numarası kayıtlı tüm uygulama kullanıcılarına gönderilir.',
            fcm: 'Mobil cihaz kaydı olan tüm uygulama kullanıcılarına push gönderilir.'
        },
        specific: {
            email: 'Seçtiğiniz kullanıcılardan e-posta adresi olanlara gönderilir.',
            sms: 'Seçtiğiniz kullanıcılardan telefon numarası olanlara gönderilir.',
            fcm: 'Seçtiğiniz kullanıcılardan mobil cihaz kaydı olanlara push gönderilir.'
        }
    };

    function replaceFeather() {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
    }

    function updateAudienceHelp() {
        var type = $('#send-type').val() || 'fcm';
        var mode = audienceMode === 'specific' ? 'specific' : 'all';
        $('#send-type-help').text(typeHelp[mode][type] || '');
    }

    function syncSendModalLayout() {
        var $modal = $('#notificationSendModal');
        $modal.removeClass('notif-step-1 notif-step-2 notif-step-3 notif-audience-specific notif-has-search-results');
        $modal.addClass('notif-step-' + sendWizardStep);
        if (sendWizardStep === 2 && audienceMode === 'specific') {
            $modal.addClass('notif-audience-specific');
        }
        var $results = $('#send-user-search-results');
        if (sendWizardStep === 2 && !$results.hasClass('d-none') && $results.children().length) {
            $modal.addClass('notif-has-search-results');
        }
    }

    function setAudienceMode(mode) {
        audienceMode = mode;
        $('.notif-audience-card').removeClass('is-selected');
        $('.notif-audience-card[data-audience="' + mode + '"]').addClass('is-selected');
        $('#send-target-panel').toggleClass('d-none', mode !== 'specific');
        if (mode === 'all') {
            selectedUsers = {};
            renderSelectedUsers();
        }
        syncTargetUsersInput();
        updateAudienceHelp();
        syncSendModalLayout();
    }

    function syncTargetUsersInput() {
        if (audienceMode === 'all') {
            $('#send-target-users').val('');
            return;
        }
        $('#send-target-users').val(Object.keys(selectedUsers).join(','));
    }

    function renderSelectedUsers() {
        var ids = Object.keys(selectedUsers);
        var $wrap = $('#send-selected-users');
        $wrap.empty();

        if (!ids.length) {
            $('#send-selected-head').addClass('d-none');
            $wrap.html('<span class="text-muted small" id="send-selected-empty">Henüz kullanıcı seçilmedi.</span>');
            syncTargetUsersInput();
            return;
        }

        $('#send-selected-head').removeClass('d-none');
        $('#send-selected-count').text(ids.length + ' kişi');

        ids.forEach(function (id) {
            var user = selectedUsers[id];
            $wrap.append(
                '<span class="notif-selected-user">' +
                    '<span>' + formatUserLine(user, $('#send-type').val(), true) + '</span>' +
                    '<button type="button" data-remove-user="' + user.id + '" aria-label="Kaldır">&times;</button>' +
                '</span>'
            );
        });
        syncTargetUsersInput();
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatUserContact(user, type) {
        type = type || $('#send-type').val() || '';
        if (type === 'sms' && user.phone) return user.phone;
        if (type === 'email' && user.email) return user.email;
        if (user.phone) return user.phone;
        if (user.email) return user.email;
        return '';
    }

    function formatUserLine(user, type, withPhoneClass) {
        var contact = formatUserContact(user, type);
        var html = escapeHtml(user.label) + ' <span class="text-muted">#' + user.id + '</span>';
        if (contact) {
            var contactClass = withPhoneClass ? ' notif-user-phone' : ' notif-user-contact';
            html += ' · <span class="' + contactClass.trim() + '">' + escapeHtml(contact) + '</span>';
        }
        return html;
    }

    function addSelectedUser(user) {
        if (selectedUsers[user.id]) {
            toastr.info('Bu kullanıcı zaten seçili.');
            return;
        }
        selectedUsers[user.id] = user;
        renderSelectedUsers();
        $('#send-user-search').val('').trigger('focus');
        $('#send-user-search-results').addClass('d-none').empty();
        syncSendModalLayout();
        var contact = formatUserContact(user);
        var addedText = user.label + ' #' + user.id + (contact ? ' (' + contact + ')' : '');
        toastr.success(addedText + ' eklendi. Aramaya devam ederek başka kullanıcı da ekleyebilirsiniz.');
    }

    function searchTargetUsers(query) {
        var type = $('#send-type').val() || '';
        $.ajax({
            url: userSearchUrl,
            type: 'GET',
            data: { q: query, type: type },
            success: function (response) {
                renderUserSearchResults(response.users || []);
            },
            error: function () {
                $('#send-user-search-results')
                    .removeClass('d-none')
                    .html('<div class="p-3 text-muted small">Kullanıcı araması başarısız.</div>');
                syncSendModalLayout();
            }
        });
    }

    function renderUserSearchResults(users) {
        var $box = $('#send-user-search-results');
        $box.empty();

        if (!users.length) {
            $box.removeClass('d-none').html('<div class="p-3 text-muted small">Sonuç bulunamadı.</div>');
            return;
        }

        users.forEach(function (user) {
            var already = !!selectedUsers[user.id];
            var type = $('#send-type').val() || '';
            var meta = [];
            if (type === 'sms' && user.email) meta.push(user.email);
            else if (type === 'email' && user.phone) meta.push(user.phone);
            else if (type === 'fcm') {
                if (user.phone) meta.push(user.phone);
                if (user.email) meta.push(user.email);
            }
            var badgeClass = user.eligible ? 'badge-eligible' : 'badge-ineligible';
            var disabledClass = already ? 'is-disabled' : '';

            var metaHtml = meta.length
                ? '<div class="meta">' + escapeHtml(meta.join(' · ')) + '</div>'
                : (!formatUserContact(user, type) ? '<div class="meta">İletişim bilgisi yok</div>' : '');

            $box.append(
                '<button type="button" class="notif-user-search-item ' + disabledClass + '" data-user-id="' + user.id + '">' +
                    '<div class="name">' + formatUserLine(user, type, true) + '</div>' +
                    metaHtml +
                    '<span class="' + badgeClass + '">' + escapeHtml(already ? 'Zaten seçili' : user.eligible_note) + '</span>' +
                '</button>'
            );

            $box.find('[data-user-id="' + user.id + '"]').data('user', user);
        });

        if (users.length >= 25) {
            $box.append('<div class="notif-user-search-foot">İlk 25 sonuç gösteriliyor. Daha net aramak için yazmaya devam edin.</div>');
        }

        $box.removeClass('d-none');
        syncSendModalLayout();
    }

    function getSelectedUsersSummary() {
        var users = Object.values(selectedUsers);
        if (!users.length) {
            return '';
        }
        if (users.length <= 3) {
            return users.map(function (u) {
                var contact = formatUserContact(u);
                return u.label + ' #' + u.id + (contact ? ' (' + contact + ')' : '');
            }).join(', ');
        }
        return users.length + ' kullanıcı seçildi';
    }

    function buildTargetSummaryHtml() {
        if (audienceMode === 'all') {
            return '<strong>Tüm uygulama kullanıcıları</strong>' +
                '<div class="small text-muted mt-1">Seçilen kanala uygun her kullanıcıya ayrı gönderim yapılır.</div>';
        }

        var users = Object.values(selectedUsers);
        if (!users.length) {
            return '<span class="text-warning fw-semibold">Henüz kullanıcı seçilmedi</span>';
        }

        var chips = users.map(function (u) {
            return '<span class="notif-selected-user">' +
                '<span>' + formatUserLine(u, $('#send-type').val(), true) + '</span>' +
            '</span>';
        }).join('');

        return '<strong>' + users.length + ' kişi</strong>' +
            '<div class="small text-muted mt-1">Seçilen kullanıcılardan kanala uygun olanlara gönderilir.</div>' +
            '<div class="notif-selected-users">' + chips + '</div>';
    }

    function getTypePill(type, label) {
        label = label || (typeLabels[type] || type);
        return '<span class="notif-type-pill notif-type-' + type + '">' + label + '</span>';
    }

    function updateFilterChips() {
        var chips = [];
        var search = $('#searchInput').val().trim();
        var type = $('#typeFilter').val();
        var status = $('#statusFilter').val();

        if (search) chips.push({ key: 'search', label: 'Arama: ' + search });
        if (type) chips.push({ key: 'type', label: 'Tip: ' + (typeLabels[type] || type) });
        if (status) chips.push({ key: 'status', label: 'Durum: ' + (status === 'active' ? 'Aktif' : 'Pasif') });

        var html = chips.map(function (chip) {
            return '<span class="notif-chip">' + chip.label +
                '<button type="button" data-chip="' + chip.key + '" aria-label="Kaldır">&times;</button></span>';
        }).join('');

        $('#filterChips').html(html || '<span class="text-muted small">Aktif filtre yok</span>');
        syncStatCards(type);
    }

    function syncStatCards(type) {
        $('.notif-stat').removeClass('is-active');
        if (!type) {
            $('.notif-stat-btn[data-stat-type=""]').find('.notif-stat').addClass('is-active');
                } else {
            $('.notif-stat-btn[data-stat-type="' + type + '"]').find('.notif-stat').addClass('is-active');
        }
    }

    function loadNotifications(page) {
        page = page || 1;
        var search = $('#searchInput').val();
        var type = $('#typeFilter').val();
        var status = $('#statusFilter').val();

        $('#notificationsTableWrap').addClass('notif-loading');

        $.ajax({
            url: '/admin/notifications',
            type: 'GET',
            data: { page: page, search: search, type: type, status: status },
            success: function (response) {
                var $html = $(response);
                $('#notificationsStats').html($html.find('#notificationsStats').html());
                $('#notificationsTableBody').html($html.find('#notificationsTableBody').html());
                $('#notificationsPagination').html($html.find('#notificationsPagination').html());
                updateFilterChips();
                replaceFeather();
            },
            error: function () {
                toastr.error('Veriler yüklenirken bir hata oluştu!');
            },
            complete: function () {
                $('#notificationsTableWrap').removeClass('notif-loading');
            }
        });
    }

    function clearFilters() {
        $('#searchInput').val('');
        $('#typeFilter').val('');
        $('#statusFilter').val('');
        loadNotifications();
    }

    function resetSendWizard() {
        sendWizardStep = 1;
        audienceMode = 'all';
        selectedUsers = {};
        $('#send-type').val('');
        $('#send-title, #send-content, #send-user-search').val('');
        $('#send-target-users, #send-template-id').val('');
        $('#send-template-pick').html('<option value="">— Manuel yaz veya şablon seç —</option>');
        sendTemplatesCache = {};
        $('#send-user-search-results').addClass('d-none').empty();
        renderSelectedUsers();
        setAudienceMode('all');
        $('.notif-channel-card').removeClass('is-selected');
        $('.notif-wizard-step').removeClass('is-active is-done');
        $('.notif-wizard-step[data-step-indicator="1"]').addClass('is-active');
        $('.notif-wizard-panel').addClass('d-none');
        $('.notif-wizard-panel[data-step="1"]').removeClass('d-none');
        $('#sendWizardPrev, #sendWizardSubmit').addClass('d-none');
        $('#sendWizardNext').removeClass('d-none').text('İleri');
        $('#summary-type').text('—');
        $('#summary-title').text('—');
        $('#summary-target').html('<span class="text-muted">Tüm uygulama kullanıcıları</span>');
        syncSendModalLayout();
    }

    function setSendWizardStep(step) {
        sendWizardStep = step;
        $('.notif-wizard-panel').addClass('d-none');
        $('.notif-wizard-panel[data-step="' + step + '"]').removeClass('d-none');
        $('.notif-wizard-step').each(function () {
            var n = parseInt($(this).data('step-indicator'), 10);
            $(this).removeClass('is-active is-done');
            if (n < step) $(this).addClass('is-done');
            if (n === step) $(this).addClass('is-active');
        });
        $('#sendWizardPrev').toggleClass('d-none', step === 1);
        $('#sendWizardNext').toggleClass('d-none', step === 3);
        $('#sendWizardSubmit').toggleClass('d-none', step !== 3);
        if (step === 3) updateSendPreview();
        if (step === 2) {
            var channel = $('#send-type').val();
            if (channel) loadSendTemplates(channel);
        }
        syncSendModalLayout();
    }

    function loadSendTemplates(channel, selectedId) {
        if (!channel) return;
        var $select = $('#send-template-pick');
        var current = selectedId || $('#send-template-id').val() || $select.val();

        if (sendTemplatesCache[channel]) {
            renderSendTemplateOptions(sendTemplatesCache[channel], current);
            return;
        }

        $select.prop('disabled', true).html('<option value="">Şablonlar yükleniyor…</option>');

        $.get(templatePickerUrl, { channel: channel }, function (res) {
            var list = (res.success && res.templates) ? res.templates : [];
            sendTemplatesCache[channel] = list;
            renderSendTemplateOptions(list, current);
        }).fail(function () {
            $select.html('<option value="">Şablon listesi alınamadı</option>');
        }).always(function () {
            $select.prop('disabled', false);
        });
    }

    function renderSendTemplateOptions(templates, selectedId) {
        var $select = $('#send-template-pick');
        $select.empty().append('<option value="">— Manuel yaz veya şablon seç —</option>');
        templates.forEach(function (t) {
            $select.append(
                $('<option></option>').val(t.id).text(t.name + ' · ' + (t.title || '').substring(0, 40))
            );
        });
        if (selectedId) {
            $select.val(String(selectedId));
        }
    }

    function applySendTemplate(templateId) {
        var channel = $('#send-type').val();
        var list = sendTemplatesCache[channel] || [];
        var tpl = list.find(function (t) { return String(t.id) === String(templateId); });
        if (!tpl) {
            $('#send-template-id').val('');
            return;
        }
        $('#send-template-id').val(tpl.id);
        $('#send-title').val(tpl.title || '');
        $('#send-content').val(tpl.content || '');
        if (sendWizardStep === 3) updateSendPreview();
    }

    function updateSendPreview() {
        var type = $('#send-type').val();
        var title = $('#send-title').val().trim() || 'Başlık';
        var content = $('#send-content').val().trim() || 'İçerik';

        $('#notificationSendModal .notif-preview-type').addClass('d-none');
        $('#notificationSendModal #preview-' + type).removeClass('d-none');

        $('#preview-title-fcm, #preview-title-email, #preview-title-sms').text(title);
        $('#preview-body-fcm, #preview-body-email, #preview-body-sms').text(content);
        $('#summary-type').html(getTypePill(type));
        $('#summary-title').text(title);
        $('#summary-target').html(buildTargetSummaryHtml());
    }

    function validateSendStep(step) {
        if (step === 1 && !$('#send-type').val()) {
            toastr.warning('Lütfen bir gönderim kanalı seçin.');
            return false;
        }
        if (step === 2) {
            if (!$('#send-title').val().trim()) {
                toastr.warning('Başlık zorunludur.');
                return false;
            }
            if (!$('#send-content').val().trim()) {
                toastr.warning('İçerik zorunludur.');
                return false;
            }
            if (audienceMode === 'specific' && !Object.keys(selectedUsers).length) {
                toastr.warning('Belirli kullanıcılar modunda en az bir kullanıcı seçmelisiniz.');
                return false;
            }
        }
        return true;
    }

    $(document).ready(function () {
        replaceFeather();
        updateFilterChips();

        $('#searchInput').on('keyup', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { loadNotifications(); }, 350);
        });
        $('#typeFilter, #statusFilter').on('change', function () { loadNotifications(); });
        $('#clearFiltersBtn').on('click', clearFilters);

        $(document).on('click', '.notif-chip button', function () {
            var chip = $(this).data('chip');
            if (chip === 'search') $('#searchInput').val('');
            if (chip === 'type') $('#typeFilter').val('');
            if (chip === 'status') $('#statusFilter').val('');
            loadNotifications();
        });

        $(document).on('click', '.notif-stat-btn', function () {
            $('#typeFilter').val($(this).data('stat-type') || '');
            loadNotifications();
        });

        $(document).on('click', '.pagination a', function (e) {
            e.preventDefault();
            var href = $(this).attr('href') || '';
            var match = href.match(/page=(\d+)/);
            loadNotifications(match ? match[1] : 1);
        });

        // Gönder wizard
        $('#notificationSendModal').on('hidden.bs.modal', resetSendWizard);

        $(document).on('click', '.notif-channel-card', function () {
            $('.notif-channel-card').removeClass('is-selected');
            $(this).addClass('is-selected');
            var channel = $(this).data('channel');
            $('#send-type').val(channel);
            updateAudienceHelp();
            loadSendTemplates(channel);
            if ($('#send-user-search').val().trim().length >= 2) {
                searchTargetUsers($('#send-user-search').val().trim());
            }
        });

        $(document).on('click', '.notif-audience-card', function () {
            setAudienceMode($(this).data('audience'));
            if (sendWizardStep === 3) updateSendPreview();
        });

        $('#send-template-pick').on('change', function () {
            var id = $(this).val();
            if (!id) {
                $('#send-template-id').val('');
                return;
            }
            applySendTemplate(id);
        });

        $('#send-user-search').on('keyup', function () {
            var q = $(this).val().trim();
            clearTimeout(userSearchTimer);
            if (q.length < 2 && !/^\d+$/.test(q)) {
                $('#send-user-search-results').addClass('d-none').empty();
                syncSendModalLayout();
                return;
            }
            userSearchTimer = setTimeout(function () {
                searchTargetUsers(q);
            }, 300);
        });

        $(document).on('click', '.notif-user-search-item:not(.is-disabled)', function () {
            addSelectedUser($(this).data('user'));
        });

        $(document).on('click', '[data-remove-user]', function () {
            delete selectedUsers[$(this).data('remove-user')];
            renderSelectedUsers();
            if (sendWizardStep === 3) updateSendPreview();
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.notif-user-search-wrap').length) {
                if (!$('#send-user-search-results').hasClass('d-none')) {
                    $('#send-user-search-results').addClass('d-none');
                    syncSendModalLayout();
                }
            }
        });

        $('#sendWizardNext').on('click', function () {
            if (!validateSendStep(sendWizardStep)) return;
            setSendWizardStep(sendWizardStep + 1);
        });

        $('#sendWizardPrev').on('click', function () {
            setSendWizardStep(sendWizardStep - 1);
        });

        $('#send-title, #send-content').on('input', function () {
            if (sendWizardStep === 3) updateSendPreview();
        });

        $('#notificationSendForm').on('submit', function (e) {
            e.preventDefault();
            if (!validateSendStep(2)) {
                setSendWizardStep(2);
                return;
            }

            var formData = new FormData(this);
            $('#sendWizardSubmit').prop('disabled', true);

        $.ajax({
                url: '/admin/notifications/send',
                type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
                success: function (response) {
                if (response.success) {
                        $('#notificationSendModal').modal('hide');
                        toastr.success(response.message + ' (' + response.sent_count + ' kişiye gönderildi)');
                    loadNotifications();
                } else {
                    toastr.error(response.message);
                }
            },
                error: function (xhr) {
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        var msgs = [];
                        $.each(xhr.responseJSON.errors, function (_, v) { msgs.push(v[0]); });
                        toastr.error(msgs.join('<br>'));
                } else {
                        toastr.error('Bildirim gönderilirken bir hata oluştu!');
                }
                },
                complete: function () {
                    $('#sendWizardSubmit').prop('disabled', false);
            }
        });
    });

        // Detay — API'den güncel kayıt
    $('#notificationShowModal').on('show.bs.modal', function (event) {
            var btn = $(event.relatedTarget);
            var id = btn.data('id');
            var type = btn.data('type');
            var title = btn.data('title') || '';
            var content = btn.data('content') || '';

        $('#show-title').text(title);
        $('#show-content').text(content);
            $('#show-type').html(getTypePill(type, btn.data('type-label')));
            $('#show-creator').text('Yükleniyor…');
            $('#show-sent-count').text('Yükleniyor…');
            $('#show-target').text('Yükleniyor…');
            $('#show-send-at').text('—');
            $('#show-status').html('<span class="badge bg-secondary">Yükleniyor…</span>');

            $('.notif-show-preview-box .notif-preview-type').addClass('d-none');
            $('#show-preview-' + type).removeClass('d-none');
            $('#show-preview-title-fcm, #show-preview-title-email, #show-preview-title-sms').text(title);
            $('#show-preview-body-fcm, #show-preview-body-email, #show-preview-body-sms').text(content);

            if (!id) {
                return;
            }

    $.ajax({
                url: '/admin/notifications/' + id,
        type: 'GET',
                success: function (response) {
                    if (!response.success || !response.notification) {
                        return;
                    }

                    var n = response.notification;
                    var sentCount = Number(n.sent_count || 0);
                    var isSent = sentCount > 0 || !!n.send_at;
                    var targetUsers = Array.isArray(n.target_users) ? n.target_users : [];
                    var targetDetail = Array.isArray(response.target_users_detail) ? response.target_users_detail : [];
                    var creatorName = (n.creator && n.creator.name) ? n.creator.name : '—';

                    $('#show-creator').text(creatorName);
                    $('#show-sent-count').text(sentCount.toLocaleString('tr-TR') + ' kişi');

                    if (targetDetail.length === 1) {
                        var u = targetDetail[0];
                        $('#show-target').text((u.name || 'Kullanıcı') + ' (#' + u.id + ')');
                    } else if (targetDetail.length > 1) {
                        $('#show-target').text(targetDetail.map(function (u) {
                            return (u.name || 'Kullanıcı') + ' (#' + u.id + ')';
                        }).join(', '));
                    } else if (targetUsers.length) {
                        $('#show-target').text(targetUsers.length + ' belirli kullanıcı');
                    } else {
                        $('#show-target').text('Tüm uygulama kullanıcıları');
                    }

                    if (isSent) {
                        $('#show-status').html('<span class="badge notif-status-sent">Gönderildi</span>');
                        $('#show-sent-note').removeClass('d-none');
                    } else {
                        $('#show-status').html('<span class="badge notif-status-active">Kayıt</span>');
                        $('#show-sent-note').addClass('d-none');
                    }

                    if (n.send_at) {
                        $('#show-send-at').text(new Date(n.send_at).toLocaleString('tr-TR'));
                    } else if (n.created_at) {
                        $('#show-send-at').text(new Date(n.created_at).toLocaleString('tr-TR'));
                    } else {
                        $('#show-send-at').text('—');
                    }
                },
                error: function () {
                    var isSent = btn.data('is-sent') == 1;
                    var sendAt = btn.data('send-at');
                    var createdAt = btn.data('created-at');

                    $('#show-creator').text(btn.data('creator') || '—');
                    $('#show-sent-count').text(Number(btn.data('sent-count') || 0).toLocaleString('tr-TR') + ' kişi');
                    $('#show-target').text('—');

                    if (isSent) {
                        $('#show-status').html('<span class="badge notif-status-sent">Gönderildi</span>');
                        $('#show-sent-note').removeClass('d-none');
                    } else {
                        $('#show-status').html('<span class="badge notif-status-active">Kayıt</span>');
                        $('#show-sent-note').addClass('d-none');
                    }

                    if (sendAt) {
                        $('#show-send-at').text(new Date(sendAt).toLocaleString('tr-TR'));
                    } else if (createdAt) {
                        $('#show-send-at').text(new Date(createdAt).toLocaleString('tr-TR'));
                    } else {
                        $('#show-send-at').text('—');
                    }
                }
            });
        });

        // Silme
        $(document).on('click', '.notif-delete-btn', function () {
            deleteNotificationId = $(this).data('id');
            $('#delete-notification-title').text('"' + $(this).data('title') + '"');
            $('#notificationDeleteModal').modal('show');
        });

        $('#confirmDeleteBtn').on('click', function () {
            if (!deleteNotificationId) return;

        $.ajax({
                url: '/admin/notifications/' + deleteNotificationId,
            type: 'DELETE',
                data: { _token: $('meta[name="csrf-token"]').attr('content') },
                success: function (response) {
                    $('#notificationDeleteModal').modal('hide');
                if (response.success) {
                    toastr.success(response.message);
                    loadNotifications();
                } else {
                    toastr.error(response.message);
                }
            },
                error: function () {
                toastr.error('Bildirim silinirken bir hata oluştu!');
            }
        });
        });
    });
})();
</script>
@endpush
@include('admin.layouts.footer')
