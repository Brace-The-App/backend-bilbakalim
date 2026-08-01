@extends('admin.layouts.app')

@section('title', 'AI Soru Kontrol')

@push('css')
<style>
.aqr-wrap { max-width: 100%; }
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
    height: 100%;
}
.aqr-stat .card-body { padding: 1.15rem 1.25rem; }
.aqr-stat .label { font-size: .85rem; color: #64748b; text-transform: uppercase; letter-spacing: .03em; }
.aqr-stat .value { font-size: 1.85rem; font-weight: 700; color: #0f172a; margin-top: .15rem; }
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

    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <div class="card aqr-stat"><div class="card-body py-3">
                <div class="label">Kontrol edilen soru</div>
                <div class="value" style="color:#166534">{{ number_format($stats['questions_reviewed']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card aqr-stat"><div class="card-body py-3">
                <div class="label">Tamamlanan inceleme</div>
                <div class="value">{{ number_format($stats['reviewed']) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card aqr-stat"><div class="card-body py-3">
                <div class="label">Pending / Failed</div>
                <div class="value">{{ $stats['pending'] }} <span style="color:#94a3b8;font-weight:500">/</span> <span style="color:#b91c1c">{{ $stats['failed'] }}</span></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card aqr-stat"><div class="card-body py-3">
                <div class="label">Ort. skor</div>
                <div class="value">{{ $stats['avg_score'] ? number_format($stats['avg_score'], 1) : '—' }}</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-body border-bottom">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
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
                    <label class="form-label small mb-1">Band</label>
                    <input type="text" name="band" value="{{ $band }}" class="form-control form-control-sm" placeholder="high / Yüksek">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-dark btn-sm" type="submit">Filtrele</button>
                    <a href="{{ route('admin.question-quality-reviews.index') }}" class="btn btn-outline-secondary btn-sm">Temizle</a>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover aqr-table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Review</th>
                        <th>Soru</th>
                        <th>Skor</th>
                        <th>Öneri</th>
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
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">#{{ $review->id }}</div>
                                <div class="small text-muted">{{ optional($review->reviewed_at ?? $review->assigned_at)->format('d.m H:i') ?: '—' }}</div>
                            </td>
                            <td>
                                <div class="aqr-q">
                                    <div class="qid">Q#{{ $review->question_id }}</div>
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
                                <div class="small fw-semibold">{{ $review->recommended_action ?: '—' }}</div>
                                <div class="small text-muted text-truncate" style="max-width:140px" title="{{ $review->quality_band }}">{{ $review->quality_band ?: '' }}</div>
                            </td>
                            <td>
                                <div class="small">{{ $review->model ?: '—' }}</div>
                                <div class="small text-muted">{{ $review->provider }}@if($review->package) · p{{ $review->package }}@endif</div>
                            </td>
                            <td><span class="badge bg-{{ $badge }}">{{ $review->status }}</span></td>
                            <td class="text-end">
                                <a href="{{ route('admin.question-quality-reviews.show', $review->id) }}" class="btn btn-sm btn-outline-dark">Detay</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">Henüz kayıt yok.</td>
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
                <div>{{ $reviews->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var pollUrl = @json(route('admin.question-quality-reviews.poll'));
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

                // Pending bitti / yeni reviewed geldi → sayfayı yenile
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
