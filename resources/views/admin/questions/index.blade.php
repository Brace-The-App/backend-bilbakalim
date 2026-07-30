@extends('admin.layouts.app')

@section('title', 'Sorular')

@section('content')
@php
    $hasFilters = request()->filled('search')
        || request()->filled('level')
        || request()->filled('status')
        || request()->filled('category_id')
        || request()->filled('check')
        || request()->filled('languages')
        || (request()->filled('per_page') && (int) request('per_page') !== 10);
    $summary = $summary ?? ['total'=>0,'active'=>0,'passive'=>0,'easy'=>0,'medium'=>0,'hard'=>0,'unchecked'=>0];
    $perPage = $perPage ?? 10;
@endphp

<div class="page-title questions-page-title">
    <div class="row align-items-center">
        <div class="col-12 col-md-6">
            <h3 class="mb-1">Sorular</h3>
            <p class="text-muted mb-0 small">Liste, filtre ve hızlı işlemler</p>
        </div>
        <div class="col-12 col-md-6 mt-2 mt-md-0 text-md-end">
            @can('create questions')
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#questionCreateModal">Yeni Soru</a>
            @endcan
        </div>
    </div>
</div>

{{-- 1) Özet şerit --}}
@php
    $kpiBase = ((int) $perPage !== 10) ? ['per_page' => (int) $perPage] : [];
@endphp
<div id="questionsKpi" class="questions-summary mb-3">
    <a href="{{ route('admin.questions.index', $kpiBase) }}" class="questions-summary__card {{ !request()->hasAny(['status','level','check']) ? 'is-active' : '' }}">
        <span class="questions-summary__label">Toplam</span>
        <strong class="questions-summary__value">{{ number_format($summary['total']) }}</strong>
    </a>
    <a href="{{ route('admin.questions.index', array_merge($kpiBase, ['status' => 1])) }}" class="questions-summary__card {{ request('status') === '1' ? 'is-active' : '' }}">
        <span class="questions-summary__label">Aktif</span>
        <strong class="questions-summary__value text-success">{{ number_format($summary['active']) }}</strong>
    </a>
    <a href="{{ route('admin.questions.index', array_merge($kpiBase, ['status' => 0])) }}" class="questions-summary__card {{ request('status') === '0' ? 'is-active' : '' }}">
        <span class="questions-summary__label">Pasif</span>
        <strong class="questions-summary__value text-danger">{{ number_format($summary['passive']) }}</strong>
    </a>
    <a href="{{ route('admin.questions.index', array_merge($kpiBase, ['level' => 'easy'])) }}" class="questions-summary__card {{ request('level') === 'easy' ? 'is-active' : '' }}">
        <span class="questions-summary__label">Kolay</span>
        <strong class="questions-summary__value text-success">{{ number_format($summary['easy']) }}</strong>
    </a>
    <a href="{{ route('admin.questions.index', array_merge($kpiBase, ['level' => 'medium'])) }}" class="questions-summary__card {{ request('level') === 'medium' ? 'is-active' : '' }}">
        <span class="questions-summary__label">Orta</span>
        <strong class="questions-summary__value text-warning">{{ number_format($summary['medium']) }}</strong>
    </a>
    <a href="{{ route('admin.questions.index', array_merge($kpiBase, ['level' => 'hard'])) }}" class="questions-summary__card {{ request('level') === 'hard' ? 'is-active' : '' }}">
        <span class="questions-summary__label">Zor</span>
        <strong class="questions-summary__value text-danger">{{ number_format($summary['hard']) }}</strong>
    </a>
    <a href="{{ route('admin.questions.index', array_merge($kpiBase, ['check' => 0])) }}" class="questions-summary__card {{ request('check') === '0' ? 'is-active' : '' }}">
        <span class="questions-summary__label">Kontrol edilmemiş</span>
        <strong class="questions-summary__value">{{ number_format($summary['unchecked']) }}</strong>
    </a>
</div>

