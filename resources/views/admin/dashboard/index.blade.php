@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $adminName = trim(auth()->user()->name . ' ' . (auth()->user()->surname ?? ''));
    $defaultAvatar = asset('assets/images/dashboard/profile.jpg');
@endphp

<div class="dash-welcome mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
        <div>
            <h3 class="mb-1">Merhaba, {{ $adminName ?: 'Admin' }}</h3>
            <p class="text-muted mb-0">Bugünün özeti · {{ now()->translatedFormat('d F Y, l') }}</p>
        </div>
        <div class="text-muted small">
            Bugün yeni kayıt: <strong>{{ number_format($stats['today_users']) }}</strong>
        </div>
    </div>
</div>

{{-- KPI cards: eşit boyut + filtreli sayfa linkleri --}}
<div class="dash-kpi-grid mb-4">
    <a href="{{ route('admin.users.index') }}" class="dash-kpi-link">
        <div class="card dash-kpi dash-kpi--purple">
            <div class="card-body">
                <div class="dash-kpi__icon"><i data-feather="users"></i></div>
                <div>
                    <span class="dash-kpi__label">Toplam Kullanıcı</span>
                    <h4 class="dash-kpi__value counter mb-0">{{ $stats['total_users'] }}</h4>
                </div>
            </div>
        </div>
    </a>
    <a href="{{ route('admin.users.index', ['online' => 1]) }}" class="dash-kpi-link">
        <div class="card dash-kpi dash-kpi--green">
            <div class="card-body">
                <div class="dash-kpi__icon"><i data-feather="user-check"></i></div>
                <div>
                    <span class="dash-kpi__label">Aktif Kullanıcı</span>
                    <h4 class="dash-kpi__value counter mb-0">{{ $stats['active_users'] }}</h4>
                </div>
            </div>
        </div>
    </a>
    <a href="{{ route('admin.questions.index') }}" class="dash-kpi-link">
        <div class="card dash-kpi dash-kpi--amber">
            <div class="card-body">
                <div class="dash-kpi__icon"><i data-feather="help-circle"></i></div>
                <div>
                    <span class="dash-kpi__label">Toplam Soru</span>
                    <h4 class="dash-kpi__value counter mb-0">{{ $stats['total_questions'] }}</h4>
                </div>
            </div>
        </div>
    </a>
    <a href="{{ route('admin.categories.index') }}" class="dash-kpi-link">
        <div class="card dash-kpi dash-kpi--blue">
            <div class="card-body">
                <div class="dash-kpi__icon"><i data-feather="folder"></i></div>
                <div>
                    <span class="dash-kpi__label">Kategoriler</span>
                    <h4 class="dash-kpi__value counter mb-0">{{ $stats['total_categories'] }}</h4>
                </div>
            </div>
        </div>
    </a>
    <a href="{{ route('admin.reward-requests.index') }}" class="dash-kpi-link">
        <div class="card dash-kpi dash-kpi--rose">
            <div class="card-body">
                <div class="dash-kpi__icon"><i data-feather="gift"></i></div>
                <div>
                    <span class="dash-kpi__label">Bekleyen Ödül</span>
                    <h4 class="dash-kpi__value counter mb-0">{{ $stats['pending_rewards'] }}</h4>
                </div>
            </div>
        </div>
    </a>
    <div class="dash-kpi-link dash-kpi-link--static">
        <div class="card dash-kpi dash-kpi--teal">
            <div class="card-body">
                <div class="dash-kpi__icon"><i data-feather="zap"></i></div>
                <div>
                    <span class="dash-kpi__label">Meydan Okuma</span>
                    <h4 class="dash-kpi__value counter mb-0">{{ $stats['finished_duels'] }}</h4>
                </div>
            </div>
        </div>
    </div>
    <a href="{{ route('admin.question-answer-stats.index') }}" class="dash-kpi-link">
        <div class="card dash-kpi dash-kpi--indigo">
            <div class="card-body">
                <div class="dash-kpi__icon"><i data-feather="check-circle"></i></div>
                <div>
                    <span class="dash-kpi__label">Doğru Cevap</span>
                    <h4 class="dash-kpi__value counter mb-0">{{ $stats['correct_answers'] }}</h4>
                </div>
            </div>
        </div>
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Son 7 Gün</h5>
                <small class="text-muted">Kayıt &amp; meydan okuma</small>
            </div>
            <div class="card-body">
                <canvas id="dashWeekChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h5 class="mb-0">Hızlı İşlemler</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="{{ route('admin.reward-requests.index') }}" class="btn dash-quick-btn w-100">
                            <i data-feather="gift"></i>
                            <span>Ödül Talepleri</span>
                            @if($stats['pending_rewards'] > 0)
                                <span class="badge bg-danger">{{ $stats['pending_rewards'] }}</span>
                            @endif
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.question-answer-stats.index') }}" class="btn dash-quick-btn w-100">
                            <i data-feather="bar-chart-2"></i>
                            <span>Cevap İstatistikleri</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.ads.index') }}" class="btn dash-quick-btn w-100">
                            <i data-feather="image"></i>
                            <span>Reklamlar</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.questions.index') }}" class="btn dash-quick-btn w-100">
                            <i data-feather="book"></i>
                            <span>Sorular</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.users.index') }}" class="btn dash-quick-btn w-100">
                            <i data-feather="users"></i>
                            <span>Kullanıcılar</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="{{ route('admin.gift-card-stores.index') }}" class="btn dash-quick-btn w-100">
                            <i data-feather="shopping-bag"></i>
                            <span>Hediye Kartları</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Son Kayıt Olan Kullanıcılar</h5>
                <a href="{{ route('admin.users.index') }}" class="small">Tümü</a>
            </div>
            <div class="card-body">
                <div class="table-responsive theme-scrollbar">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>İletişim</th>
                                <th>Jeton</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent_users as $user)
                            @php
                                $avatarUrl = $user->avatarModel->image_url
                                    ?? (!empty($user->profile_image)
                                        ? (filter_var($user->profile_image, FILTER_VALIDATE_URL)
                                            ? $user->profile_image
                                            : asset('storage/' . ltrim($user->profile_image, '/')))
                                        : $defaultAvatar);
                            @endphp
                            <tr class="dash-row-link" onclick="window.location='{{ route('admin.users.show', $user) }}'" style="cursor:pointer;">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img class="img-40 rounded-circle" src="{{ $avatarUrl }}" alt=""
                                             style="width:40px;height:40px;object-fit:cover;"
                                             onerror="this.src='{{ $defaultAvatar }}'">
                                        <div>
                                            <h6 class="mb-0">{{ trim($user->name . ' ' . ($user->surname ?? '')) }}</h6>
                                            <small class="text-muted">{{ $user->getRoleNames()->first() ?? 'Kullanıcı' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="small">{{ $user->email ?: '—' }}</div>
                                    <div class="small text-muted">{{ $user->phone ?: '—' }}</div>
                                </td>
                                <td><strong>{{ number_format((int) $user->coins) }}</strong></td>
                                <td class="small text-muted">{{ $user->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Son Eklenen Sorular</h5>
                <a href="{{ route('admin.questions.index') }}" class="small">Tümü</a>
            </div>
            <div class="card-body">
                <div class="table-responsive theme-scrollbar">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Soru</th>
                                <th>Kategori</th>
                                <th>Seviye</th>
                                <th>Tarih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recent_questions as $question)
                            <tr class="dash-row-link" onclick="window.location='{{ route('admin.questions.index', ['search' => $question->id]) }}'" style="cursor:pointer;">
                                <td>{{ \Illuminate\Support\Str::limit($question->question, 42) }}</td>
                                <td>
                                    @if($question->category)
                                        <span class="badge bg-info">{{ is_array($question->category->name) ? ($question->category->name['tr'] ?? reset($question->category->name)) : $question->category->name }}</span>
                                    @else
                                        <span class="badge bg-secondary">Yok</span>
                                    @endif
                                </td>
                                <td>
                                    @switch($question->question_level)
                                        @case('easy')
                                            <span class="badge bg-success">Kolay</span>
                                            @break
                                        @case('medium')
                                            <span class="badge bg-warning">Orta</span>
                                            @break
                                        @case('hard')
                                            <span class="badge bg-danger">Zor</span>
                                            @break
                                        @default
                                            <span class="badge bg-secondary">{{ $question->question_level }}</span>
                                    @endswitch
                                </td>
                                <td class="small text-muted">{{ $question->created_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.dash-welcome {
    margin-top: 1.25rem;
    padding-top: 0.5rem;
}
.dash-welcome h3 { font-weight: 700; color: #1f2937; }
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}
@media (max-width: 1199.98px) {
    .dash-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 575.98px) {
    .dash-kpi-grid { grid-template-columns: 1fr; }
}
.dash-kpi-link { text-decoration: none; color: inherit; display: block; height: 100%; }
.dash-kpi-link--static { cursor: default; }
.dash-kpi-link:hover .dash-kpi { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15, 23, 42, .08); }
.dash-kpi-link--static:hover .dash-kpi { transform: none; box-shadow: 0 1px 3px rgba(15, 23, 42, .06); }
.dash-kpi {
    border: 0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
    transition: transform .15s ease, box-shadow .15s ease;
    background: #fff;
    border-left: 4px solid #6366f1;
    height: 100%;
    min-height: 100px;
}
.dash-kpi .card-body {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 1.1rem 1.15rem;
    height: 100%;
    min-height: 100px;
}
.dash-kpi__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #eef2ff;
    color: #4f46e5;
}
.dash-kpi__icon svg { width: 20px; height: 20px; }
.dash-kpi__label { display: block; font-size: .8rem; color: #6b7280; margin-bottom: 2px; }
.dash-kpi__value { font-weight: 700; color: #111827; font-size: 1.35rem; }

.dash-kpi--purple { border-left-color: #7c3aed; }
.dash-kpi--purple .dash-kpi__icon { background: #f3e8ff; color: #7c3aed; }
.dash-kpi--green { border-left-color: #16a34a; }
.dash-kpi--green .dash-kpi__icon { background: #dcfce7; color: #16a34a; }
.dash-kpi--amber { border-left-color: #d97706; }
.dash-kpi--amber .dash-kpi__icon { background: #fef3c7; color: #d97706; }
.dash-kpi--blue { border-left-color: #2563eb; }
.dash-kpi--blue .dash-kpi__icon { background: #dbeafe; color: #2563eb; }
.dash-kpi--rose { border-left-color: #e11d48; }
.dash-kpi--rose .dash-kpi__icon { background: #ffe4e6; color: #e11d48; }
.dash-kpi--teal { border-left-color: #0d9488; }
.dash-kpi--teal .dash-kpi__icon { background: #ccfbf1; color: #0d9488; }
.dash-kpi--indigo { border-left-color: #4f46e5; }
.dash-kpi--indigo .dash-kpi__icon { background: #e0e7ff; color: #4f46e5; }
.dash-kpi--slate { border-left-color: #475569; }
.dash-kpi--slate .dash-kpi__icon { background: #e2e8f0; color: #475569; }

.dash-quick-btn {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    padding: 14px;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    background: #fafafa;
    color: #1f2937;
    text-align: left;
    min-height: 88px;
    position: relative;
}
.dash-quick-btn:hover { background: #fff; border-color: #c7d2fe; color: #312e81; }
.dash-quick-btn svg { width: 18px; height: 18px; }
.dash-quick-btn .badge { position: absolute; top: 10px; right: 10px; }
.dash-row-link:hover { background: #f8fafc; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
$(document).ready(function () {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    $('.counter').each(function () {
        var $this = $(this);
        var countTo = parseInt(String($this.text()).replace(/[^\d]/g, ''), 10) || 0;
        $({ countNum: 0 }).animate({ countNum: countTo }, {
            duration: 900,
            easing: 'swing',
            step: function () { $this.text(Math.floor(this.countNum).toLocaleString('tr-TR')); },
            complete: function () { $this.text(countTo.toLocaleString('tr-TR')); }
        });
    });

    var chartData = @json($chartData);
    var ctx = document.getElementById('dashWeekChart');
    if (ctx && window.Chart) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Yeni kayıt',
                        data: chartData.registrations,
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, .12)',
                        tension: .35,
                        fill: true,
                        pointRadius: 3
                    },
                    {
                        label: 'Meydan okuma',
                        data: chartData.duels,
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13, 148, 136, .10)',
                        tension: .35,
                        fill: true,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
@endpush
