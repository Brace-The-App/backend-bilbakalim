@extends('admin.layouts.app')

@section('title', 'AI Soru Kontrol')

@push('css')
<style>
.aqr-wrap { max-width: 100%; margin-top: 2.25rem; }
.aqr-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #334155 100%);
    border-radius: 16px;
    color: #fff;
    padding: 1.5rem 1.75rem;
    margin-top: 0.5rem;
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
    height: 100%; transition: box-shadow .15s ease, transform .15s ease;
}
a.aqr-stat-link { text-decoration: none; color: inherit; display: block; height: 100%; }
a.aqr-stat-link:hover .aqr-stat { box-shadow: 0 10px 24px rgba(15,23,42,.12); transform: translateY(-1px); }
a.aqr-stat-link.is-active .aqr-stat { outline: 2px solid #0f172a; }
.aqr-stat .card-body { padding: 1.15rem 1.25rem; }
.aqr-stat .label { font-size: .85rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
.aqr-stat .value { font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-top: .15rem; }
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
</style>
@endpush

@section('content')
<div class="container-fluid aqr-wrap">
    <div class="aqr-hero">
        <h3>AI Soru Kalite Kontrolleri</h3>
        <p>Claude inceleme sonuçları. Sadece bu hesap görür.</p>
        <p class="mt-2 mb-0" style="font-size:1.15rem;font-weight:650;color:#fff">
            Toplam <span style="color:#4ade80">{{ number_format($stats['questions_reviewed']) }}</span> soru kontrol edilmiş
            <span style="opacity:.75;font-weight:500;font-size:.95rem">
                ({{ number_format($stats['reviewed']) }} tamamlanan inceleme)
            </span>
        </p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="aqr-model">Aktif model (API): {{ $configuredModel }}</div>
            <div class="aqr-model" id="aqrLiveBadge"
                 style="{{ $stats['pending'] > 0 ? '' : 'display:none;' }}background:rgba(234,179,8,.25);border-color:rgba(250,204,21,.45)">
                <span class="aqr-live-dot"></span> Canlı · pending bitince yenilenir
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php
        // Kartlar birbirini değiştirir: status / max_score taşınmaz
        $filterKeep = array_filter([
            'q' => $q !== '' ? $q : null,
            'band' => $band !== '' ? $band : null,
            'per_page' => $perPage !== 50 ? $perPage : null,
        ], fn ($v) => $v !== null);
        $cardAll = $status === '' && $maxScore === null;
        $cardReviewed = $status === 'reviewed' && $maxScore === null;
        $cardLow = $maxScore !== null && (int) $maxScore === 60;
        $cardPending = $status === 'pending' && $maxScore === null;
        $cardFailed = $status === 'failed' && $maxScore === null;
    @endphp
    <div class="row g-2 mb-3">
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', $filterKeep) }}"
               class="aqr-stat-link {{ $cardAll ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body py-3">
                    <div class="label">Kontrol edilen soru</div>
                    <div class="value" style="color:#166534">{{ number_format($stats['questions_reviewed']) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['status' => 'reviewed'])) }}"
               class="aqr-stat-link {{ $cardReviewed ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body py-3">
                    <div class="label">Tamamlanan</div>
                    <div class="value">{{ number_format($stats['reviewed']) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['max_score' => 60, 'status' => 'reviewed'])) }}"
               class="aqr-stat-link {{ $cardLow ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body py-3">
                    <div class="label">Skor ≤ 60</div>
                    <div class="value" style="color:#c2410c">{{ number_format($stats['low_score'] ?? 0) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['status' => 'pending'])) }}"
               class="aqr-stat-link {{ $cardPending ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body py-3">
                    <div class="label">Pending</div>
                    <div class="value" style="color:#ca8a04">{{ number_format($stats['pending']) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <a href="{{ route('admin.question-quality-reviews.index', array_merge($filterKeep, ['status' => 'failed'])) }}"
               class="aqr-stat-link {{ $cardFailed ? 'is-active' : '' }}">
                <div class="card aqr-stat"><div class="card-body py-3">
                    <div class="label">Failed · sebepleri gör</div>
                    <div class="value" style="color:#b91c1c">{{ number_format($stats['failed']) }}</div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md">
            <div class="card aqr-stat"><div class="card-body py-3">
                <div class="label">Ort. skor</div>
                <div class="value">{{ $stats['avg_score'] ? number_format($stats['avg_score'], 1) : '—' }}</div>
            </div></div>
        </div>
    </div>

    <div class="aqr-bulk" id="aqrBulkBar">
        <span class="aqr-bulk-count"><span id="aqrSelCount">0</span> seçili</span>
        <select id="aqrBulkMode" class="form-select form-select-sm" style="max-width:220px">
            <option value="dry_run">Önizleme (yazma)</option>
            <option value="inactive_only">Sadece pasif sorulara uygula</option>
            <option value="live">Canlı sorulara uygula</option>
        </select>
        <button type="button" class="btn btn-sm btn-dark" id="aqrBulkRun">AI düzeltmesini çalıştır</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="aqrBulkClear">Seçimi temizle</button>
        <div class="small text-muted w-100 mb-0">Kategori değişmez. Varsayılan önizlemedir — canlıya yazmaz.</div>
        <div class="w-100 d-none" id="aqrBulkResultWrap">
            <pre class="aqr-preview mb-0" id="aqrBulkResult"></pre>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-body border-bottom">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small mb-1">Ara</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="review / question id">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Hepsi</option>
                        @foreach (['reviewed','pending','failed','expired'] as $st)
                            <option value="{{ $st }}" @selected($status === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Max skor</label>
                    <input type="number" name="max_score" value="{{ $maxScore }}" min="0" max="100" class="form-control form-control-sm" placeholder="örn. 60">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Band</label>
                    <input type="text" name="band" value="{{ $band }}" class="form-control form-control-sm" placeholder="high / Yüksek">
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
                        <th>Review</th>
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
                            $failReason = $review->status === 'failed'
                                ? (string) ($review->edit_reason
                                    ?: data_get($review->raw_response, 'fail_reason')
                                    ?: data_get($review->raw_response, 'error')
                                    ?: '')
                                : '';
                            $hasRevision = is_array($review->revised_content) && $review->revised_content !== [];
                            $isActiveQ = (bool) ($review->question?->is_active);
                            $laterSuccess = ($laterSuccessByQuestion[(int) $review->id] ?? null);
                        @endphp
                        <tr>
                            <td>
                                @if ($review->status === 'reviewed' && $hasRevision)
                                    <input type="checkbox" class="form-check-input aqr-row-check"
                                           value="{{ $review->id }}"
                                           data-score="{{ $score }}"
                                           data-active="{{ $isActiveQ ? '1' : '0' }}">
                                @endif
                            </td>
                            <td>
                                <div class="fw-semibold">#{{ $review->id }}</div>
                                <div class="small text-muted">{{ optional($review->reviewed_at ?? $review->assigned_at)->format('d.m H:i') ?: '—' }}</div>
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
                                    <div class="aqr-fail-reason" title="{{ $failReason }}">{{ $failReason }}</div>
                                @else
                                    <div class="small fw-semibold">{{ $review->recommended_action ?: '—' }}</div>
                                    <div class="small text-muted text-truncate" style="max-width:140px" title="{{ $review->quality_band }}">{{ $review->quality_band ?: '' }}</div>
                                    @if($hasRevision)
                                        <div class="small text-success">AI düzeltme hazır</div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <div class="small">{{ $review->model ?: '—' }}</div>
                                <div class="small text-muted">{{ $review->provider }}@if($review->package) · p{{ $review->package }}@endif</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $badge }}">{{ $review->status }}</span>
                                @php $attemptNo = (int) ($review->attempt ?? 1); @endphp
                                @if ($review->status === 'reviewed' && $attemptNo > 1)
                                    <div class="small text-success mt-1">{{ $attemptNo }}. deneme · başarılı</div>
                                @elseif ($review->status === 'failed' && $laterSuccess)
                                    <div class="small text-success mt-1">Sonraki deneme başarılı · #{{ $laterSuccess->id }}
                                        @if($laterSuccess->quality_score !== null) (skor {{ $laterSuccess->quality_score }}) @endif
                                    </div>
                                @elseif ($review->status === 'failed')
                                    <div class="small text-danger mt-1">{{ $attemptNo }}. deneme · fail · tekrar denenecek</div>
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
                                    Failed kayıt yok.
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
    var intervalMs = snapshot.pending > 0 ? 4000 : 12000;

    function selectedIds() {
        return Array.prototype.map.call(document.querySelectorAll('.aqr-row-check:checked'), function (el) {
            return parseInt(el.value, 10);
        }).filter(Boolean);
    }

    function syncBulkBar() {
        var ids = selectedIds();
        var bar = document.getElementById('aqrBulkBar');
        var count = document.getElementById('aqrSelCount');
        if (count) count.textContent = String(ids.length);
        if (bar) bar.classList.toggle('is-on', ids.length > 0);
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
            var mode = modeEl ? modeEl.value : 'dry_run';
            if (mode === 'live') {
                if (!window.confirm('CANLI soruların metni/şıkları değişecek. Emin misin? (Kategori değişmez)')) {
                    return;
                }
            }
            runBtn.disabled = true;
            runBtn.textContent = 'Çalışıyor…';
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
                    confirm_live: mode === 'live' ? 1 : 0
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
                })
                .catch(function () { alert('İstek başarısız'); })
                .finally(function () {
                    runBtn.disabled = false;
                    runBtn.textContent = 'AI düzeltmesini çalıştır';
                });
        });
    }

    function schedule() {
        timer = setTimeout(tick, intervalMs);
    }

    function tick() {
        fetch(pollUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (d) {
                var changed = d.pending !== snapshot.pending
                    || d.reviewed !== snapshot.reviewed
                    || d.failed !== snapshot.failed
                    || d.total !== snapshot.total
                    || d.latest_id !== snapshot.latest_id
                    || (d.latest_updated && d.latest_updated !== snapshot.latest_updated);

                if (changed && (
                    d.reviewed > snapshot.reviewed
                    || d.pending < snapshot.pending
                    || d.total > snapshot.total
                    || d.latest_id > snapshot.latest_id
                    || (d.latest_status === 'reviewed' && d.latest_id === snapshot.latest_id && d.latest_updated > snapshot.latest_updated)
                )) {
                    window.location.reload();
                    return;
                }

                snapshot = {
                    pending: d.pending,
                    reviewed: d.reviewed,
                    failed: d.failed,
                    total: d.total,
                    latest_id: d.latest_id,
                    latest_updated: d.latest_updated || 0
                };
                intervalMs = d.pending > 0 ? 4000 : 12000;
                var badge = document.getElementById('aqrLiveBadge');
                if (badge) {
                    badge.style.display = d.pending > 0 ? '' : 'none';
                }
                schedule();
            })
            .catch(function () { schedule(); });
    }

    schedule();
})();
</script>
@endpush
