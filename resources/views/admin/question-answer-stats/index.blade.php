@extends('admin.layouts.app')

@section('title', 'Kullanıcı Cevap İstatistikleri')

@section('content')
    <div class="page-title">
        <div class="row">
            <div class="col-6"><h3>Kullanıcı Cevap İstatistikleri</h3></div>
            <div class="col-6">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i data-feather="home"></i></a></li>
                    <li class="breadcrumb-item active">Cevap İstatistikleri</li>
                </ol>
            </div>
        </div>
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

    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h5 class="mb-1">Soru Bazlı Cevap Analizi</h5>
                        <small class="text-muted">
                            Zor / hatalı / anlaşılması güç soruları veriye göre tespit edin.
                            @if($lastCalculated)
                                Son hesaplama: {{ \Carbon\Carbon::parse($lastCalculated)->format('d.m.Y H:i') }}
                            @else
                                Henüz hesaplama yapılmadı.
                            @endif
                            · Min. cevap: {{ $minAnswers }}
                        </small>
                    </div>
                    @can('edit answer statistics')
                        <form method="POST" action="{{ route('admin.question-answer-stats.refresh') }}" onsubmit="return confirm('Tüm istatistikler yeniden hesaplansın mı?')">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i data-feather="refresh-cw"></i> Şimdi Yenile
                            </button>
                        </form>
                    @endcan
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.question-answer-stats.index') }}" class="row g-2 align-items-end mb-3">
                        <div class="col-12 col-md-6 col-lg-2">
                            <label class="form-label small text-muted">Ara</label>
                            <input type="text" name="search" class="form-control" placeholder="ID veya metin" value="{{ request('search') }}">
                        </div>
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label small text-muted">Kategori</label>
                            <select name="category_id" class="form-select">
                                <option value="">Tümü</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                                        {{ $category->getTranslation('name', 'tr') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6 col-md-3 col-lg-1">
                            <label class="form-label small text-muted">Zorluk</label>
                            <select name="level" class="form-select">
                                <option value="">Tümü</option>
                                <option value="easy" {{ request('level') === 'easy' ? 'selected' : '' }}>Kolay</option>
                                <option value="medium" {{ request('level') === 'medium' ? 'selected' : '' }}>Orta</option>
                                <option value="hard" {{ request('level') === 'hard' ? 'selected' : '' }}>Zor</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label small text-muted">Durum</label>
                            <select name="admin_status" class="form-select">
                                <option value="">Tümü</option>
                                <option value="active" {{ request('admin_status') === 'active' ? 'selected' : '' }}>Aktif</option>
                                <option value="passive" {{ request('admin_status') === 'passive' ? 'selected' : '' }}>Pasif</option>
                                <option value="maintenance" {{ request('admin_status') === 'maintenance' ? 'selected' : '' }}>Bakım</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-3 col-lg-2">
                            <label class="form-label small text-muted">Gözlenen zorluk</label>
                            <select name="observed_difficulty" class="form-select">
                                <option value="">Tümü</option>
                                <option value="easy" {{ request('observed_difficulty') === 'easy' ? 'selected' : '' }}>Kolay</option>
                                <option value="medium" {{ request('observed_difficulty') === 'medium' ? 'selected' : '' }}>Orta</option>
                                <option value="hard" {{ request('observed_difficulty') === 'hard' ? 'selected' : '' }}>Zor</option>
                                <option value="insufficient" {{ request('observed_difficulty') === 'insufficient' ? 'selected' : '' }}>Veri yetersiz</option>
                            </select>
                        </div>
                        <div class="col-4 col-md-2 col-lg-1">
                            <label class="form-label small text-muted">Başarı ≥</label>
                            <input type="number" step="0.1" min="0" max="100" name="success_min" class="form-control" value="{{ request('success_min') }}" placeholder="%">
                        </div>
                        <div class="col-4 col-md-2 col-lg-1">
                            <label class="form-label small text-muted">Başarı ≤</label>
                            <input type="number" step="0.1" min="0" max="100" name="success_max" class="form-control" value="{{ request('success_max') }}" placeholder="%">
                        </div>
                        <div class="col-4 col-md-2 col-lg-auto">
                            <button type="submit" class="btn btn-primary text-nowrap px-3">Filtrele</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Soru</th>
                                <th>Kategori</th>
                                <th>Tanımlı zorluk</th>
                                <th>Durum</th>
                                <th>Toplam</th>
                                <th>Doğru</th>
                                <th>Yanlış</th>
                                <th>Doğru %</th>
                                <th>A-B-C-D</th>
                                <th>Gözlenen</th>
                                <th style="min-width: 220px;">Yönetim</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($questions as $question)
                                @php
                                    $stat = $question->answerStat;
                                    $sufficient = $stat && $stat->data_sufficient;
                                    $total = $stat->total_answers ?? 0;
                                    $pct = $stat->correct_percentage ?? 0;
                                    $o1 = $stat->option_1_count ?? 0;
                                    $o2 = $stat->option_2_count ?? 0;
                                    $o3 = $stat->option_3_count ?? 0;
                                    $o4 = $stat->option_4_count ?? 0;
                                    $status = $question->admin_status ?? ($question->is_active ? 'active' : 'passive');
                                @endphp
                                <tr>
                                    <td>{{ $question->id }}</td>
                                    <td style="max-width: 260px;">
                                        {{ \Illuminate\Support\Str::limit($question->getTranslation('question', 'tr'), 80) }}
                                    </td>
                                    <td>{{ $question->category?->getTranslation('name', 'tr') ?? '-' }}</td>
                                    <td>
                                        @if($question->question_level === 'easy')
                                            <span class="badge bg-success">Kolay</span>
                                        @elseif($question->question_level === 'medium')
                                            <span class="badge bg-warning text-dark">Orta</span>
                                        @else
                                            <span class="badge bg-danger">Zor</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($status === 'active')
                                            <span class="badge bg-success">Aktif</span>
                                        @elseif($status === 'maintenance')
                                            <span class="badge bg-info text-dark">Bakım</span>
                                        @else
                                            <span class="badge bg-secondary">Pasif</span>
                                        @endif
                                    </td>
                                    <td>{{ $total }}</td>
                                    <td>{{ $stat->correct_count ?? 0 }}</td>
                                    <td>{{ $stat->wrong_count ?? 0 }}</td>
                                    <td>
                                        @if(!$sufficient)
                                            <span class="badge bg-light text-dark border">Veri yetersiz</span>
                                        @else
                                            <strong>{{ number_format($pct, 1) }}%</strong>
                                        @endif
                                    </td>
                                    <td style="min-width: 150px;">
                                        @if($total === 0)
                                            <span class="text-muted">-</span>
                                        @else
                                            <div class="small">
                                                <div>A: {{ $o1 }} ({{ $total ? round($o1 / $total * 100, 1) : 0 }}%)</div>
                                                <div>B: {{ $o2 }} ({{ $total ? round($o2 / $total * 100, 1) : 0 }}%)</div>
                                                <div>C: {{ $o3 }} ({{ $total ? round($o3 / $total * 100, 1) : 0 }}%)</div>
                                                <div>D: {{ $o4 }} ({{ $total ? round($o4 / $total * 100, 1) : 0 }}%)</div>
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$sufficient)
                                            <span class="badge bg-light text-dark border">Veri yetersiz</span>
                                        @elseif(($stat->observed_difficulty ?? '') === 'easy')
                                            <span class="badge bg-success">Kolay</span>
                                        @elseif(($stat->observed_difficulty ?? '') === 'medium')
                                            <span class="badge bg-warning text-dark">Orta</span>
                                        @else
                                            <span class="badge bg-danger">Zor</span>
                                        @endif
                                    </td>
                                    <td>
                                        @can('edit answer statistics')
                                            <form method="POST" action="{{ route('admin.question-answer-stats.update-level', $question) }}" class="d-flex gap-1 mb-1">
                                                @csrf
                                                @method('PATCH')
                                                <select name="question_level" class="form-select form-select-sm">
                                                    <option value="easy" {{ $question->question_level === 'easy' ? 'selected' : '' }}>Kolay</option>
                                                    <option value="medium" {{ $question->question_level === 'medium' ? 'selected' : '' }}>Orta</option>
                                                    <option value="hard" {{ $question->question_level === 'hard' ? 'selected' : '' }}>Zor</option>
                                                </select>
                                                <button class="btn btn-sm btn-outline-primary" type="submit" title="Zorluk kaydet">OK</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.question-answer-stats.update-status', $question) }}" class="d-flex gap-1 mb-1">
                                                @csrf
                                                @method('PATCH')
                                                <select name="admin_status" class="form-select form-select-sm">
                                                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                                                    <option value="passive" {{ $status === 'passive' ? 'selected' : '' }}>Pasif</option>
                                                    <option value="maintenance" {{ $status === 'maintenance' ? 'selected' : '' }}>Bakım</option>
                                                </select>
                                                <button class="btn btn-sm btn-outline-secondary" type="submit" title="Durum kaydet">OK</button>
                                            </form>
                                        @endcan
                                        <button type="button" class="btn btn-sm btn-link p-0 view-logs-btn" data-question-id="{{ $question->id }}">
                                            Loglar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">Kayıt bulunamadı.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $questions->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="logsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yönetici Değişiklik Logları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="logsLoading" class="text-muted">Yükleniyor...</div>
                    <div class="table-responsive d-none" id="logsTableWrap">
                        <table class="table table-sm">
                            <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Yönetici</th>
                                <th>Alan</th>
                                <th>Eski</th>
                                <th>Yeni</th>
                            </tr>
                            </thead>
                            <tbody id="logsTableBody"></tbody>
                        </table>
                    </div>
                    <div id="logsEmpty" class="text-muted d-none">Log bulunamadı.</div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.view-logs-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const questionId = this.dataset.questionId;
        const modal = new bootstrap.Modal(document.getElementById('logsModal'));
        const loading = document.getElementById('logsLoading');
        const wrap = document.getElementById('logsTableWrap');
        const empty = document.getElementById('logsEmpty');
        const body = document.getElementById('logsTableBody');

        loading.classList.remove('d-none');
        wrap.classList.add('d-none');
        empty.classList.add('d-none');
        body.innerHTML = '';
        modal.show();

        fetch(`{{ url('/admin/question-answer-stats') }}/${questionId}/logs`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(r => r.json())
            .then(data => {
                loading.classList.add('d-none');
                if (!data.logs || data.logs.length === 0) {
                    empty.classList.remove('d-none');
                    return;
                }
                data.logs.forEach(function (log) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${log.created_at || '-'}</td>
                        <td>${log.admin || '-'}</td>
                        <td>${log.field || '-'}</td>
                        <td>${log.old_value || '-'}</td>
                        <td>${log.new_value || '-'}</td>
                    `;
                    body.appendChild(tr);
                });
                wrap.classList.remove('d-none');
            })
            .catch(function () {
                loading.classList.add('d-none');
                empty.textContent = 'Loglar yüklenemedi.';
                empty.classList.remove('d-none');
            });
    });
});
</script>
@endpush