<div class="row">
    <div class="col-sm-12">
        <div class="card questions-card">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.questions.index') }}" class="questions-filter mb-3" id="filterForm">
                    @if(request()->filled('check'))
                        <input type="hidden" name="check" value="{{ request('check') }}">
                    @endif
                    <div class="questions-filter__grid">
                        <div class="questions-filter__field questions-filter__field--search">
                            <label class="form-label small text-muted mb-1">Soru Ara</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i data-feather="search"></i></span>
                                <input type="text" name="search" class="form-control" placeholder="ID veya metin..." value="{{ request('search') }}">
                            </div>
                        </div>
                        <div class="questions-filter__field">
                            <label class="form-label small text-muted mb-1">Kategori</label>
                            <select name="category_id" class="form-select">
                                <option value="">Tümü</option>
                                @foreach(($categories ?? []) as $category)
                                    <option value="{{ $category->id }}" {{ (string) request('category_id') === (string) $category->id ? 'selected' : '' }}>
                                        {{ $category->getTranslation('name', 'tr') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="questions-filter__field">
                            <label class="form-label small text-muted mb-1">Zorluk</label>
                            <select name="level" class="form-select">
                                <option value="">Tümü</option>
                                <option value="easy" {{ request('level') == 'easy' ? 'selected' : '' }}>Kolay</option>
                                <option value="medium" {{ request('level') == 'medium' ? 'selected' : '' }}>Orta</option>
                                <option value="hard" {{ request('level') == 'hard' ? 'selected' : '' }}>Zor</option>
                            </select>
                        </div>
                        <div class="questions-filter__field">
                            <label class="form-label small text-muted mb-1">Durum</label>
                            <select name="status" class="form-select">
                                <option value="">Tümü</option>
                                <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Aktif</option>
                                <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Pasif</option>
                            </select>
                        </div>
                        <div class="questions-filter__field">
                            <label class="form-label small text-muted mb-1">Dil</label>
                            <div class="d-flex gap-3 align-items-center" style="min-height: 38px;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="languages[]" value="tr" id="lang-tr" {{ in_array('tr', (array) request('languages', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="lang-tr">TR</label>
                                </div>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="languages[]" value="en" id="lang-en" {{ in_array('en', (array) request('languages', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label text-success" for="lang-en">EN</label>
                                </div>
                            </div>
                        </div>
                        <div class="questions-filter__field">
                            <label class="form-label small text-muted mb-1">Sayfa</label>
                            <select name="per_page" class="form-select">
                                <option value="10" {{ (int) $perPage === 10 ? 'selected' : '' }}>10</option>
                                <option value="25" {{ (int) $perPage === 25 ? 'selected' : '' }}>25</option>
                                <option value="50" {{ (int) $perPage === 50 ? 'selected' : '' }}>50</option>
                            </select>
                        </div>
                        <div class="questions-filter__actions">
                            <button type="submit" class="btn btn-primary questions-filter__btn">
                                <i data-feather="filter"></i><span>Filtrele</span>
                            </button>
                            <button type="button" id="clearFiltersBtn" class="btn btn-outline-secondary questions-filter__btn {{ $hasFilters ? '' : 'disabled' }}">
                                <i data-feather="x"></i><span>Temizle</span>
                            </button>
                        </div>
                    </div>
                </form>

                <div id="questionsSummary" class="mb-3">
                    <span class="badge bg-primary me-2 mb-1">Filtreye göre: {{ $filteredTotalCount ?? 0 }}</span>
                    @foreach(($languageCounts ?? []) as $locale => $count)
                        <span class="badge bg-info text-dark me-2 mb-1">{{ strtoupper($locale) }}: {{ $count }}</span>
                    @endforeach
                    @if(!is_null($bilingualCount ?? null) && ($bilingualCount ?? 0) > 0)
                        <span class="badge bg-success me-2 mb-1">TR + EN: {{ $bilingualCount }}</span>
                    @endif
                    @if(!is_null($trOnlyCount ?? null) && ($trOnlyCount ?? 0) > 0)
                        <span class="badge bg-secondary me-2 mb-1">Sadece TR: {{ $trOnlyCount }}</span>
                    @endif
                    @if(!is_null($enOnlyCount ?? null) && ($enOnlyCount ?? 0) > 0)
                        <span class="badge bg-warning text-dark me-2 mb-1">Sadece EN: {{ $enOnlyCount }}</span>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table questions-table align-middle">
                        <thead>
                        <tr>
                            <th style="width: 72px;">ID</th>
                            <th>Soru</th>
                            <th style="width: 64px;">Görsel</th>
                            <th>Kategori / Seviye</th>
                            <th style="width: 70px;">Coin</th>
                            <th style="width: 100px;">Durum</th>
                            <th class="text-center" style="width: 110px;">Kontrol</th>
                            <th class="text-end">İşlemler</th>
                        </tr>
                        </thead>
                        <tbody id="questionsTableBody">
                        @foreach(($questions ?? []) as $question)
                            @php
                                $rawQuestion = $question->getRawOriginal('question');
                                $questionData = is_string($rawQuestion) ? json_decode($rawQuestion, true) : ($rawQuestion ?? []);
                                $hasTr = isset($questionData['tr']) && trim((string) $questionData['tr']) !== '';
                                $hasEn = isset($questionData['en']) && trim((string) ($questionData['en'] ?? '')) !== '';
                                $qText = $question->getTranslation('question', 'tr') ?: ($questionData['tr'] ?? '');
                                $isMismatch = $question->hasLevelMismatch();
                                $isSuspicious = $question->hasSuspiciousAnswers();

                                $rawOneChoice = $question->getRawOriginal('one_choice');
                                $oneChoiceData = is_string($rawOneChoice) ? json_decode($rawOneChoice, true) : ($rawOneChoice ?? []);
                                $hasA1En = isset($oneChoiceData['en']) && $oneChoiceData['en'] !== null && $oneChoiceData['en'] !== '';
                                $rawTwoChoice = $question->getRawOriginal('two_choice');
                                $twoChoiceData = is_string($rawTwoChoice) ? json_decode($rawTwoChoice, true) : ($rawTwoChoice ?? []);
                                $hasA2En = isset($twoChoiceData['en']) && $twoChoiceData['en'] !== null && $twoChoiceData['en'] !== '';
                                $rawThreeChoice = $question->getRawOriginal('three_choice');
                                $threeChoiceData = is_string($rawThreeChoice) ? json_decode($rawThreeChoice, true) : ($rawThreeChoice ?? []);
                                $hasA3En = isset($threeChoiceData['en']) && $threeChoiceData['en'] !== null && $threeChoiceData['en'] !== '';
                                $rawFourChoice = $question->getRawOriginal('four_choice');
                                $fourChoiceData = is_string($rawFourChoice) ? json_decode($rawFourChoice, true) : ($rawFourChoice ?? []);
                                $hasA4En = isset($fourChoiceData['en']) && $fourChoiceData['en'] !== null && $fourChoiceData['en'] !== '';
                            @endphp
                            <tr>
                                <td class="text-muted small">#{{ $question->id }}</td>
                                <td>
                                    <div class="questions-qtext" title="{{ $qText }}">{{ $qText }}</div>
                                    <div class="d-flex flex-wrap gap-1 mt-1 align-items-center">
                                        @if($hasTr)<span class="badge bg-secondary">TR</span>@endif
                                        @if($hasEn)<span class="badge bg-success">EN</span>@endif
                                        @if($isMismatch)<span class="badge bg-warning text-dark" title="Tanımlı seviye ile gözlenen zorluk uyuşmuyor">Uyumsuz</span>@endif
                                        @if($isSuspicious)<span class="badge bg-danger" title="Şüpheli şık dağılımı">Şüpheli</span>@endif
                                    </div>
                                </td>
                                <td>
                                    @if($question->image)
                                        <img src="{{ asset('storage/' . $question->image) }}" alt="" class="questions-thumb">
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold small">{{ $question->category?->getTranslation('name', 'tr') ?? '—' }}</div>
                                    @if($question->question_level === 'easy')
                                        <span class="badge bg-success">Kolay</span>
                                    @elseif($question->question_level === 'medium')
                                        <span class="badge bg-warning text-dark">Orta</span>
                                    @else
                                        <span class="badge bg-danger">Zor</span>
                                    @endif
                                </td>
                                <td>{{ $question->coin_value }}</td>
                                <td>
                                    @can('edit questions')
                                        <button type="button"
                                                class="btn btn-sm question-active-btn {{ $question->is_active ? 'btn-outline-success' : 'btn-outline-danger' }}"
                                                data-id="{{ $question->id }}"
                                                data-active="{{ $question->is_active ? '1' : '0' }}">
                                            {{ $question->is_active ? 'Aktif' : 'Pasif' }}
                                        </button>
                                    @else
                                        @if($question->is_active)
                                            <span class="badge bg-success">Aktif</span>
                                        @else
                                            <span class="badge bg-danger">Pasif</span>
                                        @endif
                                    @endcan
                                </td>
                                <td class="text-center">
                                    @can('edit questions')
                                        <button type="button"
                                                class="btn btn-sm question-check-btn {{ $question->check ? 'btn-success' : 'btn-outline-secondary' }}"
                                                data-id="{{ $question->id }}"
                                                data-check="{{ $question->check ? '1' : '0' }}"
                                                title="{{ $question->check ? 'Kontrol edildi' : 'Kontrol edilmedi — tıklayın' }}">
                                            <i data-feather="{{ $question->check ? 'check' : 'circle' }}" style="width:14px;height:14px;"></i>
                                            <span>{{ $question->check ? 'OK' : 'Kontrol' }}</span>
                                        </button>
                                    @else
                                        @if($question->check)
                                            <span class="badge bg-success">OK</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    @endcan
                                </td>
                                <td>
                                    <div class="questions-actions">
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#questionShowModal"
                                                data-id="{{ $question->id }}"
                                                data-question-tr="{{ $question->getTranslation('question', 'tr') }}"
                                                data-question-en="{{ $question->getTranslation('question', 'en') }}"
                                                data-a1-tr="{{ $question->getTranslation('one_choice', 'tr') }}"
                                                data-a1-en="{{ $question->getTranslation('one_choice', 'en') }}"
                                                data-a2-tr="{{ $question->getTranslation('two_choice', 'tr') }}"
                                                data-a2-en="{{ $question->getTranslation('two_choice', 'en') }}"
                                                data-a3-tr="{{ $question->getTranslation('three_choice', 'tr') }}"
                                                data-a3-en="{{ $question->getTranslation('three_choice', 'en') }}"
                                                data-a4-tr="{{ $question->getTranslation('four_choice', 'tr') }}"
                                                data-a4-en="{{ $question->getTranslation('four_choice', 'en') }}"
                                                data-right="{{ $question->correct_answer }}"
                                                data-level="{{ $question->question_level }}"
                                                data-coin="{{ $question->coin_value }}"
                                                data-active="{{ $question->is_active }}"
                                                data-category="{{ $question->category_id }}"
                                                data-image="{{ $question->image }}">Görüntüle</button>
                                        @can('edit questions')
                                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#questionEditModal"
                                                    data-id="{{ $question->id }}"
                                                    data-question-tr="{{ $question->getTranslation('question', 'tr') }}"
                                                    data-question-en="{{ $hasEn ? ($questionData['en'] ?? '') : '' }}"
                                                    data-a1-tr="{{ $question->getTranslation('one_choice', 'tr') }}"
                                                    data-a1-en="{{ $hasA1En ? ($oneChoiceData['en'] ?? '') : '' }}"
                                                    data-a2-tr="{{ $question->getTranslation('two_choice', 'tr') }}"
                                                    data-a2-en="{{ $hasA2En ? ($twoChoiceData['en'] ?? '') : '' }}"
                                                    data-a3-tr="{{ $question->getTranslation('three_choice', 'tr') }}"
                                                    data-a3-en="{{ $hasA3En ? ($threeChoiceData['en'] ?? '') : '' }}"
                                                    data-a4-tr="{{ $question->getTranslation('four_choice', 'tr') }}"
                                                    data-a4-en="{{ $hasA4En ? ($fourChoiceData['en'] ?? '') : '' }}"
                                                    data-right="{{ $question->correct_answer }}"
                                                    data-level="{{ $question->question_level }}"
                                                    data-coin="{{ $question->coin_value }}"
                                                    data-active="{{ $question->is_active }}"
                                                    data-category="{{ $question->category_id }}"
                                                    data-image="{{ $question->image }}">Düzenle</button>
                                        @endcan
                                        <a href="{{ route('admin.question-answer-stats.index', ['search' => $question->id]) }}"
                                           class="btn btn-sm btn-outline-primary" title="Cevap istatistikleri">İstatistik</a>
                                        @can('delete questions')
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteQuestion({{ $question->id }})">Sil</button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div id="questionsPagination" class="d-flex justify-content-center mt-3">
                    {{ $questions->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>
    </div>
</div>

    <!-- Create Question Modal -->
    <div class="modal fade" id="questionCreateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Yeni Soru</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" action="{{ route('admin.questions.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" id="createQuestionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="q-tr-tab" data-bs-toggle="tab" data-bs-target="#q-tr-pane" type="button" role="tab">
                                    🇹🇷 Türkçe
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="q-en-tab" data-bs-toggle="tab" data-bs-target="#q-en-pane" type="button" role="tab">
                                    🇬🇧 English
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mt-3" id="createQuestionTabContent">
                            <!-- Turkish Tab -->
                            <div class="tab-pane fade show active" id="q-tr-pane" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <!-- <label class="form-label">Soru  *</label> -->
                                        <textarea name="question[tr]" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Şık 1  *</label>
                                        <input type="text" name="one_choice[tr]" class="form-control" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Şık 2  *</label>
                                        <input type="text" name="two_choice[tr]" class="form-control" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Şık 3  *</label>
                                        <input type="text" name="three_choice[tr]" class="form-control" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Şık 4  *</label>
                                        <input type="text" name="four_choice[tr]" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <!-- English Tab -->
                            <div class="tab-pane fade" id="q-en-pane" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Soru (English)</label>
                                        <textarea name="question[en]" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 1 (English)</label>
                                        <input type="text" name="one_choice[en]" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 2 (English)</label>
                                        <input type="text" name="two_choice[en]" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 3 (English)</label>
                                        <input type="text" name="three_choice[en]" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 4 (English)</label>
                                        <input type="text" name="four_choice[en]" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other Fields -->
                        <div class="row g-3 mt-3">
                            <div class="col-12">
                                <label class="form-label">Soru Resmi (Opsiyonel)</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <small class="form-text text-muted">JPG, PNG, GIF formatları desteklenir. Maksimum 2MB.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Doğru Şık *</label>
                                <select name="correct_answer" class="form-select" required>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Seviye *</label>
                                <select name="question_level" class="form-select" required>
                                    <option value="easy">Kolay</option>
                                    <option value="medium">Orta</option>
                                    <option value="hard">Zor</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Coin Değeri</label>
                                <input type="number" name="coin_value" class="form-control" value="10">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kategori *</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Kategori Seçin</option>
                                    @foreach($categories ?? [] as $category)
                                        <option value="{{ $category->id }}">{{ $category->getTranslation('name', 'tr') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                    <label class="form-check-label" for="is_active">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Show Question Modal -->
    <div class="modal fade" id="questionShowModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Soru Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Türkçe Soru</label>
                                <p id="show-question-tr" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">İngilizce Soru</label>
                                <p id="show-question-en" class="form-control-plaintext"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Soru Resmi</label>
                                <div id="show-image-container">
                                    <img id="show-image" src="" alt="Soru Resmi" style="max-width: 100%; height: auto; border-radius: 8px; display: none;">
                                    <p id="show-no-image" class="text-muted">Resim yok</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">A) Türkçe</label>
                                <p id="show-a1-tr" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">A) İngilizce</label>
                                <p id="show-a1-en" class="form-control-plaintext"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">B) Türkçe</label>
                                <p id="show-a2-tr" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">B) İngilizce</label>
                                <p id="show-a2-en" class="form-control-plaintext"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">C) Türkçe</label>
                                <p id="show-a3-tr" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">C) İngilizce</label>
                                <p id="show-a3-en" class="form-control-plaintext"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">D) Türkçe</label>
                                <p id="show-a4-tr" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">D) İngilizce</label>
                                <p id="show-a4-en" class="form-control-plaintext"></p>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Doğru Cevap</label>
                                <p id="show-right" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Seviye</label>
                                <p id="show-level" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Coin</label>
                                <p id="show-coin" class="form-control-plaintext"></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Durum</label>
                                <p id="show-status" class="form-control-plaintext"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div class="modal fade" id="questionEditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Soruyu Düzenle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST" id="questionEditForm" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" id="editQuestionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-q-tr-tab" data-bs-toggle="tab" data-bs-target="#edit-q-tr-pane" type="button" role="tab">
                                    🇹🇷 Türkçe
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-q-en-tab" data-bs-toggle="tab" data-bs-target="#edit-q-en-pane" type="button" role="tab">
                                    🇬🇧 English
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mt-3" id="editQuestionTabContent">
                            <!-- Turkish Tab -->
                            <div class="tab-pane fade show active" id="edit-q-tr-pane" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Soru  *</label>
                                        <textarea name="question[tr]" id="edit-q" class="form-control" rows="3" required></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 1  *</label>
                                        <input type="text" name="one_choice[tr]" id="edit-a1" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 2  *</label>
                                        <input type="text" name="two_choice[tr]" id="edit-a2" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 3  *</label>
                                        <input type="text" name="three_choice[tr]" id="edit-a3" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 4  *</label>
                                        <input type="text" name="four_choice[tr]" id="edit-a4" class="form-control" required>
                                    </div>
                                </div>
                            </div>

                            <!-- English Tab -->
                            <div class="tab-pane fade" id="edit-q-en-pane" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Soru (English)</label>
                                        <textarea name="question[en]" id="edit-q-en" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 1 (English)</label>
                                        <input type="text" name="one_choice[en]" id="edit-a1-en" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 2 (English)</label>
                                        <input type="text" name="two_choice[en]" id="edit-a2-en" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 3 (English)</label>
                                        <input type="text" name="three_choice[en]" id="edit-a3-en" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Şık 4 (English)</label>
                                        <input type="text" name="four_choice[en]" id="edit-a4-en" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other Fields -->
                        <div class="row g-3 mt-3">
                            <div class="col-12">
                                <label class="form-label">Soru Resmi (Opsiyonel)</label>
                                <input type="file" name="image" class="form-control" accept="image/*">
                                <small class="form-text text-muted">JPG, PNG, GIF formatları desteklenir. Maksimum 2MB.</small>
                                <div id="current-image" class="mt-2" style="display: none;">
                                    <label class="form-label">Mevcut Resim:</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <img id="current-image-preview" src="" alt="Mevcut Resim" style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px;">
                                        <button type="button" class="btn btn-sm btn-danger" id="remove-image-btn" onclick="removeImage()">
                                            <i class="fa fa-trash"></i> Resmi Sil
                                        </button>
                                    </div>
                                    <input type="hidden" name="remove_image" id="remove-image" value="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Doğru Şık *</label>
                                <select name="correct_answer" id="edit-right" class="form-select" required>
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Seviye *</label>
                                <select name="question_level" id="edit-level" class="form-select" required>
                                    <option value="easy">Kolay</option>
                                    <option value="medium">Orta</option>
                                    <option value="hard">Zor</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Coin Değeri</label>
                                <input type="number" name="coin_value" id="edit-coin" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kategori *</label>
                                <select name="category_id" id="edit-category" class="form-select" required>
                                    <option value="">Kategori Seçin</option>
                                    @foreach($categories ?? [] as $category)
                                        <option value="{{ $category->id }}">{{ $category->getTranslation('name', 'tr') }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit-active">
                                    <label class="form-check-label" for="edit-active">Aktif</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .questions-page-title { margin-top: 1rem; }
        .questions-summary {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: .75rem;
        }
        @media (max-width: 1199.98px) {
            .questions-summary { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        @media (max-width: 767.98px) {
            .questions-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .questions-summary__card {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            padding: .85rem 1rem;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #e5e7eb;
            text-decoration: none;
            color: inherit;
            transition: box-shadow .15s ease, border-color .15s ease;
        }
        .questions-summary__card:hover,
        .questions-summary__card.is-active {
            border-color: #a5b4fc;
            box-shadow: 0 6px 16px rgba(15,23,42,.06);
        }
        .questions-summary__label { font-size: .75rem; color: #6b7280; }
        .questions-summary__value { font-size: 1.2rem; font-weight: 700; }

        .questions-filter__grid {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: .75rem;
            align-items: end;
        }
        .questions-filter__field { grid-column: span 2; }
        .questions-filter__field--search { grid-column: span 3; }
        .questions-filter__actions {
            grid-column: span 3;
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .questions-filter__btn {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            flex: 1 1 auto;
            justify-content: center;
            min-height: 38px;
        }
        .questions-filter__btn svg { width: 16px; height: 16px; }
        .questions-filter__btn.disabled { pointer-events: none; opacity: .55; }

        @media (max-width: 1199.98px) {
            .questions-filter__field { grid-column: span 4; }
            .questions-filter__field--search { grid-column: span 8; }
            .questions-filter__actions { grid-column: span 4; }
        }
        @media (max-width: 767.98px) {
            .questions-filter__field,
            .questions-filter__field--search,
            .questions-filter__actions { grid-column: span 12; }
            .questions-filter__actions { flex-direction: column; }
            .questions-filter__btn { width: 100%; }
        }

        .questions-card { border: 0; box-shadow: 0 1px 3px rgba(15,23,42,.06); border-radius: 14px; }
        .questions-table thead th { white-space: nowrap; font-size: .82rem; color: #6b7280; }
        .questions-qtext {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.35;
            max-width: 420px;
        }
        .questions-thumb {
            width: 44px; height: 44px; object-fit: cover;
            border-radius: 8px; border: 1px solid #e5e7eb;
        }
        .questions-actions {
            display: flex; flex-wrap: wrap; gap: .35rem; justify-content: flex-end;
        }
        .question-check-btn {
            display: inline-flex; align-items: center; gap: .25rem;
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            // URL'den sayfa numarasını al
            function getCurrentPageFromUrl() {
                var urlParams = new URLSearchParams(window.location.search);
                var page = urlParams.get('page');
                return page ? parseInt(page) : null;
            }

            // Mevcut sayfa numarasını sakla
            var currentPage = getCurrentPageFromUrl() || 1;

            // Toastr configuration
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

            // Create Question Form
            $('#questionCreateModal form').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var formData = new FormData(this);
                var url = form.attr('action');

                // Clear previous errors
                form.find('.alert-danger').remove();

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Close modal
                        $('#questionCreateModal').modal('hide');
                        // Show success message
                        toastr.success('Soru başarıyla oluşturuldu!');
                        // Reload data without page refresh - mevcut sayfada kal
                        loadQuestions(currentPage);
                        // Reset form
                        $('#questionCreateModal form')[0].reset();
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            if (errors) {
                                var errorMessages = [];
                                $.each(errors, function(key, value) {
                                    errorMessages.push(value[0]);
                                });
                                toastr.error(errorMessages.join('<br>'));
                            }
                        } else {
                            toastr.error(xhr.responseJSON.message);
                        }
                    }
                });
            });

            // Edit Question Form
            $('#questionEditModal form').on('submit', function(e) {
                e.preventDefault();

                var form = $(this);
                var formData = new FormData(this);
                var url = form.attr('action');

                // Clear previous errors
                form.find('.alert-danger').remove();

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Close modal
                        $('#questionEditModal').modal('hide');
                        // Show success message
                        toastr.success('Soru başarıyla güncellendi!');
                        // Reload data without page refresh - mevcut sayfada kal
                        loadQuestions(currentPage);
                    },
                    error: function(xhr) {
                        if (xhr.status === 422) {
                            var errors = xhr.responseJSON.errors;
                            if (errors) {
                                var errorMessages = [];
                                $.each(errors, function(key, value) {
                                    errorMessages.push(value[0]);
                                });
                                toastr.error(errorMessages.join('<br>'));
                            }
                        } else {
                            toastr.error('Bir hata oluştu!');
                        }
                    }
                });
            });


            // Load Questions Function
            function loadQuestions(page = null) {
                // Sayfa belirtilmemişse mevcut sayfayı kullan
                if (page === null) {
                    page = currentPage;
                } else {
                    currentPage = page; // Sayfa numarasını güncelle
                }

                // Form verilerini al
                var formData = $('#filterForm').serialize();
                if (page > 1) {
                    formData += '&page=' + page;
                }

                // Form action'dan URL'yi al
                var formUrl = $('#filterForm').attr('action') || '/admin/questions';

                $.ajax({
                    url: formUrl,
                    type: 'GET',
                    data: formData,
                    success: function(response) {
                        // Extract table body and pagination from response
                        var kpi = $(response).find('#questionsKpi').html();
                        var summary = $(response).find('#questionsSummary').html();
                        var tableBody = $(response).find('#questionsTableBody').html();
                        var pagination = $(response).find('#questionsPagination').html();

                        if (kpi) $('#questionsKpi').html(kpi);
                        $('#questionsSummary').html(summary);
                        $('#questionsTableBody').html(tableBody);
                        $('#questionsPagination').html(pagination);

                        // URL'yi güncelle (sayfa numarasını ekle)
                        var newUrl = formUrl;
                        if (formData) {
                            newUrl += '?' + formData;
                        } else if (page > 1) {
                            newUrl += '?page=' + page;
                        }
                        if (window.history && window.history.pushState) {
                            window.history.pushState({}, '', newUrl);
                        }

                        // Re-bind edit modal events
                        bindQuestionEditModalEvents();
                        // Re-bind show modal events
                        bindQuestionShowModalEvents();
                        if (typeof feather !== 'undefined') feather.replace();
                    },
                    error: function() {
                        toastr.error('Veriler yüklenirken bir hata oluştu!');
                    }
                });
            }

            // Form submit handler - sayfa yenileme yerine AJAX kullan
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                // URL'yi güncelle (query string ile)
                var formUrl = $('#filterForm').attr('action') || window.location.pathname;
                var formData = $('#filterForm').serialize();
                var newUrl = formUrl + (formData ? '?' + formData : '');
                if (window.history && window.history.pushState) {
                    window.history.pushState({}, '', newUrl);
                }
                loadQuestions(1);
            });

            // Adım 1: 10/25/50 değişince otomatik uygula
            $('#filterForm select[name="per_page"]').on('change', function() {
                loadQuestions(1);
            });

            // Temizle butonu - formu temizle ve AJAX ile yükle
            $('#clearFiltersBtn').on('click', function(e) {
                e.preventDefault();
                // Form alanlarını temizle
                $('#filterForm')[0].reset();
                // Checkbox'ları temizle
                $('#lang-tr').prop('checked', false);
                $('#lang-en').prop('checked', false);
                // URL'yi temizle (query string'i kaldır)
                var formUrl = $('#filterForm').attr('action') || window.location.pathname;
                if (window.history && window.history.pushState) {
                    window.history.pushState({}, '', formUrl);
                }
                // AJAX ile temizlenmiş halini yükle
                loadQuestions(1);
            });

            // HTML entity decode helper function
            function decodeHtmlEntities(text) {
                if (!text) return '';
                var textarea = document.createElement('textarea');
                textarea.innerHTML = text;
                return textarea.value;
            }

            // Bind Question Edit Modal Events
            function bindQuestionEditModalEvents() {
                $('#questionEditModal').off('show.bs.modal').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget);
                    var id = button.attr('data-id');
                    var questionTr = decodeHtmlEntities(button.attr('data-question-tr') || '');
                    var questionEn = decodeHtmlEntities(button.attr('data-question-en') || '');
                    var a1Tr = decodeHtmlEntities(button.attr('data-a1-tr') || '');
                    var a1En = decodeHtmlEntities(button.attr('data-a1-en') || '');
                    var a2Tr = decodeHtmlEntities(button.attr('data-a2-tr') || '');
                    var a2En = decodeHtmlEntities(button.attr('data-a2-en') || '');
                    var a3Tr = decodeHtmlEntities(button.attr('data-a3-tr') || '');
                    var a3En = decodeHtmlEntities(button.attr('data-a3-en') || '');
                    var a4Tr = decodeHtmlEntities(button.attr('data-a4-tr') || '');
                    var a4En = decodeHtmlEntities(button.attr('data-a4-en') || '');
                    var right = button.attr('data-right');
                    var level = button.attr('data-level');
                    var coin = button.attr('data-coin');
                    var active = button.attr('data-active');
                    var category = button.attr('data-category');
                    var image = button.attr('data-image');

                    $('#edit-q').val(questionTr);
                    $('#edit-q-en').val(questionEn);
                    $('#edit-a1').val(a1Tr);
                    $('#edit-a1-en').val(a1En);
                    $('#edit-a2').val(a2Tr);
                    $('#edit-a2-en').val(a2En);
                    $('#edit-a3').val(a3Tr);
                    $('#edit-a3-en').val(a3En);
                    $('#edit-a4').val(a4Tr);
                    $('#edit-a4-en').val(a4En);
                    $('#edit-right').val(right);
                    $('#edit-level').val(level);
                    $('#edit-coin').val(coin);
                    $('#edit-active').prop('checked', active == 1);
                    $('#edit-category').val(category);
                    $('#questionEditForm').attr('action', '/admin/questions/' + id);

                    // Image handling
                    if (image) {
                        $('#current-image-preview').attr('src', '/storage/' + image);
                        $('#current-image').show();
                        $('#remove-image').val('0'); // Reset remove flag
                    } else {
                        $('#current-image').hide();
                    }
                });
            }

            // Bind Question Show Modal Events
            function bindQuestionShowModalEvents() {
                $('#questionShowModal').off('show.bs.modal').on('show.bs.modal', function (event) {
                    var button = $(event.relatedTarget);
                    var id = button.attr('data-id');
                    var questionTr = decodeHtmlEntities(button.attr('data-question-tr') || '');
                    var questionEn = decodeHtmlEntities(button.attr('data-question-en') || '');
                    var a1Tr = decodeHtmlEntities(button.attr('data-a1-tr') || '');
                    var a1En = decodeHtmlEntities(button.attr('data-a1-en') || '');
                    var a2Tr = decodeHtmlEntities(button.attr('data-a2-tr') || '');
                    var a2En = decodeHtmlEntities(button.attr('data-a2-en') || '');
                    var a3Tr = decodeHtmlEntities(button.attr('data-a3-tr') || '');
                    var a3En = decodeHtmlEntities(button.attr('data-a3-en') || '');
                    var a4Tr = decodeHtmlEntities(button.attr('data-a4-tr') || '');
                    var a4En = decodeHtmlEntities(button.attr('data-a4-en') || '');
                    var right = button.attr('data-right');
                    var level = button.attr('data-level');
                    var coin = button.attr('data-coin');
                    var active = button.attr('data-active');
                    var category = button.attr('data-category');
                    var image = button.attr('data-image');

                    $('#show-question-tr').text(questionTr || '-');
                    $('#show-question-en').text(questionEn || '-');
                    $('#show-a1-tr').text(a1Tr || '-');
                    $('#show-a1-en').text(a1En || '-');
                    $('#show-a2-tr').text(a2Tr || '-');
                    $('#show-a2-en').text(a2En || '-');
                    $('#show-a3-tr').text(a3Tr || '-');
                    $('#show-a3-en').text(a3En || '-');
                    $('#show-a4-tr').text(a4Tr || '-');
                    $('#show-a4-en').text(a4En || '-');
                    $('#show-right').text(right || '-');
                    $('#show-level').html(level === 'easy' ? '<span class="badge bg-success">Kolay</span>' :
                        level === 'medium' ? '<span class="badge bg-warning">Orta</span>' :
                            level === 'hard' ? '<span class="badge bg-danger">Zor</span>' : level);
                    $('#show-coin').text(coin || '0');
                    $('#show-status').html(active == 1 ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>');

                    // Image handling
                    if (image) {
                        $('#show-image').attr('src', '/storage/' + image).show();
                        $('#show-no-image').hide();
                    } else {
                        $('#show-image').hide();
                        $('#show-no-image').show();
                    }
                });
            }

            // Soru "Kontrol edildi" tikleme (AJAX)
            var questionToggleCheckUrl = "{{ route('admin.questions.toggle-check', ['question' => ':id']) }}";
            $(document).on('click', '.question-check-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                if (btn.prop('disabled')) return;
                var id = btn.data('id');
                var url = questionToggleCheckUrl.replace(':id', id);
                btn.prop('disabled', true);
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        if (response.success) {
                            var isChecked = response.check === 1;
                            btn.data('check', isChecked ? '1' : '0');
                            btn.attr('title', isChecked ? 'Kontrol edildi' : 'Kontrol edilmedi — tıklayın');
                            btn.toggleClass('btn-success', isChecked)
                               .toggleClass('btn-outline-secondary', !isChecked);
                            btn.html(
                                '<i data-feather="' + (isChecked ? 'check' : 'circle') + '" style="width:14px;height:14px;"></i>' +
                                '<span>' + (isChecked ? 'OK' : 'Kontrol') + '</span>'
                            );
                            if (typeof feather !== 'undefined') feather.replace();
                            toastr.success(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'İşlem yapılamadı.');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });

            // Aktif / Pasif toggle
            var questionToggleActiveUrl = "{{ route('admin.questions.toggle-active', ['question' => ':id']) }}";
            $(document).on('click', '.question-active-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                if (btn.prop('disabled')) return;
                var id = btn.data('id');
                var url = questionToggleActiveUrl.replace(':id', id);
                btn.prop('disabled', true);
                $.ajax({
                    url: url,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        if (response.success) {
                            var isActive = !!response.is_active;
                            btn.data('active', isActive ? '1' : '0');
                            btn.toggleClass('btn-outline-success', isActive)
                               .toggleClass('btn-outline-danger', !isActive)
                               .text(isActive ? 'Aktif' : 'Pasif');
                            toastr.success(response.message);
                        }
                    },
                    error: function(xhr) {
                        toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'İşlem yapılamadı.');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                    }
                });
            });

            // Pagination Click Handler (Adım 2: URLSearchParams)
            $(document).on('click', '.pagination a', function(e) {
                e.preventDefault();
                var href = $(this).attr('href');
                if (!href || href === '#') return;
                var page = 1;
                try {
                    var u = new URL(href, window.location.origin);
                    page = parseInt(u.searchParams.get('page') || '1', 10) || 1;
                    if (u.searchParams.has('per_page')) {
                        $('#filterForm select[name="per_page"]').val(u.searchParams.get('per_page'));
                    }
                } catch (err) {
                    if (href.indexOf('page=') !== -1) {
                        page = parseInt(href.split('page=')[1].split('&')[0], 10) || 1;
                    }
                }
                currentPage = page;
                loadQuestions(currentPage);
            });

            // Remove Image Function
            window.removeImage = function() {
                if (confirm('Bu resmi silmek istediğinizden emin misiniz?')) {
                    $('#current-image').hide();
                    $('#remove-image').val('1');
                    $('#current-image-preview').attr('src', '');
                }
            };

            // Delete Question
            window.deleteQuestion = function(id) {
                if (confirm('Bu soruyu silmek istediğinizden emin misiniz?')) {
                    $.ajax({
                        url: '/admin/questions/' + id,
                        type: 'POST',
                        data: {
                            _method: 'DELETE'
                        },
                        success: function(response) {
                            if (response.success) {
                                toastr.success(response.message);
                                // Reload data without page refresh - mevcut sayfada kal
                                loadQuestions(currentPage);
                            } else {
                                toastr.error(response.message);
                            }
                        },
                        error: function(xhr) {
                            if (xhr.status === 422) {
                                var response = xhr.responseJSON;
                                if (response.message) {
                                    toastr.error(response.message);
                                } else if (response.errors) {
                                    var errorMessages = [];
                                    $.each(response.errors, function(key, value) {
                                        errorMessages.push(value[0]);
                                    });
                                    toastr.error(errorMessages.join('<br>'));
                                }
                            } else {
                                toastr.error('Soru silinirken bir hata oluştu!');
                            }
                        }
                    });
                }
            };

            // Initialize question edit modal events on page load
            bindQuestionEditModalEvents();
            // Initialize question show modal events on page load
            bindQuestionShowModalEvents();

            // Stats / edit URL: ?edit=ID ile geldiyse düzenleme modalını aç
            (function openEditFromQuery() {
                var params = new URLSearchParams(window.location.search);
                var editId = params.get('edit');
                if (!editId) return;

                var tryOpen = function () {
                    var btn = document.querySelector('#questionEditModal')
                        ? document.querySelector('[data-bs-target="#questionEditModal"][data-id="' + editId + '"]')
                        : null;
                    if (!btn) return false;
                    btn.click();
                    return true;
                };

                if (!tryOpen()) {
                    // AJAX tablo yükleniyorsa kısa gecikmeyle tekrar dene
                    setTimeout(function () {
                        if (!tryOpen()) {
                            toastr.warning('Soru #' + editId + ' listede bulunamadı. Arama ile deneyin.');
                        }
                    }, 800);
                }
            })();

            // Anlık soru güncellemeleri (connect'te otomatik reload yok — Adım 2)
            if (typeof window.socketClient !== 'undefined' && window.socketClient.socket) {
                window.socketClient.socket.on('question_created', function(data) {
                    console.log('Yeni soru oluşturuldu:', data);
                    loadQuestions(currentPage);
                    toastr.success('Yeni soru eklendi!', 'BilBakalim');
                });

                window.socketClient.socket.on('question_updated', function(data) {
                    console.log('Soru güncellendi:', data);
                    loadQuestions(currentPage);
                    toastr.info('Soru güncellendi!', 'BilBakalim');
                });

                window.socketClient.socket.on('question_deleted', function(data) {
                    console.log('Soru silindi:', data);
                    loadQuestions(currentPage);
                    toastr.warning('Soru silindi!', 'BilBakalim');
                });

                window.socketClient.socket.on('category_updated', function(data) {
                    console.log('Kategori güncellendi:', data);
                    loadQuestions(currentPage);
                    toastr.info('Kategori güncellendi!', 'BilBakalim');
                });
            }


        });
    </script>
@endpush


@include('admin.layouts.footer')
