@extends('admin.layouts.app')

@section('title', 'AI Review #' . $review->id)

@push('css')
<style>
.aqr-d { max-width: 100%; }
.aqr-top {
    background: #0f172a; color: #fff; border-radius: 16px; padding: 1.4rem 1.6rem; margin-bottom: 1.25rem;
}
.aqr-top h3 { color:#fff!important; margin:0; font-size:1.45rem; font-weight:650; }
.aqr-top .meta { color: rgba(255,255,255,.8); font-size:1rem; margin-top:.45rem; line-height:1.45; }
.aqr-actions { display:flex; flex-wrap:wrap; gap:.65rem; margin-top:1rem; }
.aqr-actions .btn { font-weight:600; padding: .55rem 1rem; font-size: .95rem; }
.aqr-top .btn-outline-light,
.aqr-actions .btn-outline-light {
    color: #fff !important;
    border-color: rgba(255,255,255,.85) !important;
}
.aqr-top .btn-outline-light:hover,
.aqr-actions .btn-outline-light:hover {
    color: #0f172a !important;
    background: #fff !important;
    border-color: #fff !important;
}
.aqr-panel {
    border:0; border-radius:14px; box-shadow: 0 6px 18px rgba(15,23,42,.06); margin-bottom:1.15rem;
}
.aqr-panel .card-header {
    background:#fff; border-bottom:1px solid #e2e8f0; font-weight:650; font-size:1.08rem; padding:1rem 1.25rem;
}
.aqr-panel .card-body { padding: 1.2rem 1.3rem; font-size: 1.02rem; }
.aqr-kpi {
    display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:.85rem;
}
@media (min-width:768px){ .aqr-kpi { grid-template-columns: repeat(4, minmax(0,1fr)); } }
.aqr-kpi .item {
    background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.1rem; min-height: 92px;
}
.aqr-kpi .k { font-size:.82rem; color:#64748b; text-transform:uppercase; letter-spacing:.03em; }
.aqr-kpi .v { font-size:1.35rem; font-weight:700; color:#0f172a; margin-top:.3rem; word-break:break-word; line-height:1.3; }
.aqr-choice {
    border:1px solid #e2e8f0; border-radius:12px; padding:.85rem 1rem; margin-bottom:.65rem; background:#fff;
}
.aqr-choice.is-correct { border-color:#86efac; background:#f0fdf4; }
.aqr-choice .num {
    display:inline-flex; width:1.85rem; height:1.85rem; align-items:center; justify-content:center;
    border-radius:999px; background:#e2e8f0; font-size:.85rem; font-weight:700; margin-right:.45rem;
}
.aqr-choice.is-correct .num { background:#16a34a; color:#fff; }
.aqr-choice strong { font-size:1.05rem; }
.aqr-choice .small { font-size: .92rem !important; }
.aqr-msg {
    background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:1.15rem 1.25rem; color:#78350f; line-height:1.6; font-size:1.05rem;
}
.aqr-crit td { vertical-align: middle; padding: .75rem .65rem; font-size: .98rem; }
.aqr-bar {
    height:12px; background:#e2e8f0; border-radius:999px; overflow:hidden; min-width:100px;
}
.aqr-bar > span { display:block; height:100%; background:#0f172a; }
.aqr-diff-col h6 { font-weight:700; margin-bottom:.65rem; font-size:1.05rem; }
.aqr-diff-col p { font-size:1.05rem; line-height:1.5; }
.aqr-diff-col ol { padding-left:1.2rem; margin-bottom:.5rem; font-size:1.02rem; }
.aqr-diff-col li { margin-bottom: .35rem; }
.aqr-raw {
    max-height: 520px; overflow:auto; background:#0b1220; color:#e2e8f0;
    border-radius:12px; padding:1.15rem 1.25rem; font-size:.9rem; white-space:pre-wrap; line-height:1.45;
}
.aqr-d .fw-semibold { font-size: 1.08rem; }
.aqr-modal .modal-title,
.aqr-modal .modal-body,
.aqr-modal .modal-body p,
.aqr-modal .modal-body li,
.aqr-modal .modal-body h6 { color: #0f172a !important; }
.aqr-modal .modal-body .text-muted { color: #64748b !important; }
.aqr-modal .modal-body .text-success { color: #15803d !important; }
</style>
@endpush

@section('content')
@php
    $snap = is_array($review->question_snapshot) ? $review->question_snapshot : [];
    $raw = is_array($review->raw_response) ? $review->raw_response : [];
    $analiz = is_array($raw['analiz_sonucu'] ?? null) ? $raw['analiz_sonucu'] : [];
    $ek = is_array($analiz['ek_analizler'] ?? null) ? $analiz['ek_analizler'] : [];
    $criteria = is_array($review->criteria_scores) && $review->criteria_scores !== []
        ? $review->criteria_scores
        : (is_array($analiz['kriter_analizleri'] ?? null) ? $analiz['kriter_analizleri'] : []);
    $revised = is_array($review->revised_content) && $review->revised_content !== []
        ? $review->revised_content
        : (is_array($analiz['duzeltilmis_icerik'] ?? null) ? $analiz['duzeltilmis_icerik'] : null);
    $analizMsg = $analiz['analiz_mesaji'] ?? null;
    $editReason = $review->edit_reason ?: ($analiz['duzeltme_gerekcesi'] ?? null);
    $question = $review->question;
    $isActive = $question ? (bool) $question->is_active : null;
    $score = $review->quality_score;
@endphp

<div class="container-fluid aqr-d">
    <div class="aqr-top">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
            @php
                $h3Attempt = (int) ($review->attempt ?? 1);
            @endphp
                <h3>Review #{{ $review->id }} · Soru #{{ $review->question_id }}
                    @if ($h3Attempt > 1)
                        · Deneme {{ $h3Attempt }}
                    @endif
                </h3>
                <div class="meta">
                    Kayıt model: <strong>{{ $review->model ?: '—' }}</strong>
                    · paket {{ $review->package ?: '—' }}
                    · API şu an: <strong>{{ $configuredModel }}</strong>
                    · {{ optional($review->reviewed_at)->format('d.m.Y H:i') ?: 'henüz tamamlanmadı' }}
                    @if ($review->previous_review_id)
                        · önceki: <a class="link-light" href="{{ route('admin.question-quality-reviews.show', $review->previous_review_id) }}">#{{ $review->previous_review_id }}</a>
                    @endif
                </div>
            </div>
            <div>
                <a href="{{ route('admin.question-quality-reviews.index') }}" class="btn btn-sm btn-outline-light">Liste</a>
            </div>
        </div>

        <div class="aqr-actions">
            @if ($question)
                @if ($isActive)
                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#aqrDeactivateModal">
                        Soruyu pasife al
                    </button>
                @else
                    <form method="post" action="{{ route('admin.question-quality-reviews.activate', $review->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Soruyu aktif et</button>
                    </form>
                @endif
            @endif

            @if ($revised)
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#aqrApplyRevisionModal">
                    AI düzeltmesini uygula
                </button>
            @endif

            @if ($question)
                <a href="{{ route('admin.questions.edit', $question->id) }}" class="btn btn-outline-light btn-sm" target="_blank">Soru edit sayfası</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($review->status === 'failed')
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Failed · inceleme başarısız</div>
            <div>{{ $editReason ?: (data_get($raw, 'fail_reason') ?: data_get($raw, 'error') ?: 'Sebep kaydı yok.') }}</div>
        </div>
    @endif

    <div class="aqr-kpi mb-3">
        <div class="item"><div class="k">Skor</div><div class="v">{{ $score !== null ? $score : '—' }}</div></div>
        <div class="item"><div class="k">Kalite bandı</div><div class="v" style="font-size:1rem">{{ $review->quality_band ?: '—' }}</div></div>
        <div class="item"><div class="k">Önerilen işlem</div><div class="v" style="font-size:1rem">{{ $review->recommended_action ?: ($analiz['onerilen_islem'] ?? '—') }}</div></div>
        <div class="item"><div class="k">Tahmini zorluk</div><div class="v" style="font-size:1rem">{{ $review->estimated_difficulty ?: ($ek['tahmini_zorluk'] ?? '—') }}</div></div>
        <div class="item"><div class="k">Sıkıcılık riski</div><div class="v">{{ $review->boredom_risk ?? ($ek['tahmini_sikicilik_riski'] ?? '—') }}%</div></div>
        <div class="item"><div class="k">Belirsizlik</div><div class="v">{{ $review->ambiguity_risk ?? ($ek['belirsizlik_riski'] ?? '—') }}%</div></div>
        <div class="item"><div class="k">Mükerrerlik</div><div class="v">{{ $review->duplicate_risk ?? ($ek['mukerrerlik_riski'] ?? '—') }}%</div></div>
        <div class="item"><div class="k">Bilgi güveni</div><div class="v">{{ $review->knowledge_confidence ?? ($ek['bilgi_dogrulugu_guveni'] ?? '—') }}%</div></div>
    </div>

    @if ($analizMsg || $editReason)
    <div class="card aqr-panel">
        <div class="card-header">AI değerlendirme özeti</div>
        <div class="card-body">
            @if ($analizMsg)
                <div class="aqr-msg mb-3">{{ $analizMsg }}</div>
            @endif
            @if ($editReason)
                <div class="small text-muted mb-1">Düzeltme gerekçesi</div>
                <div>{{ $editReason }}</div>
            @endif
        </div>
    </div>
    @endif

    <div class="row">
        <div class="col-lg-6">
            <div class="card aqr-panel">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Orijinal soru (inceleme anı)</span>
                    @if ($question)
                        <span class="badge {{ $isActive ? 'bg-success' : 'bg-secondary' }}">
                            şu an {{ $isActive ? 'aktif' : 'pasif' }}
                            @if($question->admin_status) · {{ $question->admin_status }} @endif
                        </span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-1">{{ $snap['category_tr'] ?? '' }} @if(!empty($snap['category_en'])) / {{ $snap['category_en'] }} @endif</div>
                    <p class="fw-semibold mb-1">{{ $snap['question_tr'] ?? '—' }}</p>
                    <p class="text-muted small mb-3">{{ $snap['question_en'] ?? '' }}</p>
                    @for ($i = 1; $i <= 4; $i++)
                        @php $correct = (string)($snap['correct_choice_id'] ?? '') === (string)$i; @endphp
                        <div class="aqr-choice {{ $correct ? 'is-correct' : '' }}">
                            <span class="num">{{ $i }}</span>
                            <strong>{{ $snap['choice'.$i.'_tr'] ?? '—' }}</strong>
                            <div class="small text-muted ms-4">{{ $snap['choice'.$i.'_en'] ?? '' }}</div>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card aqr-panel">
                <div class="card-header">Kriter puanları (100)</div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-sm aqr-crit mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Kriter</th>
                                <th>Puan</th>
                                <th style="width:35%">Oran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($criteria as $key => $row)
                                @php
                                    $puan = (int) ($row['puan'] ?? $row['score'] ?? 0);
                                    $max = max(1, (int) ($row['max_puan'] ?? $row['max'] ?? 1));
                                    $pct = isset($row['yuzde']) ? (float) $row['yuzde'] : round($puan / $max * 100, 1);
                                    $labels = [
                                        'bilgi_dogrulugu' => 'Bilgi doğruluğu',
                                        'dil_kalitesi' => 'Dil kalitesi',
                                        'tek_kesin_cevap' => 'Tek / kesin cevap',
                                        'celdirici_kalitesi' => 'Çeldirici kalitesi',
                                        'zorluk_dengesi' => 'Zorluk dengesi',
                                        'kullanici_ilgisi' => 'Kullanıcı ilgisi',
                                        'kategori_uygunlugu' => 'Kategori uygunluğu',
                                        'dil_tutarliligi' => 'TR-EN tutarlılık',
                                        'ozgunluk' => 'Özgünlük',
                                        'guncellik_format' => 'Güncellik / format',
                                    ];
                                @endphp
                                <tr>
                                    <td class="ps-3">{{ $labels[$key] ?? $key }}</td>
                                    <td><strong>{{ $puan }}</strong><span class="text-muted">/{{ $max }}</span></td>
                                    <td class="pe-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="aqr-bar flex-grow-1"><span style="width: {{ min(100, $pct) }}%"></span></div>
                                            <span class="small text-muted" style="min-width:2.5rem">{{ $pct }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-3">Kriter yok</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if ($revised)
    <div class="card aqr-panel">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>AI düzeltilmiş içerik</span>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#aqrApplyRevisionModal">
                Bunu uygula
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 aqr-diff-col mb-3 mb-md-0">
                    <h6>Türkçe</h6>
                    <p>{{ $revised['turkce']['soru'] ?? '—' }}</p>
                    <ol>
                        @foreach (($revised['turkce']['secenekler'] ?? []) as $idx => $opt)
                            <li @if((int)($revised['turkce']['dogru_cevap_indeksi'] ?? -1) === (int)$idx) class="fw-bold text-success" @endif>{{ $opt }}</li>
                        @endforeach
                    </ol>
                    <div class="small text-muted">doğru indeks: {{ $revised['turkce']['dogru_cevap_indeksi'] ?? '—' }} (0–3)</div>
                </div>
                <div class="col-md-6 aqr-diff-col">
                    <h6>İngilizce</h6>
                    <p>{{ $revised['ingilizce']['soru'] ?? '—' }}</p>
                    <ol>
                        @foreach (($revised['ingilizce']['secenekler'] ?? []) as $idx => $opt)
                            <li @if((int)($revised['ingilizce']['dogru_cevap_indeksi'] ?? -1) === (int)$idx) class="fw-bold text-success" @endif>{{ $opt }}</li>
                        @endforeach
                    </ol>
                    <div class="small text-muted">doğru indeks: {{ $revised['ingilizce']['dogru_cevap_indeksi'] ?? '—' }} (0–3)</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card aqr-panel">
        <div class="card-header">Ham JSON (tüm dönen veri)</div>
        <div class="card-body">
            <pre class="aqr-raw mb-0">{{ json_encode($raw ?: ['info' => 'raw_response boş'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
</div>

{{-- Pasife al: evet / hayır --}}
@if ($question && $isActive)
<div class="modal fade" id="aqrDeactivateModal" tabindex="-1" aria-labelledby="aqrDeactivateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content aqr-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="aqrDeactivateModalLabel">Soruyu pasife al</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong>Soru #{{ $review->question_id }}</strong> pasife alınsın mı?</p>
                <p class="mb-0 text-muted">Pasife alınırsa oyunda / eşleşmede çıkmaz.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hayır</button>
                <form method="post" action="{{ route('admin.question-quality-reviews.deactivate', $review->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning">Evet, pasife al</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif

{{-- AI düzeltmesi: içeriği göster, onayla --}}
@if ($revised)
<div class="modal fade" id="aqrApplyRevisionModal" tabindex="-1" aria-labelledby="aqrApplyRevisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content aqr-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="aqrApplyRevisionModalLabel">AI düzeltmesini uygula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Aşağıdaki içerik <strong>soru #{{ $review->question_id }}</strong> üzerine yazılacak. Mevcut metin ve şıklar değişir.</p>
                @if ($editReason)
                    <div class="alert alert-warning py-2 small mb-3">
                        <strong>Gerekçe:</strong> {{ $editReason }}
                    </div>
                @endif
                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <h6 class="fw-bold">Türkçe</h6>
                        <p>{{ $revised['turkce']['soru'] ?? '—' }}</p>
                        <ol class="mb-1">
                            @foreach (($revised['turkce']['secenekler'] ?? []) as $idx => $opt)
                                <li @if((int)($revised['turkce']['dogru_cevap_indeksi'] ?? -1) === (int)$idx) class="fw-bold text-success" @endif>{{ $opt }}</li>
                            @endforeach
                        </ol>
                        <div class="small text-muted">doğru indeks: {{ $revised['turkce']['dogru_cevap_indeksi'] ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">İngilizce</h6>
                        <p>{{ $revised['ingilizce']['soru'] ?? '—' }}</p>
                        <ol class="mb-1">
                            @foreach (($revised['ingilizce']['secenekler'] ?? []) as $idx => $opt)
                                <li @if((int)($revised['ingilizce']['dogru_cevap_indeksi'] ?? -1) === (int)$idx) class="fw-bold text-success" @endif>{{ $opt }}</li>
                            @endforeach
                        </ol>
                        <div class="small text-muted">doğru indeks: {{ $revised['ingilizce']['dogru_cevap_indeksi'] ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <form method="post" action="{{ route('admin.question-quality-reviews.apply-revision', $review->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">Evet, uygula</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
