@extends('admin.layouts.app')

@section('title', 'YZ Soru Kontrol')

@push('css')
<style>
.aqr-wrap {
    max-width: 100%;
}
.aqr-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #334155 100%);
    border-radius: 16px;
    color: #fff;
    padding: 1.5rem 1.75rem;
    margin-bottom: 1.25rem;
}
.aqr-hero h3 { color: #fff !important; margin: 0 0 .4rem; font-weight: 650; font-size: 1.55rem; }
.aqr-hero p { margin: 0; color: rgba(255,255,255,.85); font-size: 1rem; }
.aqr-hero .aqr-model {
    display: inline-flex; align-items: center; gap: .45rem;
    margin-top: .85rem; padding: .45rem .9rem; border-radius: 999px;
    background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2);
    font-size: .9rem; font-weight: 600;
}
.aqr-stat {
    border: 0; border-radius: 14px; box-shadow: 0 6px 18px rgba(15,23,42,.06);
    height: 100%; width: 100%; transition: box-shadow .15s ease, transform .15s ease;
}
.aqr-stat-row > [class*="col"] { display: flex; }
.aqr-stat-row .aqr-stat-link,
.aqr-stat-row .aqr-stat-btn {
    display: flex; flex: 1; width: 100%; height: 100%; text-decoration: none; color: inherit;
}
.aqr-stat-row .aqr-stat-link:hover .aqr-stat { box-shadow: 0 10px 24px rgba(15,23,42,.12); transform: translateY(-1px); }
.aqr-stat-row .aqr-stat-link.is-active .aqr-stat { outline: 2px solid #0f172a; }
.aqr-stat .card-body {
    padding: 1rem 1.1rem;
    width: 100%;
    min-height: 5.75rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: .5rem;
}
.aqr-stat .label {
    font-size: .78rem;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .03em;
    line-height: 1.3;
    min-height: 2.6em;
    display: flex;
    align-items: flex-end;
}
.aqr-stat .value {
    font-size: 1.75rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    font-feature-settings: "tnum";
    letter-spacing: -0.02em;
}
.aqr-fail-reason {
    max-width: 280px; font-size: .82rem; color: #b91c1c; line-height: 1.35;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.aqr-table th { white-space: nowrap; font-size: .85rem; color: #64748b; text-transform: uppercase; letter-spacing: .02em; padding: .9rem 1rem; }
.aqr-table td { padding: .95rem 1rem; font-size: .98rem; }
.aqr-score {
    display: inline-flex; min-width: 3rem; justify-content: center;
    font-weight: 700; border-radius: 10px; padding: .35rem .6rem; font-size: 1.05rem;
}
.aqr-score.hi { background: #dcfce7; color: #166534; }
.aqr-score.mid { background: #fef9c3; color: #854d0e; }
.aqr-score.lo { background: #ffedd5; color: #9a3412; }
.aqr-score.bad { background: #fee2e2; color: #991b1b; }
.aqr-q { max-width: 480px; }
.aqr-q .qid { font-size: .82rem; color: #94a3b8; margin-bottom: .15rem; }
.aqr-q .txt { font-size: 1.02rem; color: #0f172a; line-height: 1.4; white-space: normal; }
.aqr-wrap .form-control-sm, .aqr-wrap .form-select-sm { min-height: 42px; font-size: .95rem; padding: .45rem .75rem; }
.aqr-wrap .btn-sm { padding: .45rem .9rem; font-size: .9rem; }
.aqr-wrap .badge { font-size: .8rem; padding: .4em .65em; }
.aqr-live-dot {
    width: .55rem; height: .55rem; border-radius: 50%;
    background: #facc15; display: inline-block;
    animation: aqr-pulse 1.2s ease-in-out infinite;
}
@keyframes aqr-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: .45; transform: scale(.85); }
}
.aqr-pager { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; }
.aqr-pager .pagination { margin-bottom: 0; flex-wrap: wrap; }
.aqr-pager .page-link { min-width: 2.25rem; text-align: center; }
.aqr-bulk {
    display: none; position: sticky; top: 0; z-index: 5;
    background: #fff7ed; border: 1px solid #fdba74; border-radius: 12px;
    padding: .85rem 1rem; margin-bottom: 1rem;
}
.aqr-bulk.is-on { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem; }
.aqr-bulk .aqr-bulk-count { font-weight: 700; color: #9a3412; }
.aqr-preview {
    max-height: 280px; overflow: auto; background: #0f172a; color: #e2e8f0;
    border-radius: 10px; padding: .75rem 1rem; font-size: .82rem; white-space: pre-wrap;
}
.aqr-stat-btn { width:100%; text-align:left; border:0; background:transparent; padding:0; }
.dup-modal .modal-dialog { max-width: 920px; }
.dup-modal .modal-body { max-height: 70vh; overflow:auto; }
.dup-tabs { display:flex; gap:.35rem; flex-wrap:wrap; margin-bottom:.85rem; }
.dup-tabs button {
    font-size:.8rem; font-weight:600; padding:.35rem .7rem; border-radius:999px;
    border:1px solid #e2e8f0; color:#334155; background:#fff;
}
.dup-tabs button.is-on { background:#0f172a; color:#fff; border-color:#0f172a; }
.dup-group { background:#fff; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:.7rem; overflow:hidden; }
.dup-group-hd {
    display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.4rem;
    padding:.5rem .8rem; background:#f8fafc; border-bottom:1px solid #e2e8f0; font-size:.82rem;
}
.dup-group-hd .dup-group-actions { display:flex; flex-wrap:wrap; gap:.35rem; align-items:center; }
.dup-row { display:grid; grid-template-columns: 78px 1fr auto; gap:.55rem; align-items:start; padding:.7rem .8rem; border-top:1px solid #f1f5f9; }
.dup-row:first-of-type { border-top:0; }
.dup-row.is-keep { background:#f0fdf4; }
.dup-row.is-off { opacity:.55; }
.dup-mark { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding-top:.15rem; }
.dup-mark.keep { color:#166534; }
.dup-mark.copy { color:#9a3412; }
.dup-mark.off { color:#64748b; }
.dup-txt { font-size:.95rem; line-height:1.4; color:#0f172a; }
.dup-meta { font-size:.75rem; color:#94a3b8; margin-top:.2rem; }
.dup-acts { display:flex; flex-wrap:wrap; gap:.3rem; justify-content:flex-end; }
.dup-acts .btn { font-size:.72rem; padding:.18rem .45rem; }
@media (max-width: 700px) { .dup-row { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<div class="aqr-wrap">
    <div class="aqr-hero">
        <h3>Yapay Zekalı Soru Kontrolü</h3>
        <p>İnceleme sonuçları. Sadece bu hesap görür. Fail denemeler uygulanmaz; sadece son başarılı sonuç uygulanır.</p>
        <p class="mt-2 mb-0" style="font-size:1.15rem;font-weight:650;color:#fff">
            Toplam <span style="color:#4ade80">{{ number_format($stats['total']) }}</span> inceleme
            <span style="opacity:.85;font-weight:500;font-size:.95rem">
                · <span style="color:#4ade80">{{ number_format($stats['reviewed']) }}</span> başarılı
                · <span style="color:#fca5a5">{{ number_format($stats['failed']) }}</span> başarısız
                @if(($stats['pending'] ?? 0) > 0)
                    · <span style="color:#fde047">{{ number_format($stats['pending']) }}</span> beklemede
                @endif
                @if(($stats['expired'] ?? 0) > 0)
                    · {{ number_format($stats['expired']) }} süresi dolmuş
                @endif
            </span>
        </p>
        <p class="mt-1 mb-0" style="font-size:.95rem;font-weight:500;color:rgba(255,255,255,.8)">
            {{ number_format($stats['questions_reviewed']) }} soru başarıyla kontrol edildi
            · {{ number_format($stats['admin_accepted'] ?? 0) }} uygulandı
            · {{ number_format($stats['reviewed_open'] ?? 0) }} uygulama bekliyor
        </p>
        <p class="mt-1 mb-0" style="font-size:.95rem;font-weight:500;color:rgba(255,255,255,.8)">
            {{ number_format($stats['questions_failed'] ?? 0) }} başarısız soru
            <span style="opacity:.65"> · fail’ler onay / toplu uygulamaya girmez</span>
        </p>
        @php $byDay = $stats['reviewed_by_day'] ?? []; @endphp
        @if($byDay !== [])
            <p class="mt-2 mb-0" style="font-size:.88rem;font-weight:500;color:rgba(255,255,255,.78);line-height:1.55">
                Günlük başarılı kontrol
                <span style="opacity:.7">(cron: günde en fazla {{ (int) ($stats['daily_limit'] ?? 250) }})</span>:
                <span style="display:inline;color:rgba(255,255,255,.92)">
                    @foreach($byDay as $day => $cnt)
                        <span style="white-space:nowrap">{{ \Carbon\Carbon::parse($day)->format('d.m') }}: <strong style="color:#4ade80">{{ number_format($cnt) }}</strong>@if(!$loop->last) · @endif</span>
                    @endforeach
                </span>
            </p>
        @endif
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="aqr-model">Aktif model (API): {{ $configuredModel }}</div>
            <div class="aqr-model" id="aqrLiveBadge"
                 style="{{ $stats['pending'] > 0 ? '' : 'display:none;' }}background:rgba(234,179,8,.25);border-color:rgba(250,204,21,.45)">
                <span class="aqr-live-dot"></span> Canlı inceleme · seçim yokken 30 sn’de yenilenir
            </div>
        </div>
    </div>

    <div id="aqrRefreshBanner" class="alert alert-info d-none d-flex flex-wrap align-items-center justify-content-between gap-2" style="border-radius:12px">
        <span class="mb-0">Yeni inceleme sonucu var. Çoklu seçimin bozulmasın diye otomatik yenilemedik.</span>
        <button type="button" class="btn btn-sm btn-primary" id="aqrRefreshBtn">Listeyi yenile (seçimler korunur)</button>
    </div>

    @php
        // Kartlar birbirini değiştirir: status / max_score / admin_accepted taşınmaz
        $filterKeep = array_filter([
            'q' => $q !== '' ? $q : null,
            'band' => $band !== '' ? $band : null,
            'per_page' => $perPage !== 50 ? $perPage : null,
        ], fn ($v) => $v !== null);
        $adminAccepted = $adminAccepted ?? false;
        $scope = $scope ?? '';
        $cardAll = $status === '' && $maxScore === null && !$adminAccepted && $scope === '';
        $cardSuccess = $scope === 'all_success';
        $cardReviewed = $status === 'reviewed' && $maxScore === null && !$adminAccepted && $scope === '';
        $cardLow = $maxScore !== null && (int) $maxScore === 60 && !$adminAccepted;
        $cardPending = $status === 'pending' && $maxScore === null && !$adminAccepted;
        $cardFailed = $status === 'failed' && $maxScore === null && !$adminAccepted;
        $cardAdminAccepted = $adminAccepted;
    @endphp
    <div class="row g-2 mb-3 aqr-stat-row align-items-stretch">
        <div class="col-6 col-md">
            <button type="button" class="aqr-stat-link aqr-stat-btn" id="aqrDupOpen" data-bs-toggle="modal" data-bs-target="#aqrDupModal">
                <div class="card aqr-stat"><div class="card-body">
                    <div class="label">Benzer sorular</div>
                    <div class="value" id="aqrDupCount" style="color:#7c3aed">{{ isset($dupStats['involved']) ? number_format((int) $dupStats['involved']) : '—' }}</div>
                </div></div>
            </button>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['scope' => 'all_success'])) }}"
               class="aqr-stat-link {{ $cardSuccess ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body">
                    <div class="label">Başarılı kontrol</div>
                    <div class="value" style="color:#166534">{{ number_format($stats['questions_reviewed']) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['admin_accepted' => 1])) }}"
               class="aqr-stat-link {{ $cardAdminAccepted ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body">
                    <div class="label">Uygulandı</div>
                    <div class="value" style="color:#1d4ed8">{{ number_format($stats['admin_accepted'] ?? 0) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['status' => 'reviewed'])) }}"
               class="aqr-stat-link {{ $cardReviewed ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body">
                    <div class="label">Uygulama bekliyor</div>
                    <div class="value">{{ number_format($stats['reviewed_open'] ?? $stats['reviewed']) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['max_score' => 60, 'status' => 'reviewed'])) }}"
               class="aqr-stat-link {{ $cardLow ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body">
                    <div class="label">Skor ≤ 60</div>
                    <div class="value" style="color:#c2410c">{{ number_format($stats['low_score'] ?? 0) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['status' => 'pending'])) }}"
               class="aqr-stat-link {{ $cardPending ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body">
                    <div class="label">Beklemede</div>
                    <div class="value" style="color:#ca8a04">{{ number_format($stats['pending']) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['status' => 'failed'])) }}"
               class="aqr-stat-link {{ $cardFailed ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body">
                    <div class="label">Başarısız soru</div>
                    <div class="value" style="color:#b91c1c">{{ number_format($stats['questions_failed'] ?? 0) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md d-flex">
            <div class="card aqr-stat w-100"><div class="card-body">
                <div class="label">Ort. skor</div>
                <div class="value">{{ $stats['avg_score'] ? number_format($stats['avg_score'], 1) : '—' }}</div>
            </div></div>
        </div>
    </div>

    @if($adminAccepted)
        <div class="alert alert-primary py-2" style="border-radius:12px">
            Admin’in AI düzeltmesini uyguladığı sorular (metin/şık değişmiş olabilir). Ana listeden düşürüldüler.
        </div>
    @endif

    <div class="aqr-bulk" id="aqrBulkBar">
        <span class="aqr-bulk-count"><span id="aqrSelCount">0</span> seçili</span>
        <input type="hidden" id="aqrBulkMode" value="live">
        <button type="button" class="btn btn-sm btn-primary" id="aqrBulkRun">AI düzeltmesini uygula</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="aqrBulkClear">Seçimi temizle</button>
        <div class="small text-muted w-100 mb-0">Seçilen sorulara AI düzeltmesi yazılır (aktif/pasif fark etmez). Kategori değişmez.</div>
        <div class="w-100 d-none" id="aqrBulkResultWrap">
            <pre class="aqr-preview mb-0" id="aqrBulkResult"></pre>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-body border-bottom">
            <form method="get" class="row g-2 align-items-end">
                @if($adminAccepted)
                    <input type="hidden" name="admin_accepted" value="1">
                @endif
                <div class="col-md-2">
                    <label class="form-label small mb-1">Ara</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="inceleme / soru id">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Durum</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Hepsi</option>
                        @foreach ([
                            'reviewed' => 'İncelendi',
                            'pending' => 'Beklemede',
                            'failed' => 'Başarısız',
                            'expired' => 'Süresi doldu',
                        ] as $st => $stLabel)
                            <option value="{{ $st }}" @selected($status === $st)>{{ $stLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">En yüksek skor</label>
                    <input type="number" name="max_score" value="{{ $maxScore }}" min="0" max="100" class="form-control form-control-sm" placeholder="örn. 60">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Bant</label>
                    <input type="text" name="band" value="{{ $band }}" class="form-control form-control-sm" placeholder="yüksek">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Sayfa</label>
                    <select name="per_page" class="form-select form-select-sm">
                        @foreach ([25, 50, 100] as $pp)
                            <option value="{{ $pp }}" @selected((int)$perPage === $pp)>{{ $pp }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-dark btn-sm" type="submit">Filtrele</button>
                    <a href="{{ route('admin.question-quality-reviews.index') }}" class="btn btn-outline-secondary btn-sm">Temizle</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover aqr-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:2.5rem">
                            <input type="checkbox" class="form-check-input" id="aqrCheckAll" title="Sayfadakileri seç">
                        </th>
                        <th>İnceleme</th>
                        <th>Soru</th>
                        <th>Skor</th>
                        <th>Öneri / Sebep</th>
                        <th>Model</th>
                        <th>Durum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reviews as $review)
                        @php
                            $snap = is_array($review->question_snapshot) ? $review->question_snapshot : [];
                            $qTr = $snap['question_tr'] ?? null;
                            if (!$qTr && $review->question) {
                                $qTr = $review->question->getTranslation('question', 'tr', false);
                            }
                            $score = $review->quality_score;
                            $scoreClass = match (true) {
                                $score === null => '',
                                $score >= 80 => 'hi',
                                $score >= 60 => 'mid',
                                $score >= 40 => 'lo',
                                default => 'bad',
                            };
                            $badge = match ($review->status) {
                                'reviewed' => 'success',
                                'pending' => 'warning',
                                'failed' => 'danger',
                                default => 'secondary',
                            };
                            $statusLabel = match ($review->status) {
                                'reviewed' => 'İncelendi',
                                'pending' => 'Beklemede',
                                'failed' => 'Başarısız',
                                'expired' => 'Süresi doldu',
                                default => $review->status,
                            };
                            $failReason = $review->status === 'failed'
                                ? (string) (\App\Services\QuestionQualityReviewHelper::publicFailReason(
                                    (string) ($review->edit_reason
                                        ?: data_get($review->raw_response, 'fail_reason')
                                        ?: data_get($review->raw_response, 'error')
                                        ?: '')
                                ) ?? '')
                                : '';
                            // Liste: uzun teknik mesajı 2 satırda okunabilir tut
                            $failReasonShort = $failReason !== ''
                                ? \Illuminate\Support\Str::limit($failReason, 160)
                                : '';
                            $hasRevision = is_array($review->revised_content) && $review->revised_content !== [];
                            $isActiveQ = (bool) ($review->question?->is_active);
                            $laterSuccess = ($laterSuccessByQuestion[(int) $review->id] ?? null);
                            $alreadyApplied = (bool) ($review->question?->ai_accepted
                                && (int) $review->question?->ai_quality_review_id === (int) $review->id);
                            $attemptCountByQuestion = $attemptCountByQuestion ?? [];
                        @endphp
                        <tr>
                            <td>
                                @if ($review->status === 'reviewed' && $hasRevision && !$alreadyApplied && !$adminAccepted)
                                    <input type="checkbox" class="form-check-input aqr-row-check"
                                           value="{{ $review->id }}"
                                           data-score="{{ $score }}"
                                           data-active="{{ $isActiveQ ? '1' : '0' }}">
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">#{{ $review->id }}</div>
                                <div class="small text-muted">{{ tr_time($review->reviewed_at ?? $review->assigned_at, 'd.m H:i') }}</div>
                                @php $attemptNo = (int) ($review->attempt ?? 1); @endphp
                                @if ($attemptNo > 1 || $review->previous_review_id)
                                    <div class="small mt-1">
                                        <span class="badge bg-dark">Deneme {{ $attemptNo }}</span>
                                        @if ($review->previous_review_id)
                                            <span class="text-muted">← #{{ $review->previous_review_id }}
                                                @if($review->previousReview)
                                                    ({{ $review->previousReview->status }})
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                @elseif ($attemptNo === 1)
                                    <div class="small text-muted mt-1">Deneme 1</div>
                                @endif
                            </td>
                            <td>
                                <div class="aqr-q">
                                    <div class="qid">Q#{{ $review->question_id }}
                                        @if($review->question)
                                            · {{ $isActiveQ ? 'canlı' : 'pasif' }}
                                        @endif
                                        @php $qAttempts = (int) ($attemptCountByQuestion[$review->question_id] ?? $attemptNo); @endphp
                                        · {{ $qAttempts }} deneme
                                    </div>
                                    <div class="txt" title="{{ $qTr }}">{{ \Illuminate\Support\Str::limit($qTr ?: '—', 140) }}</div>
                                </div>
                            </td>
                            <td>
                                @if ($score !== null)
                                    <span class="aqr-score {{ $scoreClass }}">{{ $score }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($failReason !== '')
                                    <div class="aqr-fail-reason" title="{{ $failReason }}">{{ $failReasonShort }}</div>
                                @else
                                    <div class="small fw-semibold">{{ $review->recommended_action ?: '—' }}</div>
                                    <div class="small text-muted text-truncate" style="max-width:140px" title="{{ $review->quality_band }}">{{ $review->quality_band ?: '' }}</div>
                                    @if($hasRevision)
                                        <div class="small text-success">AI düzeltme hazır</div>
                                    @endif
                                    @if($adminAccepted || $alreadyApplied)
                                        <div class="small mt-1"><span class="badge bg-primary">Admin uyguladı</span></div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ $review->model ?: '—' }}</div>
                                <div class="small text-muted">{{ $review->provider }}@if($review->package) · p{{ $review->package }}@endif</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $badge }}">{{ $statusLabel }}</span>
                                @php $attemptNo = (int) ($review->attempt ?? 1); @endphp
                                @if ($review->status === 'reviewed' && $attemptNo > 1)
                                    <div class="small text-success mt-1">{{ $attemptNo }}. deneme · başarılı</div>
                                @elseif ($review->status === 'failed' && $laterSuccess)
                                    <div class="small text-success mt-1">Sonraki deneme başarılı · #{{ $laterSuccess->id }}
                                        @if($laterSuccess->quality_score !== null) (skor {{ $laterSuccess->quality_score }}) @endif
                                    </div>
                                @elseif ($review->status === 'failed')
                                    <div class="small text-danger mt-1">{{ $attemptNo }}. deneme · başarısız · bekliyor (otomatik tekrar yok)</div>
                                @elseif ($attemptNo > 1)
                                    <div class="small text-muted mt-1">{{ $attemptNo }}. deneme</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.question-quality-reviews.show', $review->id) }}" class="btn btn-sm btn-outline-dark">Detay</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                @if($status === 'failed')
                                    Başarısız kayıt yok.
                                @else
                                    Henüz kayıt yok.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white aqr-pager">
            <div class="small text-muted">
                {{ $reviews->firstItem() ?: 0 }}–{{ $reviews->lastItem() ?: 0 }}
                / {{ number_format($reviews->total()) }} kayıt
                · sayfa {{ $reviews->currentPage() }}/{{ max(1, $reviews->lastPage()) }}
                · {{ $reviews->perPage() }}/sayfa
            </div>
            @if ($reviews->hasPages())
                <div>{{ $reviews->links('pagination::bootstrap-4') }}</div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade dup-modal" id="aqrDupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Benzer sorular</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="dup-tabs">
                    <button type="button" class="is-on" data-dup-type="all">Tümü</button>
                    <button type="button" data-dup-type="exact">Birebir</button>
                    <button type="button" data-dup-type="near">Benzer</button>
                </div>
                <div id="aqrDupStatus" class="text-muted small mb-2">Yükleniyor…</div>
                <div id="aqrDupList"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var pollUrl = @json(route('admin.question-quality-reviews.poll'));
    var bulkUrl = @json(route('admin.question-quality-reviews.bulk-apply-revision'));
    var csrf = @json(csrf_token());
    var snapshot = {
        pending: {{ (int) $stats['pending'] }},
        reviewed: {{ (int) $stats['reviewed'] }},
        failed: {{ (int) $stats['failed'] }},
        total: {{ (int) $stats['total'] }},
        latest_id: {{ (int) ($reviews->first()?->id ?? 0) }},
        latest_updated: 0
    };
    var timer = null;
    // Pending varken 30 sn; seçim varken otomatik reload YOK
    var intervalMs = 30000;
    var storageKey = 'aqr_selected_ids';

    function selectedIds() {
        return Array.prototype.map.call(document.querySelectorAll('.aqr-row-check:checked'), function (el) {
            return parseInt(el.value, 10);
        }).filter(Boolean);
    }

    function saveSelection() {
        try {
            var ids = selectedIds();
            if (ids.length) {
                sessionStorage.setItem(storageKey, JSON.stringify(ids));
            } else {
                sessionStorage.removeItem(storageKey);
            }
        } catch (e) {}
    }

    function restoreSelection() {
        try {
            var raw = sessionStorage.getItem(storageKey);
            if (!raw) return;
            var ids = JSON.parse(raw);
            if (!Array.isArray(ids) || !ids.length) return;
            var set = {};
            ids.forEach(function (id) { set[String(id)] = true; });
            document.querySelectorAll('.aqr-row-check').forEach(function (el) {
                if (set[String(el.value)]) el.checked = true;
            });
            syncBulkBar();
        } catch (e) {}
    }

    function showRefreshBanner() {
        var banner = document.getElementById('aqrRefreshBanner');
        if (banner) banner.classList.remove('d-none');
    }

    var refreshBtn = document.getElementById('aqrRefreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            saveSelection();
            window.location.reload();
        });
    }

    function syncBulkBar() {
        var ids = selectedIds();
        var bar = document.getElementById('aqrBulkBar');
        var count = document.getElementById('aqrSelCount');
        if (count) count.textContent = String(ids.length);
        if (bar) bar.classList.toggle('is-on', ids.length > 0);
        saveSelection();
    }

    var checkAll = document.getElementById('aqrCheckAll');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.aqr-row-check').forEach(function (el) {
                el.checked = !!checkAll.checked;
            });
            syncBulkBar();
        });
    }
    document.querySelectorAll('.aqr-row-check').forEach(function (el) {
        el.addEventListener('change', syncBulkBar);
    });
    var clearBtn = document.getElementById('aqrBulkClear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            document.querySelectorAll('.aqr-row-check').forEach(function (el) { el.checked = false; });
            if (checkAll) checkAll.checked = false;
            syncBulkBar();
        });
    }

    var runBtn = document.getElementById('aqrBulkRun');
    if (runBtn) {
        runBtn.addEventListener('click', function () {
            var ids = selectedIds();
            if (!ids.length) return;
            var modeEl = document.getElementById('aqrBulkMode');
            var mode = modeEl ? modeEl.value : 'live';
            if (!window.confirm('Seçili soruların metni/şıkları AI düzeltmesiyle değişecek. Emin misin? (Kategori değişmez)')) {
                return;
            }
            runBtn.disabled = true;
            runBtn.textContent = 'Uygulanıyor…';
            fetch(bulkUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    ids: ids,
                    mode: mode,
                    confirm_live: 1
                })
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    var wrap = document.getElementById('aqrBulkResultWrap');
                    var pre = document.getElementById('aqrBulkResult');
                    if (wrap) wrap.classList.remove('d-none');
                    if (pre) {
                        var lines = [];
                        lines.push((res.j && res.j.message) ? res.j.message : (res.ok ? 'OK' : 'Hata'));
                        lines.push('mode=' + mode);
                        (res.j && res.j.results ? res.j.results : []).forEach(function (row) {
                            var p = row.preview || {};
                            lines.push(
                                '#' + row.review_id + ' Q' + row.question_id
                                + ' score=' + row.score
                                + (row.skipped ? ' SKIP' : (row.ok ? ' OK' : ' FAIL'))
                                + ' · ' + row.message
                            );
                            if (p.new_question_tr) {
                                lines.push('  eski: ' + (p.old_question_tr || ''));
                                lines.push('  yeni: ' + p.new_question_tr);
                                lines.push('  doğru: ' + p.old_correct + ' → ' + p.new_correct
                                    + (p.is_active ? ' [CANLI]' : ' [pasif]'));
                            }
                        });
                        pre.textContent = lines.join('\n');
                    }
                    // Uygulama sonrası seçimi temizle
                    try { sessionStorage.removeItem(storageKey); } catch (e) {}
                })
                .catch(function () { alert('İstek başarısız'); })
                .finally(function () {
                    runBtn.disabled = false;
                    runBtn.textContent = 'AI düzeltmesini uygula';
                });
        });
    }

    function schedule() {
        if (snapshot.pending <= 0) {
            timer = null;
            return;
        }
        timer = setTimeout(tick, intervalMs);
    }

    function tick() {
        fetch(pollUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (d) {
                var badge = document.getElementById('aqrLiveBadge');
                if (badge) {
                    badge.style.display = d.pending > 0 ? '' : 'none';
                }

                var hadSelection = selectedIds().length > 0;
                snapshot.pending = d.pending;

                // Çoklu seçim varken asla otomatik reload — band göster
                if (hadSelection) {
                    showRefreshBanner();
                    schedule();
                    return;
                }

                if (d.pending <= 0) {
                    window.location.reload();
                    return;
                }
                // Seçim yok + pending devam → 30 sn yenile
                window.location.reload();
            })
            .catch(function () { schedule(); });
    }

    restoreSelection();

    if (snapshot.pending > 0) {
        schedule();
    }

    var dupUrl = @json(route('admin.question-quality-reviews.duplicates'));
    var dupDeactivateUrl = @json(route('admin.question-quality-reviews.duplicates.deactivate'));
    var dupDeleteUrl = @json(route('admin.question-quality-reviews.duplicates.delete'));
    var dupDismissUrl = @json(route('admin.question-quality-reviews.duplicates.dismiss'));
    var dupEditBase = @json(url('/admin/questions'));
    var dupCache = null;
    var dupType = 'all';

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderDup() {
        var box = document.getElementById('aqrDupList');
        var st = document.getElementById('aqrDupStatus');
        if (!box || !dupCache) return;
        var groups = (dupCache.groups || []).filter(function (g) {
            return dupType === 'all' || g.type === dupType;
        });
        var stats = dupCache.stats || {};
        if (st) {
            st.textContent = (stats.involved || 0) + ' soru · '
                + (stats.exact_groups || 0) + ' birebir grup · '
                + (stats.near_groups || 0) + ' benzer grup';
        }
        var countEl = document.getElementById('aqrDupCount');
        if (countEl && stats.involved != null) {
            countEl.textContent = Number(stats.involved).toLocaleString('tr-TR');
        }
        if (!groups.length) {
            box.innerHTML = '<div class="alert alert-light border mb-0">Bu filtrede grup yok.</div>';
            return;
        }
        box.innerHTML = groups.map(function (g) {
            var qs = g.questions || [];
            var keepId = parseInt(g.keep_id || (qs[0] && qs[0].id) || 0, 10);
            var rows = qs.map(function (q) {
                var id = parseInt(q.id, 10);
                var isKeep = id === keepId;
                var isOff = !q.active;
                var mark = isOff ? 'Pasif' : (isKeep ? 'Tutulan' : 'Kopya');
                var cls = 'dup-row' + (isKeep ? ' is-keep' : '') + (isOff ? ' is-off' : '');
                var markCls = isOff ? 'off' : (isKeep ? 'keep' : 'copy');
                var acts = '<a class="btn btn-outline-secondary" href="' + dupEditBase + '/' + id + '/edit">Düzenle</a>';
                if (!isKeep && !isOff) {
                    acts += '<button type="button" class="btn btn-outline-danger" data-dup-act="passive" data-id="' + id + '">Pasif</button>';
                }
                if (!isKeep) {
                    acts += '<button type="button" class="btn btn-danger" data-dup-act="delete" data-id="' + id + '">Sil</button>';
                }
                return '<div class="' + cls + '">'
                    + '<div class="dup-mark ' + markCls + '">' + mark + '</div>'
                    + '<div><div class="dup-txt">' + esc(q.text) + '</div>'
                    + '<div class="dup-meta">#' + id + ' · ' + esc(q.category || '—') + ' · ' + esc(q.level || '—') + '</div></div>'
                    + '<div class="dup-acts">' + acts + '</div></div>';
            }).join('');
            var badge = g.type === 'exact'
                ? '<span class="badge bg-danger">Birebir</span>'
                : '<span class="badge bg-warning text-dark">Benzer</span>';
            var idsAttr = qs.map(function (q) { return parseInt(q.id, 10); }).join(',');
            return '<div class="dup-group" data-dup-type="' + esc(g.type || 'near') + '"><div class="dup-group-hd">'
                + '<div>' + badge
                + '<span class="text-muted ms-1">' + qs.length + ' kayıt · tutulan #' + keepId + '</span></div>'
                + '<div class="dup-group-actions">'
                + '<button type="button" class="btn btn-sm btn-outline-secondary" data-dup-act="dismiss" data-type="' + esc(g.type || 'near') + '" data-ids="' + idsAttr + '">Listeden çıkar</button>'
                + '</div></div>'
                + rows + '</div>';
        }).join('');
    }

    function loadDup(fresh) {
        var st = document.getElementById('aqrDupStatus');
        if (st) st.textContent = 'Yükleniyor…';
        var url = dupUrl + (fresh ? '?fresh=1' : '');
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (d) { dupCache = d; renderDup(); })
            .catch(function () {
                if (st) st.textContent = 'Liste yüklenemedi.';
            });
    }

    function postDupForm(url, fields) {
        var body = new URLSearchParams();
        Object.keys(fields).forEach(function (k) {
            var v = fields[k];
            if (Array.isArray(v)) {
                v.forEach(function (item) { body.append(k + '[]', String(item)); });
            } else {
                body.set(k, String(v));
            }
        });
        return fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: body.toString()
        }).then(function (r) {
            return r.json().then(function (j) { return { ok: r.ok, j: j }; });
        });
    }

    function postDup(url, id) {
        return postDupForm(url, { question_id: id });
    }

    var dupModal = document.getElementById('aqrDupModal');
    if (dupModal) {
        dupModal.addEventListener('show.bs.modal', function () {
            if (!dupCache) loadDup(false);
            else renderDup();
        });
        document.querySelectorAll('[data-dup-type]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                dupType = btn.getAttribute('data-dup-type') || 'all';
                document.querySelectorAll('[data-dup-type]').forEach(function (b) {
                    b.classList.toggle('is-on', b === btn);
                });
                renderDup();
            });
        });
        var list = document.getElementById('aqrDupList');
        if (list) {
            list.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-dup-act]');
                if (!btn) return;
                var id = parseInt(btn.getAttribute('data-id'), 10);
                var act = btn.getAttribute('data-dup-act');
                if (!id && act !== 'dismiss') return;
                if (act === 'passive' && !window.confirm('Soru #' + id + ' pasif olsun?')) return;
                if (act === 'delete' && !window.confirm('Soru #' + id + ' silinsin? Geri alınamaz.')) return;
                if (act === 'dismiss') {
                    var ids = (btn.getAttribute('data-ids') || '').split(',').map(function (x) { return parseInt(x, 10); }).filter(Boolean);
                    if (ids.length < 2) return;
                    if (!window.confirm('Bu grup aynı soru değil — listeden çıkarılsın mı? Sorulara dokunulmaz.')) return;
                    postDupForm(dupDismissUrl, { question_ids: ids, type: btn.getAttribute('data-type') || 'near' })
                        .then(function (res) {
                            if (window.toastr) {
                                if (res.ok && res.j && res.j.success) toastr.success(res.j.message);
                                else toastr.error((res.j && res.j.message) || 'İşlem başarısız');
                            }
                            if (res.ok) loadDup(true);
                        })
                        .catch(function () {
                            if (window.toastr) toastr.error('İstek başarısız');
                        });
                    return;
                }
                postDup(act === 'delete' ? dupDeleteUrl : dupDeactivateUrl, id)
                    .then(function (res) {
                        if (window.toastr) {
                            if (res.ok && res.j && res.j.success) toastr.success(res.j.message);
                            else toastr.error((res.j && res.j.message) || 'İşlem başarısız');
                        }
                        if (res.ok) loadDup(true);
                    })
                    .catch(function () {
                        if (window.toastr) toastr.error('İstek başarısız');
                    });
            });
        }
    }
})();
</script>
@endpush
