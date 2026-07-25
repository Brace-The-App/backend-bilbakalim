@extends('admin.layouts.app')

@section('title', 'Kullanıcı Cevap İstatistikleri')

@push('css')
<style>
    .qas-page .qas-hero {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d6a9f 55%, #3d8ec9 100%);
        border-radius: 14px;
        color: #fff;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 10px 28px rgba(30, 58, 95, 0.22);
    }
    .qas-page .qas-hero h3 { color: #fff !important; margin: 0 0 .35rem; font-weight: 600; }
    .qas-page .qas-hero p,
    .qas-page .qas-hero .qas-hero-sub {
        margin: 0;
        color: #ffffff !important;
        opacity: 1;
        font-size: .9rem;
    }
    .qas-page .qas-stat-card {
        border: 0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
        cursor: pointer;
    }
    .qas-page .qas-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .1);
    }
    .qas-page .qas-stat-card:focus {
        outline: 2px solid #38bdf8;
        outline-offset: 2px;
    }
    #summaryModal .qas-sum-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .55rem 0;
        border-bottom: 1px solid #f1f5f9;
        gap: .75rem;
    }
    #summaryModal .qas-sum-row:last-child { border-bottom: 0; }
    #summaryModal .qas-sum-label { color: #475569; font-size: .9rem; }
    #summaryModal .qas-sum-value { font-weight: 700; color: #0f172a; }
    #summaryModal .qas-sum-note {
        font-size: .82rem;
        color: #64748b;
        margin-top: .75rem;
        margin-bottom: 0;
    }
    #summaryModal .qas-sum-list-item {
        font-size: .82rem;
        padding: .45rem 0;
        border-bottom: 1px solid #f8fafc;
    }
    #summaryModal .qas-sum-list-item:last-child { border-bottom: 0; }
    .qas-page .qas-stat-card .card-body { padding: 1.1rem 1.2rem; }
    .qas-page .qas-stat-label {
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #64748b;
        margin-bottom: .25rem;
    }
    .qas-page .qas-stat-value {
        font-size: 1.65rem;
        font-weight: 700;
        color: #0f172a;
        line-height: 1.1;
        margin: 0;
    }
    .qas-page .qas-stat-hint { font-size: .78rem; color: #94a3b8; margin-top: .35rem; }
    .qas-page .qas-stat-icon {
        width: 42px; height: 42px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .qas-page .qas-stat-icon svg { width: 20px; height: 20px; }
    .qas-page .qas-icon-blue { background: #e0f2fe; color: #0284c7; }
    .qas-page .qas-icon-green { background: #dcfce7; color: #16a34a; }
    .qas-page .qas-icon-amber { background: #fef3c7; color: #d97706; }
    .qas-page .qas-icon-rose { background: #ffe4e6; color: #e11d48; }
    .qas-page .qas-chart-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
        height: 100%;
    }
    .qas-page .qas-chart-card .card-header {
        background: transparent;
        border-bottom: 1px solid #f1f5f9;
        padding: 1rem 1.15rem .75rem;
    }
    .qas-page .qas-chart-card .card-header h6 {
        margin: 0;
        font-weight: 600;
        color: #0f172a;
    }
    .qas-page .qas-chart-card .card-header small { color: #94a3b8; }
    .qas-page .qas-chart-wrap {
        position: relative;
        height: 260px;
        padding: .5rem 1rem 1rem;
    }
    .qas-page .qas-chart-wrap canvas {
        width: 100% !important;
        height: 100% !important;
    }
    .qas-page .qas-chart-wrap-sm { height: 240px; }
    .qas-page .qas-chart-footnote {
        font-size: .72rem;
        line-height: 1.35;
        color: #94a3b8;
    }
    .qas-page .qas-filter-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
        margin-bottom: 1.25rem;
    }
    .qas-page .qas-filter-card .card-body { padding: 1rem 1.15rem; }
    .qas-page .qas-table-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, .06);
        overflow: hidden;
    }
    .qas-page .qas-table-card .card-header {
        background: #fff;
        border-bottom: 1px solid #f1f5f9;
        padding: 1rem 1.15rem;
    }
    .qas-page .qas-table-scroll {
        overflow-x: auto;
        cursor: grab;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .qas-page .qas-table-scroll.is-dragging {
        cursor: grabbing;
        user-select: none;
    }
    .qas-page .qas-table-scroll.is-dragging a,
    .qas-page .qas-table-scroll.is-dragging button,
    .qas-page .qas-table-scroll.is-dragging select {
        pointer-events: none;
    }
    .qas-page .qas-table thead th {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #64748b;
        font-weight: 600;
        border-bottom-width: 1px;
        white-space: nowrap;
        background: #f8fafc;
    }
    .qas-page .qas-table tbody td { vertical-align: middle; font-size: .9rem; }
    .qas-page .qas-question-cell {
        max-width: 280px;
        color: #0f172a;
        font-weight: 500;
        line-height: 1.35;
    }
    .qas-page .qas-option-bars { min-width: 140px; }
    .qas-page .qas-opt-row {
        display: flex; align-items: center; gap: .4rem;
        font-size: .72rem; margin-bottom: 3px; color: #475569;
    }
    .qas-page .qas-opt-label { width: 14px; font-weight: 600; }
    .qas-page .qas-opt-track {
        flex: 1; height: 6px; background: #e2e8f0; border-radius: 99px; overflow: hidden;
    }
    .qas-page .qas-opt-fill {
        height: 100%; border-radius: 99px; background: #94a3b8;
    }
    .qas-page .qas-opt-fill.is-correct { background: #16a34a; }
    .qas-page .qas-opt-pct { width: 38px; text-align: right; color: #64748b; }
    .qas-page .qas-pct-pill {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 58px; padding: .2rem .45rem; border-radius: 99px;
        font-weight: 700; font-size: .8rem;
    }
    .qas-page .qas-pct-high { background: #dcfce7; color: #15803d; }
    .qas-page .qas-pct-mid { background: #fef3c7; color: #b45309; }
    .qas-page .qas-pct-low { background: #ffe4e6; color: #be123c; }
    .qas-page .qas-mgmt select.form-select-sm { font-size: .75rem; min-width: 88px; width: 100%; }
    .qas-page .qas-mgmt .qas-auto-select {
        margin-bottom: .35rem;
    }
    .qas-page .qas-mgmt .qas-auto-select.is-saving {
        opacity: .65;
        pointer-events: none;
    }
    .qas-page .qas-mgmt .qas-auto-select.is-saved {
        box-shadow: 0 0 0 1px #86efac;
    }
    .qas-page .qas-mgmt .qas-detail-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
        width: 100%;
        margin-top: .15rem;
        padding: .28rem .55rem;
        border: 1px solid #bae6fd;
        border-radius: 8px;
        background: linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%);
        color: #0369a1;
        font-size: .72rem;
        font-weight: 600;
        line-height: 1.2;
        transition: background .15s ease, border-color .15s ease, color .15s ease, box-shadow .15s ease;
    }
    .qas-page .qas-mgmt .qas-detail-btn:hover {
        background: linear-gradient(180deg, #e0f2fe 0%, #bae6fd 100%);
        border-color: #7dd3fc;
        color: #075985;
        box-shadow: 0 2px 8px rgba(14, 165, 233, .18);
    }
    .qas-page .qas-mgmt .qas-detail-btn:active {
        transform: translateY(1px);
    }
    .qas-page .qas-mgmt .qas-detail-btn svg {
        width: 13px;
        height: 13px;
        stroke-width: 2.2;
        flex-shrink: 0;
    }
    .qas-page #qas-list {
        scroll-margin-top: 80px;
    }
    .qas-page .sort-link { color: inherit; text-decoration: none; }
    .qas-page .sort-link:hover { color: #0284c7; }
    .qas-page .qas-conf-weak {
        background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa;
        font-size: .68rem; font-weight: 600; padding: .15rem .4rem; border-radius: 99px;
    }
    .qas-page .qas-conf-medium {
        background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;
        font-size: .68rem; font-weight: 600; padding: .15rem .4rem; border-radius: 99px;
    }
    .qas-page .qas-conf-reliable {
        background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0;
        font-size: .68rem; font-weight: 600; padding: .15rem .4rem; border-radius: 99px;
    }
    .qas-page .qas-question-cell button {
        border: 0; background: none; padding: 0; text-align: left;
        color: #0f172a; font-weight: 500; line-height: 1.35;
    }
    .qas-page .qas-question-cell button:hover { color: #0284c7; }
    #detailModal .qas-choice {
        display: flex; gap: .75rem; align-items: flex-start;
        padding: .65rem .8rem; border-radius: 10px; border: 1px solid #e2e8f0;
        margin-bottom: .5rem; background: #fff;
    }
    #detailModal .qas-choice.is-correct {
        border-color: #86efac; background: #f0fdf4;
    }
    #detailModal .qas-choice-letter {
        width: 28px; height: 28px; border-radius: 8px; background: #e2e8f0;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; flex-shrink: 0; font-size: .85rem;
    }
    #detailModal .qas-choice.is-correct .qas-choice-letter { background: #16a34a; color: #fff; }
    #detailModal .qas-meta-chip {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .25rem .55rem; border-radius: 99px; background: #f1f5f9;
        font-size: .78rem; color: #334155; margin: 0 .25rem .35rem 0;
    }
    #detailModal .qas-lang-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .75rem;
        margin-bottom: 1rem;
    }
    @media (max-width: 767px) {
        #detailModal .qas-lang-grid { grid-template-columns: 1fr; }
    }
    #detailModal .qas-lang-box {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: .75rem .85rem;
        background: #fff;
        min-height: 100%;
    }
    #detailModal .qas-lang-box.is-missing {
        border-style: dashed;
        background: #fff7ed;
    }
    #detailModal .qas-lang-tag {
        display: inline-block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: .4rem;
    }
    #detailModal .qas-lang-text {
        margin: 0;
        font-weight: 600;
        line-height: 1.45;
        color: #0f172a;
        font-size: .95rem;
    }
    #detailModal .qas-lang-missing {
        margin: 0;
        color: #c2410c;
        font-size: .85rem;
        font-style: italic;
    }
    #detailModal .qas-choice-langs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem;
    }
    @media (max-width: 767px) {
        #detailModal .qas-choice-langs { grid-template-columns: 1fr; }
    }
    #detailModal .qas-choice-en {
        color: #475569;
        font-size: .88rem;
        line-height: 1.4;
    }
    #detailModal .qas-choice-en.is-missing {
        color: #c2410c;
        font-style: italic;
        font-size: .8rem;
    }
