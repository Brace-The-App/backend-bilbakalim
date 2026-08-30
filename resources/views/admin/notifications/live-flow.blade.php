@extends('admin.layouts.app')

@section('title', 'Canlı Bildirim Akışı')

@push('styles')
<style>
.nlf-wrap { max-width: 100%; --nlf-green: #10b981; --nlf-green-d: #059669; --nlf-ink: #0f172a; }
.nlf-hero {
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, #022c22 0%, #065f46 40%, #047857 100%);
    border-radius: 20px; color: #fff; padding: 1.6rem 1.75rem; margin-bottom: 1.35rem;
    box-shadow: 0 20px 50px rgba(6, 95, 70, .25);
}
.nlf-hero::before {
    content: ''; position: absolute; top: -40%; right: -8%; width: 280px; height: 280px;
    background: radial-gradient(circle, rgba(255,255,255,.12) 0%, transparent 70%); pointer-events: none;
}
.nlf-hero h3 { color: #fff; margin: 0 0 .4rem; font-weight: 700; font-size: 1.65rem; letter-spacing: -.02em; }
.nlf-hero p { margin: 0; color: rgba(255,255,255,.82); font-size: .95rem; max-width: 36rem; }
.nlf-back {
    display: inline-flex; align-items: center; gap: .4rem; color: rgba(255,255,255,.88);
    text-decoration: none; font-size: .86rem; margin-bottom: .75rem; font-weight: 500;
}
.nlf-back:hover { color: #fff; }
.nlf-hero-row { position: relative; z-index: 1; }
.nlf-clock {
    display: inline-flex; align-items: center; gap: .5rem; padding: .5rem .85rem;
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
    border-radius: 999px; font-size: .82rem; font-weight: 600;
}
.nlf-live-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #4ade80;
    box-shadow: 0 0 0 4px rgba(74, 222, 128, .25); animation: nlf-pulse 2s infinite;
}
@keyframes nlf-pulse { 0%,100%{opacity:1; transform:scale(1)} 50%{opacity:.65; transform:scale(.92)} }
.nlf-stat-mini {
    background: #fff; border-radius: 14px; padding: .85rem 1rem; border: 1px solid #e2e8f0;
    box-shadow: 0 4px 14px rgba(15,23,42,.04); height: 100%;
}
.nlf-stat-mini .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #64748b; font-weight: 600; }
.nlf-stat-mini .val { font-size: 1.5rem; font-weight: 700; color: var(--nlf-ink); margin-top: .2rem; }
.nlf-card {
    border: 1px solid #e2e8f0; border-radius: 18px; background: #fff;
    box-shadow: 0 10px 30px rgba(15,23,42,.05); height: 100%;
}
.nlf-card-h {
    padding: .95rem 1.15rem; border-bottom: 1px solid #f1f5f9;
    font-weight: 700; font-size: .92rem; color: var(--nlf-ink);
    display: flex; align-items: center; justify-content: space-between; gap: .5rem;
}
.nlf-card-b { padding: 1rem 1.15rem; }
.nlf-scroll { overflow-y: auto; overflow-x: hidden; -webkit-overflow-scrolling: touch; }
.nlf-scroll-sm { max-height: min(42vh, 420px); }
.nlf-channel-tabs { display: grid; grid-template-columns: repeat(3, 1fr); gap: .55rem; margin-bottom: 1.15rem; }
.nlf-channel-tab {
    border: 2px solid #e2e8f0; border-radius: 14px; padding: .75rem .5rem;
    text-align: center; cursor: pointer; background: #fafafa; transition: all .2s ease;
}
.nlf-channel-tab .ch-icon { font-size: 1.15rem; margin-bottom: .25rem; }
.nlf-channel-tab .ch-label { font-size: .78rem; font-weight: 700; color: #64748b; }
.nlf-channel-tab.is-active { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15,23,42,.08); }
.nlf-channel-tab[data-ch="fcm"].is-active { border-color: #8b5cf6; background: linear-gradient(180deg,#faf5ff,#fff); }
.nlf-channel-tab[data-ch="fcm"].is-active .ch-label { color: #6d28d9; }
.nlf-channel-tab[data-ch="sms"].is-active { border-color: #f59e0b; background: linear-gradient(180deg,#fffbeb,#fff); }
.nlf-channel-tab[data-ch="sms"].is-active .ch-label { color: #b45309; }
.nlf-channel-tab[data-ch="email"].is-active { border-color: #3b82f6; background: linear-gradient(180deg,#eff6ff,#fff); }
.nlf-channel-tab[data-ch="email"].is-active .ch-label { color: #1d4ed8; }
.nlf-field label { font-size: .78rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: .03em; margin-bottom: .35rem; }
.nlf-field .form-control, .nlf-field .form-select {
    border-radius: 12px; border-color: #e2e8f0; padding: .65rem .85rem; font-size: .92rem;
}
.nlf-field .form-control:focus { border-color: var(--nlf-green); box-shadow: 0 0 0 3px rgba(16,185,129,.12); }
.nlf-flash {
    display: none; align-items: center; gap: .45rem; padding: .5rem .75rem; margin-bottom: .85rem;
    background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; font-size: .82rem; font-weight: 600; color: #047857;
    animation: nlf-flash-in .25s ease;
}
.nlf-flash.is-visible { display: flex; }
@keyframes nlf-flash-in { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: none; } }
.nlf-editor-highlight .form-control { animation: nlf-highlight .6s ease; }
@keyframes nlf-highlight { 0%{box-shadow:0 0 0 3px rgba(16,185,129,.35)} 100%{box-shadow:none} }
.nlf-template-item {
    border: 1px solid #e2e8f0; border-radius: 14px; padding: .8rem; margin-bottom: .55rem;
    cursor: pointer; transition: all .15s ease; background: #fafafa;
}
.nlf-template-item:hover { border-color: #94a3b8; background: #fff; transform: translateX(2px); }
.nlf-template-item.is-selected { border-color: var(--nlf-green); background: #ecfdf5; box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
.nlf-template-item .name { font-weight: 700; font-size: .88rem; color: var(--nlf-ink); }
.nlf-template-item .meta { font-size: .72rem; color: #64748b; margin-top: .25rem; display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
.nlf-badge { display: inline-flex; padding: .12rem .45rem; border-radius: 999px; font-size: .68rem; font-weight: 700; }
.nlf-badge-preset { background: #dbeafe; color: #1d4ed8; }
.nlf-badge-admin { background: #ede9fe; color: #6d28d9; }
.nlf-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
.nlf-btn {
    display: inline-flex; align-items: center; gap: .4rem; border-radius: 12px; padding: .55rem .95rem;
    font-size: .84rem; font-weight: 700; border: none; cursor: pointer; transition: all .15s;
}
.nlf-btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: #fff; box-shadow: 0 4px 14px rgba(16,185,129,.35); }
.nlf-btn-primary:hover { filter: brightness(1.05); color: #fff; transform: translateY(-1px); }
.nlf-btn-ghost { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.nlf-btn-ghost:hover { background: #e2e8f0; color: #0f172a; }
.nlf-btn-auto { background: #fff; color: #047857; border: 2px dashed #6ee7b7; font-weight: 700; }
.nlf-btn-auto:hover { background: #ecfdf5; border-color: #10b981; border-style: solid; color: #047857; }
.nlf-quick-times { display: flex; flex-wrap: wrap; gap: .4rem; margin-bottom: .75rem; }
.nlf-quick-time {
    border: 1px solid #cbd5e1; background: #fff; border-radius: 999px; padding: .3rem .7rem;
    font-size: .78rem; font-weight: 700; color: #334155; cursor: pointer; transition: all .15s;
}
.nlf-quick-time.is-active { background: var(--nlf-green-d); border-color: var(--nlf-green-d); color: #fff; }
.nlf-time-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; min-height: 1.5rem; }
.nlf-time-chip {
    display: inline-flex; align-items: center; gap: .3rem; padding: .25rem .55rem; border-radius: 999px;
    background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; font-size: .78rem; font-weight: 700;
}
.nlf-time-chip button { border: 0; background: transparent; color: #64748b; cursor: pointer; padding: 0; line-height: 1; }
.nlf-schedule-box {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; margin-bottom: 1rem;
}
.nlf-btn-danger { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
.nlf-btn-danger:hover { background: #fef2f2; color: #991b1b; }
.nlf-feed-item {
    border-left: 3px solid var(--nlf-green); padding: .6rem .75rem; margin-bottom: .55rem;
    background: #f8fafc; border-radius: 0 12px 12px 0; font-size: .84rem;
}
.nlf-feed-item .time { font-size: .72rem; color: #94a3b8; margin-top: .15rem; }
.nlf-status-badge {
    display: inline-flex; padding: .1rem .45rem; border-radius: 999px;
    font-size: .66rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
}
.nlf-status-sent { background: #dcfce7; color: #166534; }
.nlf-status-pending { background: #fef3c7; color: #92400e; }
.nlf-status-paused { background: #f1f5f9; color: #64748b; }
.nlf-status-failed { background: #fee2e2; color: #991b1b; }
.nlf-status-success { background: #dcfce7; color: #166534; }
.nlf-send-history-item {
    border-left: 3px solid #94a3b8; padding: .55rem .7rem; margin-bottom: .5rem;
    background: #f8fafc; border-radius: 0 10px 10px 0; font-size: .82rem;
}
.nlf-send-history-item.is-success { border-left-color: #10b981; }
.nlf-send-history-item.is-failed { border-left-color: #ef4444; }
.nlf-send-history-item .meta { font-size: .72rem; color: #94a3b8; margin-top: .2rem; }
.nlf-sched-item {
    display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem;
    padding: .65rem 0; border-bottom: 1px solid #f1f5f9;
}
.nlf-sched-item:last-child { border-bottom: 0; }
.nlf-empty { text-align: center; color: #94a3b8; font-size: .86rem; padding: 1.75rem .75rem; }
.nlf-template-search {
    padding: 0 .85rem .75rem; border-bottom: 1px solid #f1f5f9;
}
.nlf-template-search .input-group-text { background: #fff; color: #64748b; border-right: 0; }
.nlf-template-search .form-control { border-left: 0; font-size: .88rem; }
.nlf-template-list-wrap {
    max-height: min(70vh, 720px); overflow-y: auto; overflow-x: hidden;
    padding: .75rem; -webkit-overflow-scrolling: touch;
}
.nlf-editor-hint {
    font-size: .78rem; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0;
    border-radius: 10px; padding: .55rem .75rem; margin-bottom: 1rem; line-height: 1.45;
}
.nlf-toast-bar {
    position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 1080; max-width: 320px;
    padding: .75rem 1rem; border-radius: 12px; font-size: .86rem; font-weight: 600;
    box-shadow: 0 12px 30px rgba(15,23,42,.15); display: none;
}
.nlf-toast-bar.is-error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; display: block; }
.nlf-toast-bar.is-success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; display: block; }
.nlf-audience-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; margin-bottom: 1rem; }
@media (max-width: 575px) { .nlf-audience-grid { grid-template-columns: 1fr; } }
.nlf-audience-card {
    border: 2px solid #e2e8f0; border-radius: 14px; padding: .85rem; cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease; background: #fafafa;
}
.nlf-audience-card:hover { border-color: #94a3b8; }
.nlf-audience-card.is-selected { border-color: #10b981; background: #ecfdf5; box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
.nlf-audience-card .title { font-weight: 650; color: #0f172a; font-size: .9rem; }
.nlf-audience-card .desc { font-size: .78rem; color: #64748b; margin-top: .15rem; }
.nlf-user-search-wrap { position: relative; }
.nlf-user-search-results {
    margin-top: .5rem;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
    box-shadow: 0 8px 20px rgba(15,23,42,.08);
    max-height: 320px; overflow-y: auto; overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}
.nlf-user-search-foot {
    padding: .55rem .85rem; font-size: .78rem; color: #64748b;
    background: #f8fafc; border-top: 1px solid #e2e8f0;
    position: sticky; bottom: 0;
}
.nlf-user-search-item {
    display: block; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #f1f5f9;
    background: #fff; padding: .65rem .75rem; cursor: pointer;
}
.nlf-user-search-item:last-child { border-bottom: 0; }
.nlf-user-search-item:hover { background: #f8fafc; }
.nlf-user-search-item.is-disabled { opacity: .55; cursor: not-allowed; }
.nlf-user-search-item .name { font-weight: 600; color: #0f172a; font-size: .88rem; }
.nlf-user-search-item .meta { font-size: .76rem; color: #64748b; margin-top: .1rem; }
.nlf-user-search-item .badge-eligible {
    display: inline-block; margin-top: .25rem; font-size: .7rem; padding: .12rem .4rem;
    border-radius: 999px; background: #dcfce7; color: #166534;
}
.nlf-user-search-item .badge-ineligible {
    display: inline-block; margin-top: .25rem; font-size: .7rem; padding: .12rem .4rem;
    border-radius: 999px; background: #fee2e2; color: #991b1b;
}
.nlf-selected-users { display: flex; flex-wrap: wrap; gap: .45rem; min-height: 2rem; margin-top: .65rem; }
.nlf-selected-head {
    display: flex; align-items: center; justify-content: space-between; gap: .5rem;
    margin-top: .75rem; margin-bottom: .35rem;
}
.nlf-selected-count {
    font-size: .8rem; font-weight: 650; color: #047857;
    background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 999px; padding: .2rem .65rem;
}
.nlf-selected-user {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .3rem .55rem; border-radius: 999px; background: #ecfdf5; border: 1px solid #a7f3d0;
    color: #047857; font-size: .8rem; font-weight: 600;
}
.nlf-selected-user button {
    border: 0; background: transparent; color: #64748b; line-height: 1; padding: 0; cursor: pointer; font-size: 1rem;
}
.nlf-user-phone { font-weight: 650; color: #0f172a; font-variant-numeric: tabular-nums; }
.nlf-user-contact { font-size: .82rem; color: #475569; font-variant-numeric: tabular-nums; }
.nlf-target-panel.d-none { display: none !important; }
.nlf-target-panel { overflow: visible; }
.nlf-card-b { overflow: visible; }
.nlf-type-help { font-size: .8rem; color: #64748b; margin-top: .5rem; }
</style>
@endpush

@section('content')
<div class="nlf-wrap">
    <div class="nlf-hero">
        <a href="{{ route('admin.notifications.index') }}" class="nlf-back">
            <i data-feather="arrow-left"></i> Bildirim yönetimine dön
        </a>
        <div class="nlf-hero-row d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <h3><span class="nlf-live-dot me-2"></span>Canlı Bildirim Akışı</h3>
                <p>Şablon hazırla, saatleri ayarla ve kaydet — cron otomatik gönderir.</p>
            </div>
            <div class="nlf-clock" id="nlfServerClock">
                <span class="nlf-live-dot"></span>
                <span>TR · yükleniyor…</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6">
            <div class="nlf-stat-mini"><div class="lbl">Kayıtlı şablon</div><div class="val" id="nlf-stat-template-count">{{ $templates->count() }}</div></div>
        </div>
        <div class="col-6">
            <div class="nlf-stat-mini"><div class="lbl">Aktif zamanlama</div><div class="val" id="nlf-stat-schedule-count">{{ $schedules->where('is_active', true)->count() }}</div></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="nlf-card">
                <div class="nlf-card-h">
                    <span>Kayıtlı şablonlar</span>
                    <span class="badge text-bg-secondary" id="nlf-template-count">{{ $templates->count() }}</span>
                </div>
                <div class="nlf-template-search">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i data-feather="search" style="width:14px;height:14px"></i></span>
                        <input type="text" class="form-control" id="nlf-template-search" placeholder="Şablon ara…" autocomplete="off">
                    </div>
                </div>
                <div class="nlf-template-list-wrap" id="nlfTemplateList">
                    @include('admin.notifications.live-flow._templates', ['templates' => $templates, 'search' => ''])
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="nlf-card">
                <div class="nlf-card-h d-flex justify-content-between align-items-center">
                    <span>Şablon & zamanlama</span>
                    <button type="button" class="nlf-btn nlf-btn-ghost btn-sm py-1" id="nlfBtnNewTemplate">+ Yeni</button>
                </div>
                <div class="nlf-card-b">
                    <div class="nlf-editor-hint">
                        Soldan şablon seç veya <strong>Otomatik getir</strong> ile doldur. Saatleri işaretle, <strong>Şablonu kaydet</strong> de — kayıtlı saatlerde otomatik gider. Anlık gönderim için <a href="{{ route('admin.notifications.index') }}">Bildirim Gönder</a> sayfasını kullan.
                    </div>
                    <div class="nlf-channel-tabs">
                        <div class="nlf-channel-tab is-active" data-ch="fcm">
                            <div class="ch-icon">📱</div>
                            <div class="ch-label">Push</div>
                        </div>
                        <div class="nlf-channel-tab" data-ch="sms">
                            <div class="ch-icon">💬</div>
                            <div class="ch-label">SMS</div>
                        </div>
                        <div class="nlf-channel-tab" data-ch="email">
                            <div class="ch-icon">✉️</div>
                            <div class="ch-label">E-posta</div>
                        </div>
                    </div>

                    <div class="nlf-flash" id="nlfAutoFlash">
                        <i data-feather="check-circle" style="width:15px;height:15px"></i>
                        <span id="nlfAutoFlashText">Otomatik getirildi</span>
                    </div>

                    <button type="button" class="nlf-btn nlf-btn-auto w-100 justify-content-center mb-3" id="nlfBtnRandom">
                        <i data-feather="shuffle" style="width:16px;height:16px"></i> Otomatik getir
                    </button>

                    <input type="hidden" id="nlfChannel" value="fcm">
                    <input type="hidden" id="nlfTemplateId" value="">
                    <input type="hidden" id="nlfPresetKey" value="">

                    <div class="nlf-field mb-3">
                        <label>Şablon adı</label>
                        <input type="text" class="form-control" id="nlfName" placeholder="Örn: Sabah hatırlatması">
                    </div>
                    <div class="nlf-field mb-3">
                        <label>Başlık</label>
                        <input type="text" class="form-control" id="nlfTitle" maxlength="255">
                    </div>
                    <div class="nlf-field mb-3">
                        <label>İçerik</label>
                        <textarea class="form-control" id="nlfContent" rows="5"></textarea>
                    </div>

                    <hr class="my-3">

                    <div class="nlf-field mb-3">
                        <label>Hedef kitle</label>
                        <div class="nlf-audience-grid">
                            <div class="nlf-audience-card is-selected" data-audience="all">
                                <div class="title">Tüm kullanıcılar</div>
                                <div class="desc">Seçilen kanala uygun tüm uygulama kullanıcıları</div>
                            </div>
                            <div class="nlf-audience-card" data-audience="specific">
                                <div class="title">Belirli kullanıcılar</div>
                                <div class="desc">Birden fazla kullanıcı seçebilirsiniz</div>
                            </div>
                        </div>

                        <div id="nlf-target-panel" class="nlf-target-panel d-none">
                            <label for="nlf-user-search" class="form-label small fw-semibold">Kullanıcı ara ve ekle</label>
                            <div class="form-text mb-2">Listeden bir kullanıcıya tıklayın; aramaya devam edip istediğiniz kadar ekleyebilirsiniz.</div>
                            <div class="nlf-user-search-wrap">
                                <div class="input-group">
                                    <span class="input-group-text"><i data-feather="search"></i></span>
                                    <input type="text" class="form-control" id="nlf-user-search"
                                           placeholder="Ad, e-posta, telefon veya kullanıcı ID..." autocomplete="off">
                                </div>
                                <div id="nlf-user-search-results" class="nlf-user-search-results d-none"></div>
                            </div>
                            <div class="nlf-selected-head d-none" id="nlf-selected-head">
                                <span class="small fw-semibold text-muted">Seçilen kullanıcılar</span>
                                <span class="nlf-selected-count" id="nlf-selected-count">0 kişi</span>
                            </div>
                            <div class="nlf-selected-users" id="nlf-selected-users">
                                <span class="text-muted small" id="nlf-selected-empty">Henüz kullanıcı seçilmedi.</span>
                            </div>
                        </div>

                        <input type="hidden" id="nlf-target-users" value="">
                        <div class="nlf-type-help" id="nlf-type-help">Mobil cihaz kaydı olan tüm uygulama kullanıcılarına push gönderilir.</div>
                    </div>

                    <div class="nlf-schedule-box">
                        <div class="nlf-field mb-2">
                            <label>Zamanlama türü</label>
                            <select class="form-select form-select-sm" id="nlfScheduleType">
                                <option value="daily">Her gün (birden fazla saat seçilebilir)</option>
                                <option value="once">Tek sefer</option>
                            </select>
                        </div>
                        <div id="nlfDailyBlock">
                            <label class="form-label small fw-semibold text-muted">Gönderim saatleri (TR) — tıkla ekle/çıkar</label>
                            <div class="nlf-quick-times mb-2">
                                @foreach(['09:00', '13:00', '17:00', '20:00'] as $qt)
                                    <button type="button" class="nlf-quick-time" data-time="{{ $qt }}">{{ $qt }}</button>
                                @endforeach
                            </div>
                            <div class="d-flex gap-2 align-items-end mb-2">
                                <div class="flex-grow-1 nlf-field mb-0">
                                    <label class="small">Özel saat</label>
                                    <input type="time" class="form-control form-control-sm" id="nlfSendTime" value="09:00">
                                </div>
                                <button type="button" class="nlf-btn nlf-btn-ghost btn-sm mb-1" id="nlfAddCustomTime">Ekle</button>
                            </div>
                            <div class="nlf-time-chips" id="nlfTimeChips"></div>
                        </div>
                        <div id="nlfOnceBlock" class="d-none">
                            <div class="nlf-field mb-0">
                                <label>Tarih & saat (TR)</label>
                                <input type="datetime-local" class="form-control form-control-sm" id="nlfSendAt">
                            </div>
                        </div>
                    </div>

                    <div class="nlf-actions">
                        <button type="button" class="nlf-btn nlf-btn-primary flex-grow-1 justify-content-center" id="nlfBtnSave">
                            <i data-feather="save" style="width:16px;height:16px"></i> Şablonu kaydet
                        </button>
                        <button type="button" class="nlf-btn nlf-btn-danger d-none" id="nlfBtnDeleteTemplate">
                            <i data-feather="trash-2" style="width:15px;height:15px"></i> Sil
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="nlf-card mb-3">
                <div class="nlf-card-h">
                    <span id="nlf-schedule-panel-title">Şablon zamanlamaları</span>
                    <span class="badge text-bg-success" id="nlf-schedule-active-count">0</span>
                </div>
                <div class="nlf-card-b nlf-scroll nlf-scroll-sm" id="nlfScheduleList">
                    <div class="nlf-empty">Şablon seçin</div>
                </div>
            </div>

            <div class="nlf-card">
                <div class="nlf-card-h"><span id="nlf-send-panel-title">Gönderim durumu</span></div>
                <div class="nlf-card-b nlf-scroll nlf-scroll-sm" id="nlfSendStatusPanel">
                    <div class="nlf-empty">Şablon seçin — gönderim geçmişi burada görünür</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="nlf-toast-bar" id="nlfToast"></div>
@endsection

@push('scripts')
<script>
(function () {
    const routes = {
        random: @json(route('admin.notifications.live-flow.random-preset')),
        templates: @json(route('admin.notifications.live-flow.templates')),
        templateShow: @json(url('/admin/notifications/live-flow/templates')),
        save: @json(route('admin.notifications.live-flow.save')),
        destroyTemplate: @json(url('/admin/notifications/live-flow/templates')),
        schedules: @json(route('admin.notifications.live-flow.schedules')),
        userSearch: @json(route('admin.notifications.users.search')),
        destroySchedule: @json(url('/admin/notifications/live-flow/schedules')),
        toggleSchedule: @json(url('/admin/notifications/live-flow/schedules')),
        notificationsIndex: @json(route('admin.notifications.index')),
    };

    let selectedTemplateId = '';
    let flashTimer = null;
    let autoGenBusy = false;
    let audienceMode = 'all';
    let selectedUsers = {};
    let userSearchTimer = null;
    let templateSearchTimer = null;
    let selectedDailyTimes = new Set();

    const typeHelp = {
        all: {
            email: 'Kayıtlı e-posta adresi olan tüm uygulama kullanıcılarına gönderilir.',
            sms: 'Telefon numarası kayıtlı tüm uygulama kullanıcılarına gönderilir.',
            fcm: 'Mobil cihaz kaydı olan tüm uygulama kullanıcılarına push gönderilir.',
        },
        specific: {
            email: 'Seçtiğiniz kullanıcılardan e-posta adresi olanlara gönderilir.',
            sms: 'Seçtiğiniz kullanıcılardan telefon numarası olanlara gönderilir.',
            fcm: 'Seçtiğiniz kullanıcılardan mobil cihaz kaydı olanlara push gönderilir.',
        },
    };

    function csrfHeaders() {
        return { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') };
    }

    function showToast(msg, type) {
        const $t = $('#nlfToast');
        $t.removeClass('is-error is-success').addClass(type === 'error' ? 'is-error' : 'is-success').text(msg).show();
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () { $t.fadeOut(200, function () { $(this).hide().removeClass('is-error is-success'); }); }, 3500);
    }

    function showAutoFlash(text) {
        $('#nlfAutoFlashText').text(text || 'Otomatik getirildi');
        const $f = $('#nlfAutoFlash').addClass('is-visible');
        clearTimeout(flashTimer);
        flashTimer = setTimeout(function () { $f.removeClass('is-visible'); }, 2200);
    }

    function currentChannel() { return $('#nlfChannel').val(); }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatUserContact(user, type) {
        type = type || currentChannel();
        if (type === 'sms' && user.phone) return user.phone;
        if (type === 'email' && user.email) return user.email;
        if (user.phone) return user.phone;
        if (user.email) return user.email;
        return '';
    }

    function formatUserLine(user, type, withPhoneClass) {
        const contact = formatUserContact(user, type);
        let html = escapeHtml(user.label) + ' <span class="text-muted">#' + user.id + '</span>';
        if (contact) {
            const contactClass = withPhoneClass ? ' nlf-user-phone' : ' nlf-user-contact';
            html += ' · <span class="' + contactClass.trim() + '">' + escapeHtml(contact) + '</span>';
        }
        return html;
    }

    function updateAudienceHelp() {
        const type = currentChannel();
        const mode = audienceMode === 'specific' ? 'specific' : 'all';
        $('#nlf-type-help').text(typeHelp[mode][type] || '');
    }

    function syncTargetUsersInput() {
        if (audienceMode === 'all') {
            $('#nlf-target-users').val('');
            return;
        }
        $('#nlf-target-users').val(Object.keys(selectedUsers).join(','));
    }

    function renderSelectedUsers() {
        const ids = Object.keys(selectedUsers);
        const $wrap = $('#nlf-selected-users');
        $wrap.empty();

        if (!ids.length) {
            $('#nlf-selected-head').addClass('d-none');
            $wrap.html('<span class="text-muted small" id="nlf-selected-empty">Henüz kullanıcı seçilmedi.</span>');
            syncTargetUsersInput();
            return;
        }

        $('#nlf-selected-head').removeClass('d-none');
        $('#nlf-selected-count').text(ids.length + ' kişi');

        ids.forEach(function (id) {
            const user = selectedUsers[id];
            $wrap.append(
                '<span class="nlf-selected-user">' +
                    '<span>' + formatUserLine(user, currentChannel(), true) + '</span>' +
                    '<button type="button" data-remove-user="' + user.id + '" aria-label="Kaldır">&times;</button>' +
                '</span>'
            );
        });
        syncTargetUsersInput();
    }

    function setAudienceMode(mode) {
        audienceMode = mode;
        $('.nlf-audience-card').removeClass('is-selected');
        $('.nlf-audience-card[data-audience="' + mode + '"]').addClass('is-selected');
        $('#nlf-target-panel').toggleClass('d-none', mode !== 'specific');
        if (mode === 'all') {
            selectedUsers = {};
            renderSelectedUsers();
        }
        syncTargetUsersInput();
        updateAudienceHelp();
    }

    function addSelectedUser(user) {
        if (selectedUsers[user.id]) {
            showToast('Bu kullanıcı zaten seçili', 'error');
            return;
        }
        selectedUsers[user.id] = user;
        renderSelectedUsers();
        $('#nlf-user-search').val('').trigger('focus');
        $('#nlf-user-search-results').addClass('d-none').empty();
    }

    function searchTargetUsers(query) {
        $.ajax({
            url: routes.userSearch,
            type: 'GET',
            data: { q: query, type: currentChannel() },
            success: function (response) {
                renderUserSearchResults(response.users || []);
            },
            error: function () {
                $('#nlf-user-search-results')
                    .removeClass('d-none')
                    .html('<div class="p-3 text-muted small">Kullanıcı araması başarısız.</div>');
            }
        });
    }

    function renderUserSearchResults(users) {
        const $box = $('#nlf-user-search-results');
        $box.empty();

        if (!users.length) {
            $box.removeClass('d-none').html('<div class="p-3 text-muted small">Sonuç bulunamadı.</div>');
            return;
        }

        const type = currentChannel();
        users.forEach(function (user) {
            const already = !!selectedUsers[user.id];
            const meta = [];
            if (type === 'sms' && user.email) meta.push(user.email);
            else if (type === 'email' && user.phone) meta.push(user.phone);
            else if (type === 'fcm') {
                if (user.phone) meta.push(user.phone);
                if (user.email) meta.push(user.email);
            }
            const badgeClass = user.eligible ? 'badge-eligible' : 'badge-ineligible';
            const disabledClass = already ? 'is-disabled' : '';
            const metaHtml = meta.length
                ? '<div class="meta">' + escapeHtml(meta.join(' · ')) + '</div>'
                : (!formatUserContact(user, type) ? '<div class="meta">İletişim bilgisi yok</div>' : '');

            const $btn = $(
                '<button type="button" class="nlf-user-search-item ' + disabledClass + '" data-user-id="' + user.id + '">' +
                    '<div class="name">' + formatUserLine(user, type, true) + '</div>' +
                    metaHtml +
                    '<span class="' + badgeClass + '">' + escapeHtml(already ? 'Zaten seçili' : user.eligible_note) + '</span>' +
                '</button>'
            );
            $btn.data('user', user);
            $box.append($btn);
        });

        if (users.length >= 25) {
            $box.append('<div class="nlf-user-search-foot">İlk 25 sonuç gösteriliyor. Daha net aramak için yazmaya devam edin.</div>');
        }

        $box.removeClass('d-none');
    }

    function validateTargetAudience() {
        if (audienceMode === 'specific' && !Object.keys(selectedUsers).length) {
            showToast('Belirli kullanıcılar modunda en az bir kullanıcı seçmelisiniz.', 'error');
            return false;
        }
        return true;
    }

    function fillEditor(data, opts) {
        opts = opts || {};
        if (data.channel) setChannelUi(data.channel);
        if (data.name !== undefined) $('#nlfName').val(data.name);
        if (data.title !== undefined) $('#nlfTitle').val(data.title);
        if (data.content !== undefined) $('#nlfContent').val(data.content);
        if (data.templateId !== undefined) {
            selectedTemplateId = data.templateId || '';
            $('#nlfTemplateId').val(selectedTemplateId);
        }
        if (data.presetKey !== undefined) $('#nlfPresetKey').val(data.presetKey || '');
        if (opts.highlight) {
            $('.nlf-field .form-control').addClass('nlf-editor-highlight');
            setTimeout(function () { $('.nlf-field .form-control').removeClass('nlf-editor-highlight'); }, 650);
        }
        if (opts.flash) showAutoFlash(opts.flashText);
    }

    function setChannelUi(ch) {
        $('#nlfChannel').val(ch);
        $('.nlf-channel-tab').removeClass('is-active');
        $('.nlf-channel-tab[data-ch="' + ch + '"]').addClass('is-active');
        updateAudienceHelp();
        const q = $('#nlf-user-search').val().trim();
        if (q.length >= 2 || /^\d+$/.test(q)) {
            searchTargetUsers(q);
        }
    }

    function autoGenerate(opts) {
        opts = opts || {};
        if (autoGenBusy) return;
        autoGenBusy = true;
        const ch = opts.channel || currentChannel();
        $.post(routes.random, { channel: ch }, function (res) {
            if (!res.success || !res.preset) {
                if (!opts.silent) showToast(res.message || 'Şablon alınamadı', 'error');
                return;
            }
            const p = res.preset;
            $('.nlf-template-item').removeClass('is-selected');
            fillEditor({
                channel: p.channel,
                name: p.name || p.key,
                title: p.title,
                content: p.content,
                templateId: '',
                presetKey: p.key || '',
            }, {
                highlight: true,
                flash: !opts.silent,
                flashText: opts.silent ? null : 'Otomatik getirildi',
            });
            setAudienceMode('all');
            $('#nlfBtnDeleteTemplate').addClass('d-none');
            $('#nlfScheduleList').html('<div class="nlf-empty">Kaydedince zamanlamalar burada görünür</div>');
            $('#nlf-schedule-active-count').text('0');
        }).fail(function (xhr) {
            if (!opts.silent) showToast(xhr.responseJSON?.message || 'Şablon alınamadı', 'error');
        }).always(function () {
            autoGenBusy = false;
        });
    }

    function syncTimeUi() {
        $('.nlf-quick-time').each(function () {
            const t = $(this).data('time');
            $(this).toggleClass('is-active', selectedDailyTimes.has(t));
        });
        const $chips = $('#nlfTimeChips').empty();
        if (!selectedDailyTimes.size) {
            $chips.html('<span class="text-muted small">En az bir saat seçin</span>');
            return;
        }
        Array.from(selectedDailyTimes).sort().forEach(function (t) {
            $chips.append(
                '<span class="nlf-time-chip">' + t +
                ' <button type="button" data-remove-time="' + t + '">&times;</button></span>'
            );
        });
    }

    function setDailyTimes(times) {
        selectedDailyTimes = new Set(times || []);
        syncTimeUi();
    }

    function toggleDailyTime(time) {
        if (!time) return;
        if (selectedDailyTimes.has(time)) selectedDailyTimes.delete(time);
        else selectedDailyTimes.add(time);
        syncTimeUi();
    }

    function resetEditor() {
        selectedTemplateId = '';
        $('#nlfTemplateId').val('');
        $('#nlfPresetKey').val('');
        $('#nlfName, #nlfTitle, #nlfContent').val('');
        setDailyTimes([]);
        $('#nlfScheduleType').val('daily').trigger('change');
        $('#nlfSendAt').val('');
        setAudienceMode('all');
        $('.nlf-template-item').removeClass('is-selected');
        $('#nlfBtnDeleteTemplate').addClass('d-none');
        $('#nlfScheduleList').html('<div class="nlf-empty">Şablon seçin</div>');
        $('#nlf-schedule-active-count').text('0');
        $('#nlfSendStatusPanel').html('<div class="nlf-empty">Şablon seçin — gönderim geçmişi burada görünür</div>');
    }

    function renderTemplateSendStatus(res) {
        const $panel = $('#nlfSendStatusPanel').empty();
        const history = res.send_history || [];
        const schedules = res.schedules_status || [];

        if (!schedules.length && !history.length) {
            $panel.html('<div class="nlf-empty">Bu şablonda henüz gönderim yok.<br><span class="small">Zamanlama kaydedince otomatik gider; anlık gönderim için ana sayfadaki <strong>Bildirim Gönder</strong> kullanılır.</span></div>');
            return;
        }

        if (schedules.length) {
            $panel.append('<div class="small fw-bold text-muted text-uppercase mb-2" style="letter-spacing:.03em">Zamanlama durumu</div>');
            schedules.forEach(function (sch) {
                const statusClass = sch.status === 'sent' ? 'nlf-status-sent' : (sch.status === 'paused' ? 'nlf-status-paused' : 'nlf-status-pending');
                const lastLine = sch.last_sent_at
                    ? 'Son: ' + sch.last_sent_at
                    : 'Henüz gönderilmedi';
                $panel.append(
                    '<div class="nlf-send-history-item">' +
                        '<div class="d-flex align-items-center gap-2 flex-wrap">' +
                            '<strong>' + escapeHtml(sch.label) + '</strong>' +
                            '<span class="nlf-status-badge ' + statusClass + '">' + escapeHtml(sch.status_label) + '</span>' +
                        '</div>' +
                        '<div class="meta">' + escapeHtml(lastLine) + '</div>' +
                    '</div>'
                );
            });
        }

        if (history.length) {
            $panel.append('<div class="small fw-bold text-muted text-uppercase mb-2 mt-3" style="letter-spacing:.03em">Gönderim kayıtları</div>');
            history.forEach(function (item) {
                const ok = item.status === 'success';
                $panel.append(
                    '<div class="nlf-send-history-item ' + (ok ? 'is-success' : 'is-failed') + '">' +
                        '<div class="d-flex align-items-center gap-2 flex-wrap">' +
                            '<strong>' + escapeHtml(item.title) + '</strong>' +
                            '<span class="nlf-status-badge ' + (ok ? 'nlf-status-success' : 'nlf-status-failed') + '">' + escapeHtml(item.status_label) + '</span>' +
                        '</div>' +
                        '<div class="meta">' + escapeHtml(item.created_at) + ' · ' + (item.sent_count || 0) + ' kişi · ' +
                            '<a href="' + routes.notificationsIndex + '" class="text-decoration-none">Ana listede gör</a>' +
                        '</div>' +
                    '</div>'
                );
            });
        } else if (schedules.length) {
            $panel.append('<div class="small text-muted mt-2">Cron henüz kayıt oluşturmadı; gönderimler ana bildirim listesinde de görünür.</div>');
        }
    }

    function loadTemplateDetail(id) {
        $.get(routes.templateShow + '/' + id, function (res) {
            if (!res.success) return;
            const t = res.template;
            selectedTemplateId = String(t.id);
            $('#nlfTemplateId').val(selectedTemplateId);
            $('#nlfPresetKey').val(t.preset_key || '');
            setChannelUi(t.channel);
            $('#nlfName').val(t.name);
            $('#nlfTitle').val(t.title);
            $('#nlfContent').val(t.content);
            $('#nlfBtnDeleteTemplate').removeClass('d-none');
            $('#nlfScheduleType').val(res.schedule_type || 'daily').trigger('change');
            if (res.schedule_type === 'once') {
                if (res.send_at) $('#nlfSendAt').val(res.send_at);
                setDailyTimes([]);
            } else {
                setDailyTimes(res.daily_times || []);
            }
            if (res.audience_mode === 'specific' && Array.isArray(res.target_users_detail) && res.target_users_detail.length) {
                setAudienceMode('specific');
                selectedUsers = {};
                res.target_users_detail.forEach(function (user) {
                    selectedUsers[user.id] = user;
                });
                renderSelectedUsers();
            } else if (Array.isArray(res.target_users) && res.target_users.length) {
                setAudienceMode('specific');
                selectedUsers = {};
                res.target_users.forEach(function (uid) {
                    selectedUsers[uid] = { id: uid, label: 'Kullanıcı #' + uid };
                });
                renderSelectedUsers();
            } else {
                setAudienceMode('all');
            }
            renderTemplateSendStatus(res);
            loadSchedules(id);
        });
    }

    function editorPayload(extra) {
        return Object.assign({
            title: $('#nlfTitle').val(),
            content: $('#nlfContent').val(),
            channel: currentChannel(),
            template_name: $('#nlfName').val(),
            preset_key: $('#nlfPresetKey').val() || '',
            template_id: selectedTemplateId || '',
            target_users: $('#nlf-target-users').val(),
        }, extra || {});
    }

    $(document).on('click', '.nlf-audience-card', function () {
        setAudienceMode($(this).data('audience'));
    });

    $('#nlf-user-search').on('keyup', function () {
        const q = $(this).val().trim();
        clearTimeout(userSearchTimer);
        if (q.length < 2 && !/^\d+$/.test(q)) {
            $('#nlf-user-search-results').addClass('d-none').empty();
            return;
        }
        userSearchTimer = setTimeout(function () {
            searchTargetUsers(q);
        }, 300);
    });

    $(document).on('click', '.nlf-user-search-item:not(.is-disabled)', function () {
        addSelectedUser($(this).data('user'));
    });

    $(document).on('click', '[data-remove-user]', function () {
        delete selectedUsers[$(this).data('remove-user')];
        renderSelectedUsers();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.nlf-user-search-wrap').length) {
            $('#nlf-user-search-results').addClass('d-none');
        }
    });

    function setChannel(ch, skipAuto) {
        setChannelUi(ch);
        if (!skipAuto) autoGenerate({ channel: ch, silent: false });
    }

    $('.nlf-channel-tab').on('click', function () {
        setChannel($(this).data('ch'), false);
    });

    $(document).on('click', '.nlf-template-item', function (e) {
        if ($(e.target).closest('.nlf-delete-template').length) return;
        const id = $(this).data('template-id');
        $('.nlf-template-item').removeClass('is-selected');
        $(this).addClass('is-selected');
        loadTemplateDetail(id);
    });

    $(document).on('click', '.nlf-delete-template', function (e) {
        e.stopPropagation();
        deleteTemplate($(this).data('id'));
    });

    $('#nlfBtnDeleteTemplate').on('click', function () {
        if (!selectedTemplateId) return;
        deleteTemplate(selectedTemplateId);
    });

    function deleteTemplate(id) {
        if (!confirm('Bu şablon ve tüm zamanlamaları silinsin mi?')) return;
        $.ajax({
            url: routes.destroyTemplate + '/' + id,
            method: 'DELETE',
            headers: csrfHeaders(),
            success: function (res) {
                showToast(res.message || 'Silindi', 'success');
                if (String(id) === String(selectedTemplateId)) resetEditor();
                loadTemplates();
                loadSchedules();
            },
            error: function () { showToast('Silinemedi', 'error'); }
        });
    }

    $('#nlfBtnNewTemplate').on('click', function () {
        resetEditor();
        autoGenerate({ channel: currentChannel(), silent: false });
    });

    $('#nlf-template-search').on('keyup', function () {
        clearTimeout(templateSearchTimer);
        templateSearchTimer = setTimeout(function () {
            loadTemplates();
        }, 300);
    });

    function loadTemplates() {
        $.get(routes.templates, {
            q: $('#nlf-template-search').val().trim(),
            selected_id: selectedTemplateId || '',
        }, function (res) {
            if (!res.success) return;
            $('#nlfTemplateList').html(res.html);
            $('#nlf-template-count').text(res.count);
            $('#nlf-stat-template-count').text(res.count);
            if (typeof feather !== 'undefined') feather.replace();
        });
    }

    function loadSchedules(templateId) {
        const params = templateId ? { template_id: templateId } : {};
        if (!templateId) {
            $('#nlfScheduleList').html('<div class="nlf-empty">Şablon seçin</div>');
            $('#nlf-schedule-active-count').text('0');
            return;
        }
        $.get(routes.schedules, params, function (res) {
            if (!res.success) return;
            $('#nlfScheduleList').html(res.html);
            $('#nlf-stat-schedule-count').text(res.active_count);
            $('#nlf-schedule-active-count').text(res.active_count);
        });
    }

    $('#nlfBtnRandom').on('click', function () {
        autoGenerate({ silent: false });
    });

    $('#nlfScheduleType').on('change', function () {
        const daily = $(this).val() === 'daily';
        $('#nlfDailyBlock').toggleClass('d-none', !daily);
        $('#nlfOnceBlock').toggleClass('d-none', daily);
    });

    $('.nlf-quick-time').on('click', function (e) {
        e.preventDefault();
        toggleDailyTime($(this).data('time'));
    });

    $('#nlfAddCustomTime').on('click', function () {
        toggleDailyTime($('#nlfSendTime').val());
        if ($('#nlfSendTime').val()) selectedDailyTimes.add($('#nlfSendTime').val());
        syncTimeUi();
    });

    $(document).on('click', '[data-remove-time]', function () {
        selectedDailyTimes.delete($(this).data('remove-time'));
        syncTimeUi();
    });

    $('#nlfBtnSave').on('click', function () {
        const $btn = $(this).prop('disabled', true);
        if (!validateTargetAudience()) {
            $btn.prop('disabled', false);
            return;
        }
        const payload = editorPayload({
            schedule_type: $('#nlfScheduleType').val(),
        });
        if (!payload.title || !payload.content) {
            showToast('Başlık ve içerik gerekli', 'error');
            $btn.prop('disabled', false);
            return;
        }
        if (payload.schedule_type === 'daily') {
            payload.daily_times = Array.from(selectedDailyTimes);
            if (!payload.daily_times.length) {
                showToast('En az bir gönderim saati seçin', 'error');
                $btn.prop('disabled', false);
                return;
            }
        } else {
            payload.send_at = $('#nlfSendAt').val();
            if (!payload.send_at) {
                showToast('Tek sefer için tarih/saat seçin', 'error');
                $btn.prop('disabled', false);
                return;
            }
        }
        $.ajax({
            url: routes.save,
            method: 'POST',
            headers: csrfHeaders(),
            data: payload,
            success: function (res) {
                showToast(res.message || 'Kaydedildi', 'success');
                if (res.template_id) {
                    selectedTemplateId = String(res.template_id);
                    $('#nlfTemplateId').val(selectedTemplateId);
                    $('#nlfBtnDeleteTemplate').removeClass('d-none');
                }
                loadTemplates();
                loadSchedules(selectedTemplateId);
                if (selectedTemplateId) loadTemplateDetail(selectedTemplateId);
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.errors
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : (xhr.responseJSON?.message || 'Kayıt hatası');
                showToast(msg, 'error');
            },
            complete: function () { $btn.prop('disabled', false); }
        });
    });

    $(document).on('click', '.nlf-delete-schedule', function () {
        if (!confirm('Bu zamanlamayı sil?')) return;
        $.ajax({
            url: routes.destroySchedule + '/' + $(this).data('id'),
            method: 'DELETE',
            headers: csrfHeaders(),
            success: function () {
                loadSchedules(selectedTemplateId);
                loadTemplates();
            }
        });
    });

    $(document).on('click', '.nlf-toggle-schedule', function () {
        $.ajax({
            url: routes.toggleSchedule + '/' + $(this).data('id') + '/toggle',
            method: 'PATCH',
            headers: csrfHeaders(),
            success: function () { loadSchedules(selectedTemplateId); }
        });
    });

    $('#nlfServerClock span:last').text('TR · {{ now(\App\Services\NotificationFlowHelper::timezone())->format("d.m.Y H:i:s") }}');

    autoGenerate({ channel: 'fcm', silent: true });
    updateAudienceHelp();
    syncTimeUi();

    if (typeof feather !== 'undefined') feather.replace();
})();
</script>
@endpush