</style>
@endpush

@section('content')
<div class="qas-page">
    <div class="qas-hero d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3>Soru Cevap Analizi</h3>
            <p class="qas-hero-sub">
                Gerçek kullanıcı cevaplarıyla zorluk ve başarı dağılımını izleyin.
                @if($lastCalculated)
                    · Son hesaplama: {{ \Carbon\Carbon::parse($lastCalculated)->format('d.m.Y H:i') }}
                @endif
                · Min. cevap: {{ $minAnswers }}
            </p>
        </div>
        @can('edit answer statistics')
            <form method="POST" action="{{ route('admin.question-answer-stats.refresh') }}" onsubmit="return confirm('Tüm istatistikler yeniden hesaplansın mı?')">
                @csrf
                <button type="submit" class="btn btn-light btn-sm">
                    <i data-feather="refresh-cw"></i> Şimdi Yenile
                </button>
            </form>
        @endcan
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php $s = $chartData['summary']; @endphp
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card qas-stat-card" role="button" tabindex="0" data-summary="analyzed" title="Özeti göster">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="qas-stat-label">Analiz edilen</div>
                        <p class="qas-stat-value">{{ number_format($s['analyzed']) }}</p>
                        <div class="qas-stat-hint">En az 1 cevaplı soru · tıkla</div>
                    </div>
                    <div class="qas-stat-icon qas-icon-blue"><i data-feather="database"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card qas-stat-card" role="button" tabindex="0" data-summary="answers" title="Özeti göster">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="qas-stat-label">Toplam cevap</div>
                        <p class="qas-stat-value">{{ number_format($s['total_answers']) }}</p>
                        <div class="qas-stat-hint">Quiz + düello + turnuva · tıkla</div>
                    </div>
                    <div class="qas-stat-icon qas-icon-green"><i data-feather="activity"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card qas-stat-card" role="button" tabindex="0" data-summary="success" title="Özeti göster">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="qas-stat-label">Ort. başarı</div>
                        <p class="qas-stat-value">{{ number_format($s['avg_success'], 1) }}%</p>
                        <div class="qas-stat-hint">Doğru cevap oranı · tıkla</div>
                    </div>
                    <div class="qas-stat-icon qas-icon-amber"><i data-feather="trending-up"></i></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card qas-stat-card" role="button" tabindex="0" data-summary="mismatch" title="Özeti göster">
                <div class="card-body d-flex justify-content-between align-items-start">
                    <div>
                        <div class="qas-stat-label">Uyumsuz (güvenilir)</div>
                        <p class="qas-stat-value">{{ number_format($s['mismatch']) }}</p>
                        <div class="qas-stat-hint">Tanımlı ≠ gözlenen · tıkla</div>
                    </div>
                    <div class="qas-stat-icon qas-icon-rose"><i data-feather="alert-triangle"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-4">
            <div class="card qas-chart-card">
                <div class="card-header">
                    <h6>Gözlenen zorluk</h6>
                    <small>Başarı oranına göre pasta dağılım</small>
                </div>
                <div class="qas-chart-wrap qas-chart-wrap-sm">
                    <canvas id="qasObservedPie"></canvas>
                </div>
                <div class="px-3 pb-3">
                    <p class="qas-chart-footnote mb-0">
                        Kolay ≥%70 · Orta %40–69 · Zor &lt;%40 başarı. En az 1 cevaplı sorular dahil edilir.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card qas-chart-card">
                <div class="card-header">
                    <h6>Tanımlı vs gözlenen</h6>
                    <small>Seviye karşılaştırması</small>
                </div>
                <div class="qas-chart-wrap qas-chart-wrap-sm">
                    <canvas id="qasCompareBar"></canvas>
                </div>
                <div class="px-3 pb-3">
                    <p class="qas-chart-footnote mb-0">
                        Mavi: adminin tanımladığı seviye. Mor: kullanıcı başarı oranından hesaplanan seviye.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card qas-chart-card">
                <div class="card-header">
                    <h6>En düşük başarı</h6>
                    <small>Min. 3 cevaplı en zor 8 soru</small>
                </div>
                <div class="qas-chart-wrap">
                    <canvas id="qasHardBar"></canvas>
                </div>
                <div class="px-3 pb-3">
                    <p class="qas-chart-footnote mb-0">
                        En az 3 cevabı olan sorulardan başarı oranı en düşük 8 tanesi. Tek cevaplı gürültü hariç tutulur.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card qas-filter-card">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.question-answer-stats.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label small text-muted mb-1">Ara</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="ID veya metin" value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small text-muted mb-1">Kategori</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                                {{ $category->getTranslation('name', 'tr') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label small text-muted mb-1">Zorluk</label>
                    <select name="level" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="easy" {{ request('level') === 'easy' ? 'selected' : '' }}>Kolay</option>
                        <option value="medium" {{ request('level') === 'medium' ? 'selected' : '' }}>Orta</option>
                        <option value="hard" {{ request('level') === 'hard' ? 'selected' : '' }}>Zor</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label small text-muted mb-1">Durum</label>
                    <select name="admin_status" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="active" {{ request('admin_status') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="passive" {{ request('admin_status') === 'passive' ? 'selected' : '' }}>Pasif</option>
                        <option value="maintenance" {{ request('admin_status') === 'maintenance' ? 'selected' : '' }}>Bakım</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <label class="form-label small text-muted mb-1">Gözlenen</label>
                    <select name="observed_difficulty" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="easy" {{ request('observed_difficulty') === 'easy' ? 'selected' : '' }}>Kolay</option>
                        <option value="medium" {{ request('observed_difficulty') === 'medium' ? 'selected' : '' }}>Orta</option>
                        <option value="hard" {{ request('observed_difficulty') === 'hard' ? 'selected' : '' }}>Zor</option>
                    </select>
                </div>
                <div class="col-4 col-md-2 col-lg-1">
                    <label class="form-label small text-muted mb-1">Başarı ≥</label>
                    <input type="number" step="0.1" min="0" max="100" name="success_min" class="form-control form-control-sm" value="{{ request('success_min') }}" placeholder="%">
                </div>
                <div class="col-4 col-md-2 col-lg-1">
                    <label class="form-label small text-muted mb-1">Başarı ≤</label>
                    <input type="number" step="0.1" min="0" max="100" name="success_max" class="form-control form-control-sm" value="{{ request('success_max') }}" placeholder="%">
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label small text-muted mb-1">Güven</label>
                    <select name="confidence" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="weak" {{ request('confidence') === 'weak' ? 'selected' : '' }}>Zayıf (1-2)</option>
                        <option value="medium" {{ request('confidence') === 'medium' ? 'selected' : '' }}>Orta (3-4)</option>
                        <option value="reliable" {{ request('confidence') === 'reliable' ? 'selected' : '' }}>Güvenilir (5+)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label small text-muted mb-1">Uyumsuz</label>
                    <select name="mismatch" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="1" {{ request('mismatch') === '1' ? 'selected' : '' }}>Güvenilir uyumsuz</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label small text-muted mb-1">Şüpheli şık</label>
                    <select name="suspicious" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="1" {{ request('suspicious') === '1' ? 'selected' : '' }}>Şüpheli (3+)</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 col-lg-1">
                    <label class="form-label small text-muted mb-1">EN çeviri</label>
                    <select name="missing_en" class="form-select form-select-sm">
                        <option value="">Tümü</option>
                        <option value="1" {{ request('missing_en') === '1' ? 'selected' : '' }}>EN yok</option>
                    </select>
                </div>
                <div class="col-12 col-lg-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3">Filtrele</button>
                    <a href="{{ route('admin.question-answer-stats.index') }}" class="btn btn-outline-secondary btn-sm">Temizle</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card qas-table-card" id="qas-list">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0">Soru listesi</h6>
                <small class="text-muted">Yalnızca en az 1 kez yanıtlanmış sorular · seçim anında kaydedilir</small>
            </div>
            <small class="text-muted">{{ $questions->total() }} kayıt</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive qas-table-scroll" id="qasTableScroll">
                <table class="table table-hover align-middle mb-0 qas-table">
                    <thead>
                    <tr>
                        @php
                            $currentSort = request('sort', 'total_answers');
                            $currentDir = request('dir', 'desc');
                            $toggleDir = $currentDir === 'asc' ? 'desc' : 'asc';
                        @endphp
                        <th>ID</th>
                        <th>Soru</th>
                        <th>Kategori</th>
                        <th>Tanımlı</th>
                        <th>Durum</th>
                        <th>
                            <a class="sort-link" href="{{ request()->fullUrlWithQuery(['sort' => 'total_answers', 'dir' => $currentSort === 'total_answers' ? $toggleDir : 'desc']) }}">Toplam</a>
                        </th>
                        <th>Güven</th>
                        <th>D / Y</th>
                        <th>
                            <a class="sort-link" href="{{ request()->fullUrlWithQuery(['sort' => 'correct_percentage', 'dir' => $currentSort === 'correct_percentage' ? $toggleDir : 'asc']) }}">Başarı</a>
                        </th>
                        <th>A-B-C-D</th>
                        <th>Gözlenen</th>
                        <th style="min-width: 200px;">Yönetim</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($questions as $question)
                        @php
                            $stat = $question->answerStat;
                            $total = $stat->total_answers ?? 0;
                            $pct = (float) ($stat->correct_percentage ?? 0);
                            $o1 = $stat->option_1_count ?? 0;
                            $o2 = $stat->option_2_count ?? 0;
                            $o3 = $stat->option_3_count ?? 0;
                            $o4 = $stat->option_4_count ?? 0;
                            $status = $question->admin_status ?? ($question->is_active ? 'active' : 'passive');
                            $correctOpt = (string) ($question->correct_answer ?? '');
                            $observed = $stat->observed_difficulty ?? 'insufficient';
                            $isMismatch = in_array($observed, ['easy','medium','hard'], true)
                                && $observed !== $question->question_level;
                            $pctClass = $pct >= 70 ? 'qas-pct-high' : ($pct >= 40 ? 'qas-pct-mid' : 'qas-pct-low');
                            $opts = [
                                '1' => $o1,
                                '2' => $o2,
                                '3' => $o3,
                                '4' => $o4,
                            ];
                            $optLabels = ['1' => 'A', '2' => 'B', '3' => 'C', '4' => 'D'];
                            if ($total >= 5) {
                                $confKey = 'reliable'; $confLabel = 'Güvenilir';
                            } elseif ($total >= 3) {
                                $confKey = 'medium'; $confLabel = 'Orta';
                            } else {
                                $confKey = 'weak'; $confLabel = 'Zayıf';
                            }
                            // Uyumsuz etiketi sadece güvenilir örneklemde anlamlı
                            $showMismatch = $isMismatch && $total >= 5;
                            $correctShare = $total > 0 ? (($opts[$correctOpt] ?? 0) / $total * 100) : 100;
                            $suspicious = false;
                            if ($total >= 3) {
                                if ($correctShare < 10) {
                                    $suspicious = true;
                                } else {
                                    foreach ($opts as $optKey => $optCount) {
                                        if ((string) $optKey === (string) $correctOpt) {
                                            continue;
                                        }
                                        if (($optCount / $total * 100) >= 70) {
                                            $suspicious = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            $textEn = $question->getTranslation('question', 'en', false);
                            $missingEn = empty($textEn);
                        @endphp
                        <tr>
                            <td class="text-muted">{{ $question->id }}</td>
                            <td>
                                <div class="qas-question-cell">
                                    <button type="button" class="view-detail-btn" data-question-id="{{ $question->id }}" title="Detayı aç">
                                        {{ \Illuminate\Support\Str::limit($question->getTranslation('question', 'tr'), 90) }}
                                    </button>
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        @if($suspicious)
                                            <span class="badge bg-danger">Şüpheli şık</span>
                                        @endif
                                        @if($missingEn)
                                            <span class="badge bg-warning text-dark">EN yok</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $question->category?->getTranslation('name', 'tr') ?? '-' }}</td>
                            <td>
                                @if($question->question_level === 'easy')
                                    <span class="badge bg-success qas-level-badge" data-level-badge>Kolay</span>
                                @elseif($question->question_level === 'medium')
                                    <span class="badge bg-warning text-dark qas-level-badge" data-level-badge>Orta</span>
                                @else
                                    <span class="badge bg-danger qas-level-badge" data-level-badge>Zor</span>
                                @endif
                            </td>
                            <td>
                                @if($status === 'active')
                                    <span class="badge bg-success qas-status-badge" data-status-badge>Aktif</span>
                                @elseif($status === 'maintenance')
                                    <span class="badge bg-info text-dark qas-status-badge" data-status-badge>Bakım</span>
                                @else
                                    <span class="badge bg-secondary qas-status-badge" data-status-badge>Pasif</span>
                                @endif
                            </td>
                            <td><strong>{{ $total }}</strong></td>
                            <td><span class="qas-conf-{{ $confKey }}">{{ $confLabel }}</span></td>
                            <td>
                                <span class="text-success">{{ $stat->correct_count ?? 0 }}</span>
                                /
                                <span class="text-danger">{{ $stat->wrong_count ?? 0 }}</span>
                            </td>
                            <td>
                                <span class="qas-pct-pill {{ $pctClass }}">{{ number_format($pct, 1) }}%</span>
                            </td>
                            <td>
                                <div class="qas-option-bars">
                                    @foreach($opts as $key => $count)
                                        @php $share = $total > 0 ? round($count / $total * 100, 1) : 0; @endphp
                                        <div class="qas-opt-row">
                                            <span class="qas-opt-label">{{ $optLabels[$key] }}</span>
                                            <div class="qas-opt-track">
                                                <div class="qas-opt-fill {{ $correctOpt === (string) $key ? 'is-correct' : '' }}" style="width: {{ $share }}%"></div>
                                            </div>
                                            <span class="qas-opt-pct">{{ $share }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if($observed === 'easy')
                                    <span class="badge bg-success">Kolay</span>
                                @elseif($observed === 'medium')
                                    <span class="badge bg-warning text-dark">Orta</span>
                                @elseif($observed === 'hard')
                                    <span class="badge bg-danger">Zor</span>
                                @else
                                    <span class="badge bg-light text-dark border">Yetersiz</span>
                                @endif
                                @if($showMismatch)
                                    <div class="small text-danger mt-1">Uyumsuz</div>
                                @endif
                            </td>
                            <td class="qas-mgmt">
                                @can('edit answer statistics')
                                    <select
                                        class="form-select form-select-sm qas-auto-select"
                                        data-auto-save="level"
                                        data-url="{{ route('admin.question-answer-stats.update-level', $question) }}"
                                        data-previous="{{ $question->question_level }}"
                                        title="Zorluk — seçince kaydedilir"
                                    >
                                        <option value="easy" {{ $question->question_level === 'easy' ? 'selected' : '' }}>Kolay</option>
                                        <option value="medium" {{ $question->question_level === 'medium' ? 'selected' : '' }}>Orta</option>
                                        <option value="hard" {{ $question->question_level === 'hard' ? 'selected' : '' }}>Zor</option>
                                    </select>
                                    <select
                                        class="form-select form-select-sm qas-auto-select"
                                        data-auto-save="status"
                                        data-url="{{ route('admin.question-answer-stats.update-status', $question) }}"
                                        data-previous="{{ $status }}"
                                        title="Durum — seçince kaydedilir"
                                    >
                                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                                        <option value="passive" {{ $status === 'passive' ? 'selected' : '' }}>Pasif</option>
                                        <option value="maintenance" {{ $status === 'maintenance' ? 'selected' : '' }}>Bakım</option>
                                    </select>
                                @endcan
                                <button type="button" class="qas-detail-btn view-detail-btn" data-question-id="{{ $question->id }}" title="Soru detayını aç">
                                    <i data-feather="eye"></i>
                                    <span>Detay</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted py-5">En az 1 kez yanıtlanmış soru bulunamadı.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($questions->hasPages())
            <div class="card-footer bg-white">
                {{ $questions->links('pagination::bootstrap-4') }}
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="summaryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="summaryModalTitle">Özet</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="summaryModalBody"></div>
            <div class="modal-footer" id="summaryModalFooter"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalTitle">Soru detayı</h5>
                <div class="d-flex align-items-center gap-2 ms-auto me-2">
                    <a id="detailEditBtn" href="#" target="_blank" class="btn btn-sm btn-primary d-none">
                        Soruyu düzenle
                    </a>
                    <button type="button" class="btn-close m-0" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="detailLoading" class="text-muted py-4 text-center">Yükleniyor...</div>
                <div id="detailContent" class="d-none">
                    <div class="qas-lang-grid">
                        <div class="qas-lang-box" id="detailBoxTr">
                            <div class="qas-lang-tag">TR</div>
                            <p class="qas-lang-text" id="detailTextTr"></p>
                        </div>
                        <div class="qas-lang-box" id="detailBoxEn">
                            <div class="qas-lang-tag">EN</div>
                            <p class="qas-lang-text" id="detailTextEn"></p>
                        </div>
                    </div>
                    <div id="detailImageWrap" class="mb-3 d-none">
                        <img id="detailImage" src="" alt="" class="img-fluid rounded border" style="max-height:220px;">
                    </div>
                    <div id="detailMeta" class="mb-3"></div>
                    <h6 class="mb-2">Şıklar <span class="text-muted fw-normal small">(TR · EN)</span></h6>
                    <div id="detailChoices"></div>
                    <h6 class="mt-3 mb-2">Son değişiklikler</h6>
                    <div id="detailLogsEmpty" class="text-muted small d-none">Henüz yönetim değişikliği yok.</div>
                    <div class="table-responsive d-none" id="detailLogsWrap">
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Yönetici</th>
                                <th>Alan</th>
                                <th>Eski</th>
                                <th>Yeni</th>
                            </tr>
                            </thead>
                            <tbody id="detailLogsBody"></tbody>
                        </table>
                    </div>
                </div>
                <div id="detailError" class="text-danger d-none">Detay yüklenemedi.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
{{-- Theme chart.min.js = Chart.js v1 (eski API). Modern grafikler için Chart.js 4 CDN --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
    window.qasChartData = @json($chartData);

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof feather !== 'undefined') {
            feather.replace();
        }

        // Listeyi basılı tutup yatay sürükleyerek kaydır
        (function initTableDragScroll() {
            const el = document.getElementById('qasTableScroll');
            if (!el) return;

            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;
            let moved = false;

            const interactive = 'a, button, select, input, textarea, label, .view-detail-btn';

            el.addEventListener('mousedown', function (e) {
                if (e.button !== 0) return;
                if (e.target.closest(interactive)) return;
                isDown = true;
                moved = false;
                startX = e.pageX - el.offsetLeft;
                scrollLeft = el.scrollLeft;
                el.classList.add('is-dragging');
            });

            const stopDrag = function () {
                if (!isDown) return;
                isDown = false;
                el.classList.remove('is-dragging');
            };

            window.addEventListener('mouseup', stopDrag);
            el.addEventListener('mouseleave', stopDrag);

            el.addEventListener('mousemove', function (e) {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - el.offsetLeft;
                const walk = (x - startX) * 1.2;
                if (Math.abs(walk) > 4) moved = true;
                el.scrollLeft = scrollLeft - walk;
            });

            // Sürüklerken yanlışlıkla tıklama olmasın
            el.addEventListener('click', function (e) {
                if (moved) {
                    e.preventDefault();
                    e.stopPropagation();
                    moved = false;
                }
            }, true);

            // Trackpad / mouse wheel yatay kaydırma desteği
            el.addEventListener('wheel', function (e) {
                if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
                if (e.shiftKey || Math.abs(e.deltaY) > 0 && el.scrollWidth > el.clientWidth) {
                    if (e.shiftKey) {
                        e.preventDefault();
                        el.scrollLeft += e.deltaY;
                    }
                }
            }, { passive: false });
        })();

        // Yönetim select: seçilince AJAX kaydet, sayfa yenilenmez / listede kalır
        (function initAutoSaveSelects() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const levelMap = {
                easy: { label: 'Kolay', cls: 'badge bg-success qas-level-badge' },
                medium: { label: 'Orta', cls: 'badge bg-warning text-dark qas-level-badge' },
                hard: { label: 'Zor', cls: 'badge bg-danger qas-level-badge' },
            };
            const statusMap = {
                active: { label: 'Aktif', cls: 'badge bg-success qas-status-badge' },
                passive: { label: 'Pasif', cls: 'badge bg-secondary qas-status-badge' },
                maintenance: { label: 'Bakım', cls: 'badge bg-info text-dark qas-status-badge' },
            };

            function notify(type, message) {
                if (typeof toastr !== 'undefined') {
                    toastr[type] ? toastr[type](message) : toastr.info(message);
                }
            }

            document.querySelectorAll('.qas-auto-select').forEach(function (select) {
                select.addEventListener('change', function () {
                    const url = select.dataset.url;
                    const kind = select.dataset.autoSave;
                    const previous = select.dataset.previous;
                    const value = select.value;
                    if (!url || value === previous) return;

                    const body = new FormData();
                    body.append('_method', 'PATCH');
                    if (kind === 'level') body.append('question_level', value);
                    if (kind === 'status') body.append('admin_status', value);

                    select.classList.add('is-saving');
                    select.classList.remove('is-saved');

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf || '',
                        },
                        body: body,
                    })
                        .then(function (r) {
                            if (!r.ok) throw new Error('save failed');
                            return r.json();
                        })
                        .then(function (data) {
                            select.dataset.previous = value;
                            select.classList.remove('is-saving');
                            select.classList.add('is-saved');
                            setTimeout(function () { select.classList.remove('is-saved'); }, 900);

                            const row = select.closest('tr');
                            if (row && kind === 'level' && levelMap[value]) {
                                const badge = row.querySelector('[data-level-badge]');
                                if (badge) {
                                    badge.className = levelMap[value].cls;
                                    badge.setAttribute('data-level-badge', '');
                                    badge.textContent = levelMap[value].label;
                                }
                            }
                            if (row && kind === 'status' && statusMap[value]) {
                                const badge = row.querySelector('[data-status-badge]');
                                if (badge) {
                                    badge.className = statusMap[value].cls;
                                    badge.setAttribute('data-status-badge', '');
                                    badge.textContent = statusMap[value].label;
                                }
                            }

                            if (!data.unchanged) {
                                notify('success', data.message || 'Kaydedildi');
                            }
                        })
                        .catch(function () {
                            select.value = previous;
                            select.classList.remove('is-saving');
                            notify('error', 'Kaydedilemedi, tekrar deneyin.');
                        });
                });
            });
        })();

        // Hash ile listeye dön (fallback / yenile sonrası)
        if (window.location.hash === '#qas-list') {
            const list = document.getElementById('qas-list');
            if (list) {
                setTimeout(function () {
                    list.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 50);
            }
        }

        const data = window.qasChartData || {};
        const observed = data.observed || {};
        const defined = data.defined || {};
        const topHard = data.top_hard || { labels: [], values: [], totals: [] };
        const topHardTotals = topHard.totals || [];

        const pieColors = ['#16a34a', '#f59e0b', '#e11d48', '#94a3b8'];

        if (typeof Chart !== 'undefined') {
        try {
            const observedPieEl = document.getElementById('qasObservedPie');
            if (observedPieEl) {
                new Chart(observedPieEl, {
                    type: 'doughnut',
                    data: {
                        labels: ['Kolay', 'Orta', 'Zor', 'Yetersiz'],
                        datasets: [{
                            data: [
                                observed.easy || 0,
                                observed.medium || 0,
                                observed.hard || 0,
                                observed.insufficient || 0
                            ],
                            backgroundColor: pieColors,
                            borderWidth: 0,
                            hoverOffset: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '62%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 14, font: { size: 11 } }
                            }
                        }
                    }
                });
            }

            const compareEl = document.getElementById('qasCompareBar');
            if (compareEl) {
                new Chart(compareEl, {
                    type: 'bar',
                    data: {
                        labels: ['Kolay', 'Orta', 'Zor'],
                        datasets: [
                            {
                                label: 'Tanımlı',
                                data: [defined.easy || 0, defined.medium || 0, defined.hard || 0],
                                backgroundColor: '#38bdf8',
                                borderRadius: 6,
                                maxBarThickness: 28
                            },
                            {
                                label: 'Gözlenen',
                                data: [observed.easy || 0, observed.medium || 0, observed.hard || 0],
                                backgroundColor: '#6366f1',
                                borderRadius: 6,
                                maxBarThickness: 28
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { boxWidth: 12, padding: 14, font: { size: 11 } }
                            }
                        },
                        scales: {
                            x: { grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                ticks: { precision: 0 },
                                grid: { color: '#f1f5f9' }
                            }
                        }
                    }
                });
            }

            const hardEl = document.getElementById('qasHardBar');
            if (hardEl) {
                const hardValues = (topHard.values || []).map(function (v) {
                    // %0 bar görünmüyordu; görsel için min 2 göster, tooltip gerçek değeri yazar
                    return Math.max(Number(v) || 0, 2);
                });
                new Chart(hardEl, {
                    type: 'bar',
                    data: {
                        labels: topHard.labels || [],
                        datasets: [{
                            label: 'Başarı %',
                            data: hardValues,
                            backgroundColor: '#fb7185',
                            borderRadius: 6,
                            maxBarThickness: 18
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        const real = (topHard.values || [])[ctx.dataIndex];
                                        const total = topHardTotals[ctx.dataIndex] || '-';
                                        return ' Başarı: ' + real + '% · ' + total + ' cevap';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: 100,
                                grid: { color: '#f1f5f9' },
                                ticks: { callback: (v) => v + '%' }
                            },
                            y: {
                                grid: { display: false },
                                ticks: { font: { size: 10 } }
                            }
                        }
                    }
                });
            }
        } catch (err) {
            console.error('QAS chart init error', err);
        }
        } else {
            console.error('Chart.js yüklenemedi');
        }

        const levelLabel = { easy: 'Kolay', medium: 'Orta', hard: 'Zor', insufficient: 'Yetersiz' };
        const statusLabel = { active: 'Aktif', passive: 'Pasif', maintenance: 'Bakım' };
        const summary = (window.qasChartData && window.qasChartData.summary) ? window.qasChartData.summary : {};
        const observedSummary = (window.qasChartData && window.qasChartData.observed) ? window.qasChartData.observed : {};
        const successBands = (window.qasChartData && window.qasChartData.success_bands) ? window.qasChartData.success_bands : {};

        function escapeHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function openSummaryModal(key) {
            const titleEl = document.getElementById('summaryModalTitle');
            const bodyEl = document.getElementById('summaryModalBody');
            const footerEl = document.getElementById('summaryModalFooter');
            const buckets = summary.answer_buckets || {};
            let title = 'Özet';
            let body = '';
            let footer = '<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Kapat</button>';

            if (key === 'analyzed') {
                title = 'Analiz edilen sorular';
                body = `
                    <div class="qas-sum-row"><span class="qas-sum-label">Toplam analiz</span><span class="qas-sum-value">${Number(summary.analyzed || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Zayıf güven (1-2 cevap)</span><span class="qas-sum-value">${Number(summary.weak || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Orta güven (3-4 cevap)</span><span class="qas-sum-value">${Number(summary.medium_conf || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Güvenilir (5+ cevap)</span><span class="qas-sum-value">${Number(summary.reliable || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Gözlenen kolay / orta / zor</span><span class="qas-sum-value">${observedSummary.easy || 0} / ${observedSummary.medium || 0} / ${observedSummary.hard || 0}</span></div>
                    <p class="qas-sum-note">Sadece en az 1 kez yanıtlanmış sorular listelenir.</p>
                `;
                footer += ` <a class="btn btn-primary btn-sm" href="{{ route('admin.question-answer-stats.index', ['confidence' => 'reliable']) }}">Güvenilirleri aç</a>`;
            } else if (key === 'answers') {
                title = 'Toplam cevap özeti';
                body = `
                    <div class="qas-sum-row"><span class="qas-sum-label">Toplam cevap</span><span class="qas-sum-value">${Number(summary.total_answers || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Soru başı ortalama</span><span class="qas-sum-value">${summary.avg_answers_per_question || 0}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">1 cevaplı soru</span><span class="qas-sum-value">${Number(buckets['1'] || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">2 cevaplı soru</span><span class="qas-sum-value">${Number(buckets['2'] || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">3-4 cevaplı soru</span><span class="qas-sum-value">${Number(buckets['3_4'] || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">5+ cevaplı soru</span><span class="qas-sum-value">${Number(buckets['5_plus'] || 0).toLocaleString('tr-TR')}</span></div>
                    <p class="qas-sum-note">Kaynaklar birleşik sayılır (quiz, düello, turnuva).</p>
                `;
            } else if (key === 'success') {
                title = 'Başarı oranı özeti';
                body = `
                    <div class="qas-sum-row"><span class="qas-sum-label">Ortalama başarı</span><span class="qas-sum-value">${summary.avg_success || 0}%</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Kolay bandı (≥%70)</span><span class="qas-sum-value">${Number(successBands.easy || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Orta bandı (%40-69)</span><span class="qas-sum-value">${Number(successBands.medium || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="qas-sum-row"><span class="qas-sum-label">Zor bandı (<%40)</span><span class="qas-sum-value">${Number(successBands.hard || 0).toLocaleString('tr-TR')}</span></div>
                    <p class="qas-sum-note">Eşikler: kolay ≥%70, orta ≥%40, altı zor.</p>
                `;
                footer += ` <a class="btn btn-outline-danger btn-sm" href="{{ route('admin.question-answer-stats.index', ['success_max' => 40]) }}">Zor bandını aç</a>`;
            } else if (key === 'mismatch') {
                title = 'Uyumsuz zorluk (güvenilir)';
                const pairs = summary.mismatch_pairs || [];
                const list = summary.mismatch_list || [];
                const pairsHtml = pairs.length
                    ? pairs.map(function (p) {
                        return `<div class="qas-sum-row"><span class="qas-sum-label">${escapeHtml(p.from)} → ${escapeHtml(p.to)}</span><span class="qas-sum-value">${p.count}</span></div>`;
                    }).join('')
                    : '<div class="text-muted">Uyumsuz kayıt yok.</div>';
                const listHtml = list.length
                    ? list.map(function (item) {
                        return `<div class="qas-sum-list-item"><strong>#${item.id}</strong> · ${escapeHtml(levelLabel[item.defined] || item.defined)} → ${escapeHtml(levelLabel[item.observed] || item.observed)} · %${item.pct} · ${item.total} cevap<br><span class="text-muted">${escapeHtml(item.text)}</span></div>`;
                    }).join('')
                    : '';
                body = `
                    <div class="qas-sum-row"><span class="qas-sum-label">Toplam güvenilir uyumsuz</span><span class="qas-sum-value">${Number(summary.mismatch || 0).toLocaleString('tr-TR')}</span></div>
                    <div class="mt-2 mb-1 small text-muted">Yön dağılımı</div>
                    ${pairsHtml}
                    ${listHtml ? '<div class="mt-3 mb-1 small text-muted">Örnekler</div>' + listHtml : ''}
                    <p class="qas-sum-note">Sadece 5+ cevaplı sorular sayılır.</p>
                `;
                footer += ` <a class="btn btn-primary btn-sm" href="{{ route('admin.question-answer-stats.index', ['mismatch' => 1]) }}">Listeyi aç</a>`;
            }

            titleEl.textContent = title;
            bodyEl.innerHTML = body;
            footerEl.innerHTML = footer;
            new bootstrap.Modal(document.getElementById('summaryModal')).show();
        }

        document.querySelectorAll('.qas-stat-card[data-summary]').forEach(function (card) {
            const open = function () { openSummaryModal(card.dataset.summary); };
            card.addEventListener('click', open);
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    open();
                }
            });
        });

        document.querySelectorAll('.view-detail-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const questionId = this.dataset.questionId;
                const modal = new bootstrap.Modal(document.getElementById('detailModal'));
                const loading = document.getElementById('detailLoading');
                const content = document.getElementById('detailContent');
                const error = document.getElementById('detailError');

                loading.classList.remove('d-none');
                content.classList.add('d-none');
                error.classList.add('d-none');
                document.getElementById('detailModalTitle').textContent = 'Soru #' + questionId;
                const editBtn = document.getElementById('detailEditBtn');
                editBtn.classList.add('d-none');
                editBtn.removeAttribute('href');
                modal.show();

                fetch(`{{ url('/admin/question-answer-stats') }}/${questionId}/detail`, {
                    headers: { 'Accept': 'application/json' }
                })
                    .then(r => r.json())
                    .then(payload => {
                        loading.classList.add('d-none');
                        if (!payload.success || !payload.question) {
                            error.classList.remove('d-none');
                            return;
                        }

                        const q = payload.question;
                        if (q.edit_url) {
                            editBtn.href = q.edit_url;
                            editBtn.classList.remove('d-none');
                        }

                        document.getElementById('detailTextTr').textContent = q.text_tr || '-';

                        const enBox = document.getElementById('detailBoxEn');
                        const enText = document.getElementById('detailTextEn');
                        if (q.missing_en || !q.text_en) {
                            enBox.classList.add('is-missing');
                            enText.className = 'qas-lang-missing';
                            enText.textContent = 'EN çeviri yok';
                        } else {
                            enBox.classList.remove('is-missing');
                            enText.className = 'qas-lang-text';
                            enText.textContent = q.text_en;
                        }

                        const imgWrap = document.getElementById('detailImageWrap');
                        const img = document.getElementById('detailImage');
                        if (q.image) {
                            img.src = q.image;
                            imgWrap.classList.remove('d-none');
                        } else {
                            img.removeAttribute('src');
                            imgWrap.classList.add('d-none');
                        }

                        const confClass = q.confidence.key === 'reliable'
                            ? 'qas-conf-reliable'
                            : (q.confidence.key === 'medium' ? 'qas-conf-medium' : 'qas-conf-weak');

                        const missingChoiceEn = (q.choices || []).some(function (c) { return c.missing_en; });
                        document.getElementById('detailMeta').innerHTML = `
                            <span class="qas-meta-chip">${escapeHtml(q.category || '-')}</span>
                            <span class="qas-meta-chip">Tanımlı: ${escapeHtml(levelLabel[q.level] || q.level)}</span>
                            <span class="qas-meta-chip">Gözlenen: ${escapeHtml(levelLabel[q.stats.observed] || q.stats.observed)}</span>
                            <span class="qas-meta-chip">Durum: ${escapeHtml(statusLabel[q.status] || q.status)}</span>
                            <span class="qas-meta-chip">Başarı: ${q.stats.percentage}% (${q.stats.correct}/${q.stats.total})</span>
                            <span class="${confClass}">${escapeHtml(q.confidence.label)} · ${escapeHtml(q.confidence.hint)}</span>
                            ${(q.missing_en || missingChoiceEn) ? '<span class="qas-meta-chip" style="background:#fff7ed;color:#c2410c;">EN eksik</span>' : ''}
                            ${q.suspicious ? '<span class="qas-meta-chip" style="background:#ffe4e6;color:#be123c;">Şüpheli şık dağılımı</span>' : ''}
                        `;

                        const choicesHtml = (q.choices || []).map(function (c) {
                            const enPart = c.missing_en || !c.text_en
                                ? '<div class="qas-choice-en is-missing">EN yok</div>'
                                : `<div class="qas-choice-en">${escapeHtml(c.text_en)}</div>`;
                            return `
                                <div class="qas-choice ${c.is_correct ? 'is-correct' : ''}">
                                    <div class="qas-choice-letter">${escapeHtml(c.label)}</div>
                                    <div class="flex-grow-1">
                                        <div class="qas-choice-langs">
                                            <div>
                                                <div class="small text-muted mb-1">TR</div>
                                                <div class="fw-medium">${escapeHtml(c.text_tr || '-')}</div>
                                            </div>
                                            <div>
                                                <div class="small text-muted mb-1">EN</div>
                                                ${enPart}
                                            </div>
                                        </div>
                                        <div class="small text-muted mt-1">${c.count} cevap · %${c.percent}${c.is_correct ? ' · doğru şık' : ''}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        document.getElementById('detailChoices').innerHTML = choicesHtml;

                        const logsWrap = document.getElementById('detailLogsWrap');
                        const logsEmpty = document.getElementById('detailLogsEmpty');
                        const logsBody = document.getElementById('detailLogsBody');
                        logsBody.innerHTML = '';
                        if (!q.logs || q.logs.length === 0) {
                            logsWrap.classList.add('d-none');
                            logsEmpty.classList.remove('d-none');
                        } else {
                            logsEmpty.classList.add('d-none');
                            q.logs.forEach(function (log) {
                                const tr = document.createElement('tr');
                                tr.innerHTML = `
                                    <td>${escapeHtml(log.created_at)}</td>
                                    <td>${escapeHtml(log.admin)}</td>
                                    <td>${escapeHtml(log.field)}</td>
                                    <td>${escapeHtml(log.old_value)}</td>
                                    <td>${escapeHtml(log.new_value)}</td>
                                `;
                                logsBody.appendChild(tr);
                            });
                            logsWrap.classList.remove('d-none');
                        }

                        content.classList.remove('d-none');
                    })
                    .catch(function () {
                        loading.classList.add('d-none');
                        error.classList.remove('d-none');
                    });
            });
        });
    });
</script>
@endpush
