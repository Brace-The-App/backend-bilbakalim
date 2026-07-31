@extends('admin.layouts.app')

@section('title', 'Düello Bot')

@push('styles')
<style>
.page-title { margin-top: 1rem !important; }
.duel-bot-list { display: flex; flex-direction: column; gap: 10px; }
.duel-bot-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    background: #fff;
    text-decoration: none !important;
    color: inherit;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.duel-bot-card:hover { border-color: #adb5bd; color: inherit; }
.duel-bot-card.is-active {
    border-color: #0d6efd;
    box-shadow: 0 0 0 2px rgba(13,110,253,.12);
}
.duel-bot-card.is-dummy { opacity: .72; background: #f8f9fa; }
.duel-bot-card__avatar-wrap {
    position: relative;
    width: 52px;
    height: 52px;
    flex-shrink: 0;
}
.duel-bot-card__avatar-wrap .duel-bot-card__avatar {
    width: 52px;
    height: 52px;
}
.duel-bot-card__power {
    position: absolute;
    top: -6px;
    left: -6px;
    z-index: 2;
    margin: 0;
    padding: 0;
    line-height: 1;
}
.duel-bot-card__power .form-check-input {
    width: 1.7em;
    height: 1em;
    margin: 0;
    cursor: pointer;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,.25);
    background-color: #adb5bd;
}
.duel-bot-card__power .form-check-input:checked {
    background-color: #198754;
    border-color: #fff;
}
.duel-bot-card__power .form-check-input:focus {
    box-shadow: 0 0 0 2px rgba(25,135,84,.35);
}
.duel-bot-card__avatar--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: #6c757d;
    font-weight: 600;
}
.duel-bot-card__meta { min-width: 0; flex: 1; }
.duel-bot-card__name { font-weight: 600; margin: 0; font-size: 0.95rem; }
.duel-bot-card__sub { margin: 2px 0 0; font-size: 0.75rem; color: #6c757d; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.duel-bot-card__badges { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }

/* Zorluk renkleri */
.badge-tier-easy { background: #198754 !important; color: #fff !important; }
.badge-tier-medium { background: #0d6efd !important; color: #fff !important; }
.badge-tier-hard { background: #fd7e14 !important; color: #fff !important; }
.badge-tier-professor { background: #6f42c1 !important; color: #fff !important; }
.duel-bot-card[data-tier="easy"] { border-left: 4px solid #198754; }
.duel-bot-card[data-tier="medium"] { border-left: 4px solid #0d6efd; }
.duel-bot-card[data-tier="hard"] { border-left: 4px solid #fd7e14; }
.duel-bot-card[data-tier="professor"] { border-left: 4px solid #6f42c1; }
.duel-bot-card.is-active[data-tier="easy"] { box-shadow: 0 0 0 2px rgba(25,135,84,.18); }
.duel-bot-card.is-active[data-tier="medium"] { box-shadow: 0 0 0 2px rgba(13,110,253,.18); }
.duel-bot-card.is-active[data-tier="hard"] { box-shadow: 0 0 0 2px rgba(253,126,20,.2); }
.duel-bot-card.is-active[data-tier="professor"] { box-shadow: 0 0 0 2px rgba(111,66,193,.2); }
tr[data-tier="easy"].table-primary { --bs-table-bg: #d1e7dd; }
tr[data-tier="medium"].table-primary { --bs-table-bg: #cfe2ff; }
tr[data-tier="hard"].table-primary { --bs-table-bg: #ffe5d0; }
tr[data-tier="professor"].table-primary { --bs-table-bg: #e2d9f3; }
.duel-bot-avatar-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
    gap: 10px;
    max-height: 280px;
    overflow-y: auto;
}
.duel-bot-avatar-option {
    position: relative;
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 12px;
    padding: 4px;
    transition: border-color .15s ease;
}
.duel-bot-avatar-option.is-selected,
.duel-bot-avatar-option:has(input:checked) { border-color: #0d6efd; }
.duel-bot-avatar-option img {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    border-radius: 8px;
    display: block;
}
.duel-bot-avatar-option input { position: absolute; opacity: 0; pointer-events: none; }
.duel-bot-preview {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #e9ecef;
}
.duel-bot-terminal {
    background: #0d1117;
    color: #c9d1d9;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    line-height: 1.5;
    border-radius: 8px;
    padding: 12px 14px;
    height: 320px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
}
.duel-bot-terminal .dim { color: #8b949e; }
.duel-bot-terminal .log-match { color: #58a6ff; }
.duel-bot-terminal .log-ok { color: #3fb950; }
.duel-bot-terminal .log-bad { color: #f85149; }
.duel-bot-terminal .log-ans { color: #d2a8ff; }
.duel-bot-dummy-panel {
    border: 1px dashed #ced4da;
    border-radius: 12px;
    padding: 48px 24px;
    text-align: center;
    background: #f8f9fa;
    color: #6c757d;
}
#duelBotWorkspace {
    scroll-margin-top: 90px;
}
</style>
@endpush

@section('content')
<div class="page-title">
    <div class="row align-items-center">
        <div class="col-12">
            <h3 class="mb-1">Düello Bot</h3>
            <p class="text-muted mb-0 small">Soldan bot seç · sağda ayar / log (çoklu bot yapısına hazır)</p>
        </div>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
@endif
<div id="duelBotToast" class="alert d-none" role="alert"></div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Canlı durum</h5>
        <span class="small text-muted" id="duelBotLiveMeta">her 3 sn</span>
    </div>
    <div class="card-body p-2">
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2 px-1" id="duelBotMixBar">
            @php
                $dash = \App\Services\DuelBotSettings::adminMatchmakingDashboard();
                $mix = ($dash['match_mix'] ?? null) ?: \App\Services\DuelBotSettings::matchMixStats(24);
                $ops = $dash['ops_stats'] ?? [];
                $tierCov = $dash['tier_coverage'] ?? ['tiers' => [], 'tips' => []];
                $fr = $ops['forfeit_reasons'] ?? [];
            @endphp
            <span class="badge bg-dark">Son 24s</span>
            <span class="badge bg-success" id="duelBotMixHuman">insan–insan {{ (int)($mix['human'] ?? 0) }}</span>
            <span class="badge bg-warning text-dark" id="duelBotMixBot">bot {{ (int)($mix['bot'] ?? 0) }}</span>
            <span class="badge bg-secondary" id="duelBotMixPct">
                bot oranı {{ isset($mix['bot_pct']) ? ('%'.$mix['bot_pct']) : '—' }}
            </span>
            <span class="badge bg-danger" id="duelBotOpsAfk" title="timeout+disconnect+afk_streak">
                AFK/timeout {{ (int)(($fr['answer_timeout'] ?? 0) + ($fr['disconnect'] ?? 0) + ($fr['afk_streak'] ?? 0)) }}
            </span>
            <span class="badge bg-info text-dark" id="duelBotOpsLeave">leave {{ (int)($fr['leave'] ?? 0) }}</span>
            <span class="badge bg-secondary" id="duelBotOpsRestart">worker restart {{ (int)($ops['worker_restarts'] ?? 0) }}</span>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2 px-1" id="duelBotTierBar">
            @foreach(($tierCov['tiers'] ?? []) as $tc)
                <span class="badge badge-tier-{{ $tc['tier'] }}" data-tier="{{ $tc['tier'] }}">
                    {{ strtoupper($tc['tier']) }} {{ (int)$tc['idle'] }}/{{ (int)$tc['active'] }} boşta
                </span>
            @endforeach
        </div>
        <div class="small text-warning px-1 mb-2 {{ empty($tierCov['tips']) ? 'd-none' : '' }}" id="duelBotTierTips">
            @foreach(($tierCov['tips'] ?? []) as $tip)
                <div>{{ $tip }}</div>
            @endforeach
        </div>
        <div class="table-responsive mb-2">
            <table class="table table-sm table-hover mb-0 align-middle" id="duelBotLiveTable">
                <thead>
                <tr class="small text-muted">
                    <th>Bot</th>
                    <th>Durum</th>
                    <th>Rakip</th>
                    <th>Soru</th>
                    <th>İsabet</th>
                    <th>Bahis</th>
                    <th>Jeton</th>
                    <th></th>
                </tr>
                </thead>
                <tbody id="duelBotLiveBody">
                <tr><td colspan="8" class="text-muted">Yükleniyor…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="border-top pt-2 px-1">
            <div class="small text-muted mb-1">Neden bu bot? · son pick’ler</div>
            <div class="small font-monospace" id="duelBotPickLines" style="max-height:140px;overflow:auto;line-height:1.45">
                @php $picks = ($dash['recent_picks'] ?? null) ?: \App\Services\DuelBotSettings::recentPickInsights(8); @endphp
                @forelse($picks as $p)
                    <div class="{{ ($p['status'] ?? '') === 'ok' ? 'text-success' : (($p['status'] ?? '') === 'cooldown' ? 'text-warning' : 'text-muted') }}">
                        {{ $p['at'] ?? '' }} · {{ $p['line'] ?? json_encode($p) }}
                    </div>
                @empty
                    <div class="text-muted">Henüz pick yok</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@php
    $mm = $matchmaking ?? \App\Services\DuelBotSettings::matchmakingSettings();
    $tierKeys = ['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard', 'professor' => 'Profesör'];
@endphp
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Akıllı eşleşme</h5>
        <button type="button" class="btn btn-sm btn-primary" id="duelBotMmSave">Kaydet</button>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3 mb-md-2">
            Oyuncunun son cevaplarındaki doğruluk oranına göre bot seviyesi seçilir.
            Kuyrukta 2+ insan varken bot yok. Bot’la peş peşe çok maç olursa sistem kısa ek bekleme uygular (arka planda).
        </p>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="mm_cooldown">Bot sonrası bekleme (sn)</label>
                <input type="number" class="form-control" id="mm_cooldown" min="0" max="120"
                       value="{{ (int) $mm['rematch_cooldown_seconds'] }}">
                <div class="form-text">Bot’la maç bitince tekrar bot’a düşmeden önce bu kadar bekler (0 = kapalı). Öneri: 5 sn.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="mm_new_player">Yeni oyuncu eşiği</label>
                <input type="number" class="form-control" id="mm_new_player" min="0" max="50"
                       value="{{ (int) $mm['new_player_max_duels'] }}">
                <div class="form-text">Bitmiş düellosu bu sayıdan azsa hep orta bot (medium).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="mm_sample">İsabet hesabı (son kaç cevap)</label>
                <input type="number" class="form-control" id="mm_sample" min="5" max="100"
                       value="{{ (int) $mm['skill_sample_answers'] }}">
                <div class="form-text">
                    Oyuncunun “ne kadar iyi” olduğunu ölçmek için son kaç düello cevabına bakılır.
                    Örn. 25 → son 25 cevaptan % doğru; buna göre easy/medium/hard bot seçilir.
                </div>
            </div>
        </div>
        {{-- Soft cap (peş peşe bot) ayarları şimdilik gizli; varsayılan: 3 maç → +30sn cooldown, kuyruk +2sn --}}
        <input type="hidden" id="mm_soft_streak" value="{{ (int) ($mm['soft_cap_streak'] ?? 3) }}">
        <input type="hidden" id="mm_soft_extra" value="{{ (int) ($mm['soft_cap_extra_seconds'] ?? 10) }}">
        <input type="hidden" id="mm_soft_wait" value="{{ (int) ($mm['soft_cap_wait_bump'] ?? 2) }}">
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0" id="duelBotMmBands">
                <thead class="table-light">
                <tr class="small">
                    <th>Etiket</th>
                    <th>≤ İsabet %</th>
                    <th>İzinli bot zorlukları</th>
                </tr>
                </thead>
                <tbody>
                @foreach($mm['bands'] as $bi => $band)
                    <tr data-band-idx="{{ $bi }}">
                        <td>
                            <input type="text" class="form-control form-control-sm mm-label"
                                   value="{{ $band['label'] }}" maxlength="40">
                        </td>
                        <td style="max-width:110px">
                            <input type="number" class="form-control form-control-sm mm-max" min="1" max="100"
                                   value="{{ (int) $band['max_pct'] }}">
                        </td>
                        <td>
                            @foreach($tierKeys as $tk => $tl)
                                <label class="form-check form-check-inline small mb-0">
                                    <input class="form-check-input mm-tier" type="checkbox" value="{{ $tk }}"
                                        @checked(in_array($tk, $band['tiers'] ?? [], true))>
                                    {{ $tl }}
                                </label>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-text mt-2">Örnek: ≤40 → easy+medium; boşta yoksa komşu banda düşülür.</div>
    </div>
</div>

<div class="row" id="duelBotWorkspace">
    {{-- Sol: bot kartları --}}
    <div class="col-lg-3 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center gap-2">
                <h5 class="mb-0">Botlar</h5>
                <div class="d-flex align-items-center gap-1">
                    <span class="badge bg-light text-dark" id="duelBotListCount">{{ count($bots) }}</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#duelBotCreateModal">
                        Yeni bot
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <input type="search" class="form-control form-control-sm" id="duelBotSearch"
                           placeholder="Bot adına göre ara..." autocomplete="off">
                </div>
                <div class="duel-bot-list" id="duelBotList">
                    @foreach($bots as $item)
                        <a href="{{ route('admin.duel-bot.index', ['bot' => $item['id']]) }}#duelBotWorkspace"
                           class="duel-bot-card {{ (string)$selectedId === (string)$item['id'] ? 'is-active' : '' }} {{ !empty($item['is_dummy']) ? 'is-dummy' : '' }}"
                           data-tier="{{ $item['difficulty'] ?? '' }}"
                           data-bot-id="{{ $item['user_id'] ?? $item['id'] }}"
                           data-name="{{ strtolower($item['name'] . ' ' . ($item['subtitle'] ?? '')) }}">
                            <div class="duel-bot-card__avatar-wrap">
                                @if(empty($item['is_dummy']))
                                    <div class="form-check form-switch duel-bot-card__power" title="{{ !empty($item['is_active']) ? 'Aktif — tıkla pasif yap' : 'Pasif — tıkla aktif yap' }}">
                                        <input class="form-check-input js-bot-card-active" type="checkbox" role="switch"
                                               data-bot-id="{{ (int) ($item['user_id'] ?? $item['id']) }}"
                                               {{ !empty($item['is_active']) ? 'checked' : '' }}
                                               aria-label="Bot aktif">
                                    </div>
                                @endif
                                @if(!empty($item['avatar_url']))
                                    <img class="duel-bot-card__avatar" src="{{ $item['avatar_url'] }}" alt="">
                                @else
                                    <div class="duel-bot-card__avatar duel-bot-card__avatar--empty">BOT</div>
                                @endif
                            </div>
                            <div class="duel-bot-card__meta">
                                <p class="duel-bot-card__name">{{ $item['name'] }}</p>
                                <p class="duel-bot-card__sub">{{ $item['subtitle'] }}</p>
                                <div class="duel-bot-card__badges">
                                    @if(!empty($item['is_dummy']))
                                        <span class="badge bg-secondary">Dummy</span>
                                    @else
                                        <span class="badge bg-dark">BOT</span>
                                        <span class="badge js-bot-card-status {{ !empty($item['is_active']) ? 'bg-success' : 'bg-secondary' }}">
                                            {{ !empty($item['is_active']) ? 'Aktif' : 'Pasif' }}
                                        </span>
                                        @if(!empty($item['difficulty']))
                                            <span class="badge badge-tier-{{ $item['difficulty'] }}">{{ strtoupper($item['difficulty']) }}</span>
                                        @endif
                                        @if(!empty($item['busy']))
                                            <span class="badge bg-warning text-dark">Maçta</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="small text-muted mt-2 d-none" id="duelBotSearchEmpty">Sonuç yok</div>
            </div>
        </div>
    </div>

    {{-- Sağ: seçilen bot --}}
    <div class="col-lg-9 mb-3">
        @if(!empty($selected['is_dummy']))
            <div class="duel-bot-dummy-panel">
                <h5 class="mb-2">{{ $selected['name'] }}</h5>
                <p class="mb-0">Soldan bir bot seçin veya <strong>Yeni bot</strong> ile zorluk seçerek hızlı oluşturun.</p>
            </div>
        @elseif(!$bot || !$showDetail)
            <div class="alert alert-warning mb-0">Bot bulunamadı veya seçilemedi.</div>
        @else
            @php
                $previewUrl = $bot->resolveAvatarUrl() ?? asset('storage/' . ($bot->profile_image ?: 'avatars/default.png'));
            @endphp

            <div class="card mb-3">
                <div class="card-body d-flex align-items-center gap-3 flex-wrap">
                    <img id="duelBotPreview" class="duel-bot-preview" src="{{ $previewUrl }}" alt="{{ $bot->name }}">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap">
                            <div>
                                <h4 class="mb-1" id="duelBotNameLabel">{{ $bot->name }}</h4>
                                <div class="text-muted small mb-2">#{{ $bot->id }} · {{ $bot->email }}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary js-bot-duels"
                                    data-bot-id="{{ $bot->id }}"
                                    data-bot-name="{{ $bot->name }}">Düello geçmişi</button>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-dark">BOT</span>
                            <span id="duelBotStatusBadge" class="badge {{ $settings['is_active'] ? 'bg-success' : 'bg-secondary' }}">
                                {{ $settings['is_active'] ? 'Aktif' : 'Pasif' }}
                            </span>
                            @php
                                $tierMeta = $settings['tier_meta'] ?? [];
                                $accBand = isset($tierMeta['min'], $tierMeta['max'])
                                    ? ('%' . (int) round($tierMeta['min'] * 100) . '–' . (int) round($tierMeta['max'] * 100))
                                    : null;
                            @endphp
                            <span id="duelBotDifficultyBadge" class="badge badge-tier-{{ $settings['difficulty'] }}">{{ strtoupper($settings['difficulty']) }}</span>
                            @php
                                $ex8 = \App\Services\BotAnswerEngine::discreteExamples($settings['difficulty'], 8)[0] ?? null;
                            @endphp
                            @if($accBand)
                                <span class="badge bg-light text-dark" id="duelBotAccBadge">
                                    Bant {{ $accBand }}
                                    @if($ex8) · 8q ≈ {{ $ex8['correct'] }}/8 (%{{ $ex8['pct'] }}) @endif
                                </span>
                            @endif
                            <span id="duelBotWaitBadge" class="badge bg-light text-dark">Bekleme {{ $settings['match_wait_seconds'] }} sn</span>
                            @if(!empty($selected['busy']))
                                <span class="badge bg-warning text-dark">Şu an maçta</span>
                            @endif
                            <span class="badge bg-light text-dark">Jeton: <span id="duelBotCoinsLabel">{{ number_format((int) $bot->coins) }}</span></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="mb-0">Bot davranışı</h5></div>
                <div class="card-body">
                    <div class="alert alert-light border small mb-3" id="duelBotTierHelp">
                        {!! nl2br(e(\App\Services\BotAnswerEngine::tierHelpText($settings['difficulty']))) !!}
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered mb-0 small">
                            <thead class="table-light">
                            <tr>
                                <th>Seviye</th>
                                <th>Hedef bant</th>
                                <th>8 soruda mümkün</th>
                                <th>10 soruda mümkün</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach(\App\Services\BotAnswerEngine::tierGuideRows() as $row)
                                <tr data-tier="{{ $row['key'] }}" class="{{ $settings['difficulty'] === $row['key'] ? 'table-primary' : '' }}">
                                    <td><strong>{{ $row['label'] }}</strong></td>
                                    <td>%{{ $row['min_pct'] }}–{{ $row['max_pct'] }} (≈%{{ $row['target_pct'] }})</td>
                                    <td>{{ $row['example_8'] }}</td>
                                    <td>{{ $row['example_10'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <div class="form-text mt-1">Kısa maçta % kesirli olmaz; motor bu “mümkün” doğru sayılarına çeker.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Durum</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" id="is_active"
                                    {{ $settings['is_active'] ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_active">Bot aktif</label>
                            </div>
                            <div class="form-text">Toggle anında kaydolur.</div>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="difficulty">Zorluk</label>
                            <select class="form-select" id="difficulty">
                                @foreach(\App\Services\BotAnswerEngine::tierOptions() as $value => $label)
                                    <option value="{{ $value }}" @selected($settings['difficulty'] === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Maç boyunca isabet bu banda çekilir; her soru yine rastgele sıralı doğru/yanlış gelebilir.</div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="match_wait_seconds">Eşleşme bekleme (sn)</label>
                            <input type="number" class="form-control" id="match_wait_seconds"
                                   min="1" max="30" value="{{ $settings['match_wait_seconds'] }}">
                        </div>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.duel-bot.profile') }}" class="card mb-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="user_id" value="{{ $bot->id }}">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Profil</h5>
                    <button type="submit" class="btn btn-sm btn-primary">Profili kaydet</button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">İsim</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $bot->name) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="coins">Jeton</label>
                            <input type="number" class="form-control" id="coins" name="coins" min="0" value="{{ old('coins', $bot->coins) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" value="{{ old('email', $bot->email) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telefon</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $bot->phone) }}">
                        </div>
                    </div>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.duel-bot.avatar') }}" class="card mb-3">
                @csrf
                @method('PUT')
                <input type="hidden" name="user_id" value="{{ $bot->id }}">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Avatar seç</h5>
                    <button type="submit" class="btn btn-sm btn-primary">Avatarı kaydet</button>
                </div>
                <div class="card-body">
                    <div class="duel-bot-avatar-grid">
                        @foreach($avatars as $avatar)
                            <label class="duel-bot-avatar-option {{ (string) old('avatar', $bot->avatar) === (string) $avatar->id ? 'is-selected' : '' }}">
                                <input type="radio" name="avatar" value="{{ $avatar->id }}"
                                       {{ (string) old('avatar', $bot->avatar) === (string) $avatar->id ? 'checked' : '' }}
                                       data-url="{{ $avatar->image_url }}" required>
                                <img src="{{ $avatar->image_url }}" alt="Avatar {{ $avatar->id }}">
                            </label>
                        @endforeach
                    </div>
                </div>
            </form>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0">Canlı bot log</h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small text-muted" id="duelBotLogMeta">her 2 sn yenilenir</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="duelBotLogClear">Temizle</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-2" id="duelBotLogFilters">
                        <button type="button" class="btn btn-sm {{ empty($logBotFilter) ? 'btn-primary' : 'btn-outline-secondary' }} js-log-filter" data-bot="">Tümü</button>
                        @foreach($bots as $item)
                            @if(empty($item['is_dummy']) && !empty($item['user_id']))
                                <button type="button"
                                        class="btn btn-sm js-log-filter {{ (int)($logBotFilter ?? 0) === (int)$item['user_id'] ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        data-bot="{{ $item['user_id'] }}"
                                        title="{{ $item['name'] }}">
                                    #{{ $item['user_id'] }}
                                    <span class="opacity-75">{{ strtoupper($item['difficulty'] ?? '') }}</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                    <div class="duel-bot-terminal" id="duelBotTerminal">@foreach(($logs ?? []) as $line)
{{ $line }}
@endforeach</div>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Hızlı bot oluştur --}}
<div class="modal fade" id="duelBotCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni bot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-3">
                    İsim, e-posta, avatar ve 1000 jeton otomatik hazırlanır. Sadece zorluk seç; bot <strong>pasif</strong> olarak listeye eklenir.
                </p>
                <label class="form-label" for="duelBotCreateDifficulty">Zorluk</label>
                <select class="form-select" id="duelBotCreateDifficulty">
                    @foreach(\App\Services\BotAnswerEngine::tierOptions() as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <button type="button" class="btn btn-primary" id="duelBotCreateBtn">Oluştur</button>
            </div>
        </div>
    </div>
</div>

{{-- Bot düello geçmişi modal --}}
<div class="modal fade" id="duelBotHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="duelBotHistoryTitle">Düello geçmişi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="duelBotHistoryMeta"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr class="small text-muted">
                            <th>#</th>
                            <th>Tarih</th>
                            <th>Rakip</th>
                            <th>x</th>
                            <th>Sonuç</th>
                            <th>Doğru</th>
                            <th>Yanlış</th>
                            <th>İsabet</th>
                            <th>+Jeton</th>
                            <th>−Jeton</th>
                            <th>Net</th>
                            <th>Bakiye</th>
                        </tr>
                        </thead>
                        <tbody id="duelBotHistoryBody">
                        <tr><td colspan="12" class="text-muted">Yükleniyor…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="form-text mt-2">Satıra tıkla → soru detayı</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

{{-- Soru detay modal --}}
<div class="modal fade" id="duelBotDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="duelBotDetailTitle">Soru detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="duelBotDetailMeta"></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr class="small text-muted">
                            <th>#</th>
                            <th>Soru</th>
                            <th>Doğru şık</th>
                            <th>Bot</th>
                            <th>Rakip</th>
                        </tr>
                        </thead>
                        <tbody id="duelBotDetailBody">
                        <tr><td colspan="5" class="text-muted">Yükleniyor…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Seçili bot varken (veya #duelBotWorkspace) sayfa bot paneline gelsin
    function focusBotWorkspace() {
        var params = new URLSearchParams(window.location.search);
        var hasBot = params.has('bot') && String(params.get('bot') || '') !== '';
        var hasHash = (window.location.hash || '') === '#duelBotWorkspace';
        if (!hasBot && !hasHash) return;

        var workspace = document.getElementById('duelBotWorkspace');
        if (workspace) {
            workspace.scrollIntoView({ behavior: 'auto', block: 'start' });
        }

        var active = document.querySelector('#duelBotList .duel-bot-card.is-active');
        var list = document.getElementById('duelBotList');
        if (active && list && typeof active.scrollIntoView === 'function') {
            active.scrollIntoView({ behavior: 'auto', block: 'nearest' });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            setTimeout(focusBotWorkspace, 50);
        });
    } else {
        setTimeout(focusBotWorkspace, 50);
    }
    window.addEventListener('load', function () {
        setTimeout(focusBotWorkspace, 80);
    });

    var search = document.getElementById('duelBotSearch');
    var list = document.getElementById('duelBotList');
    var empty = document.getElementById('duelBotSearchEmpty');
    var countBadge = document.getElementById('duelBotListCount');
    if (search && list) {
        search.addEventListener('input', function () {
            var q = (search.value || '').toLocaleLowerCase('tr-TR').trim();
            var cards = list.querySelectorAll('.duel-bot-card');
            var shown = 0;
            cards.forEach(function (card) {
                var name = (card.getAttribute('data-name') || '').toLocaleLowerCase('tr-TR');
                var ok = !q || name.indexOf(q) !== -1;
                card.classList.toggle('d-none', !ok);
                if (ok) shown++;
            });
            if (empty) empty.classList.toggle('d-none', shown > 0);
            if (countBadge) countBadge.textContent = String(shown);
        });
    }

    // Sol kart: avatar üstü aktif/pasif (karta girmeden)
    var csrfCard = @json(csrf_token());
    var activeUrlCard = @json(route('admin.duel-bot.active'));
    var selectedBotIdCard = @json((string) ((!empty($showDetail) && !empty($bot)) ? $bot->id : ($selectedId ?? '')));

    function toastCard(msg, ok) {
        var el = document.getElementById('duelBotToast');
        if (!el) return;
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = msg;
        el.classList.remove('d-none');
        setTimeout(function () { el.classList.add('d-none'); }, 2800);
    }

    function syncCardActiveUi(botId, on) {
        var card = document.querySelector('.duel-bot-card[data-bot-id="' + botId + '"]');
        if (card) {
            var badge = card.querySelector('.js-bot-card-status');
            if (badge) {
                badge.textContent = on ? 'Aktif' : 'Pasif';
                badge.className = 'badge js-bot-card-status ' + (on ? 'bg-success' : 'bg-secondary');
            }
            var sw = card.querySelector('.js-bot-card-active');
            if (sw) {
                sw.checked = !!on;
                var wrap = sw.closest('.duel-bot-card__power');
                if (wrap) wrap.title = on ? 'Aktif — tıkla pasif yap' : 'Pasif — tıkla aktif yap';
            }
        }
        // Sağ panel aynı bot açıksa senkron
        if (String(botId) === String(selectedBotIdCard)) {
            var detailSw = document.getElementById('is_active');
            var statusBadge = document.getElementById('duelBotStatusBadge');
            if (detailSw) detailSw.checked = !!on;
            if (statusBadge) {
                statusBadge.textContent = on ? 'Aktif' : 'Pasif';
                statusBadge.className = 'badge ' + (on ? 'bg-success' : 'bg-secondary');
            }
        }
    }

    // Toggle tıklanınca kart linkine gitmesin (preventDefault yok — checkbox çalışsın)
    document.addEventListener('click', function (e) {
        var power = e.target.closest('.duel-bot-card__power');
        if (!power) return;
        e.stopPropagation();
    }, true);

    document.addEventListener('change', function (e) {
        var sw = e.target.closest('.js-bot-card-active');
        if (!sw) return;
        e.stopPropagation();
        var botId = parseInt(sw.getAttribute('data-bot-id') || '0', 10);
        var on = !!sw.checked;
        if (!botId) return;
        sw.disabled = true;
        fetch(activeUrlCard, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfCard,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ user_id: botId, is_active: on })
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              sw.disabled = false;
              if (!res.ok || !res.data.success) {
                  sw.checked = !on;
                  toastCard((res.data && res.data.message) || 'Kaydedilemedi', false);
                  return;
              }
              var finalOn = !!(res.data.is_active != null ? res.data.is_active : on);
              syncCardActiveUi(botId, finalOn);
              toastCard(res.data.message || (finalOn ? 'Aktif' : 'Pasif'), true);
          }).catch(function () {
              sw.disabled = false;
              sw.checked = !on;
              toastCard('Bağlantı hatası', false);
          });
    });

    // Akıllı eşleşme kaydet
    var mmSave = document.getElementById('duelBotMmSave');
    if (mmSave) {
        var csrfMm = @json(csrf_token());
        var mmUrl = @json(route('admin.duel-bot.matchmaking'));
        function toastMm(msg, ok) {
            var el = document.getElementById('duelBotToast');
            if (!el) return;
            el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
            el.textContent = msg;
            el.classList.remove('d-none');
            setTimeout(function () { el.classList.add('d-none'); }, 3200);
        }
        mmSave.addEventListener('click', function () {
            var bands = [];
            document.querySelectorAll('#duelBotMmBands tbody tr').forEach(function (tr) {
                var tiers = [];
                tr.querySelectorAll('.mm-tier:checked').forEach(function (cb) { tiers.push(cb.value); });
                bands.push({
                    label: (tr.querySelector('.mm-label') || {}).value || '',
                    max_pct: parseInt((tr.querySelector('.mm-max') || {}).value || '100', 10),
                    tiers: tiers.length ? tiers : ['medium']
                });
            });
            mmSave.disabled = true;
            fetch(mmUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfMm,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    rematch_cooldown_seconds: parseInt(document.getElementById('mm_cooldown').value || '5', 10),
                    new_player_max_duels: parseInt(document.getElementById('mm_new_player').value || '5', 10),
                    skill_sample_answers: parseInt(document.getElementById('mm_sample').value || '25', 10),
                    soft_cap_streak: parseInt((document.getElementById('mm_soft_streak') || {}).value || '3', 10),
                    soft_cap_extra_seconds: parseInt((document.getElementById('mm_soft_extra') || {}).value || '10', 10),
                    soft_cap_wait_bump: parseInt((document.getElementById('mm_soft_wait') || {}).value || '2', 10),
                    bands: bands
                })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
              .then(function (res) {
                  mmSave.disabled = false;
                  toastMm((res.data && res.data.message) || (res.ok ? 'Kaydedildi' : 'Hata'), !!res.ok && res.data.success);
              }).catch(function () {
                  mmSave.disabled = false;
                  toastMm('Bağlantı hatası', false);
              });
        });
    }

    // Hızlı bot oluştur
    var createBtn = document.getElementById('duelBotCreateBtn');
    if (createBtn) {
        var csrfCreate = @json(csrf_token());
        var createUrl = @json(route('admin.duel-bot.store'));
        createBtn.addEventListener('click', function () {
            var diff = (document.getElementById('duelBotCreateDifficulty') || {}).value || 'medium';
            createBtn.disabled = true;
            fetch(createUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfCreate,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ difficulty: diff })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
              .then(function (res) {
                  createBtn.disabled = false;
                  if (!res.ok || !res.data.success) {
                      alert((res.data && res.data.message) || 'Oluşturulamadı');
                      return;
                  }
                  var url = res.data.bot && res.data.bot.redirect;
                  if (url) window.location.href = url;
                  else window.location.reload();
              }).catch(function () {
                  createBtn.disabled = false;
                  alert('Bağlantı hatası');
              });
        });
    }
})();
</script>
@if(!empty($showDetail) && $bot)
<script>
(function () {
    var csrf = @json(csrf_token());
    var botUserId = {{ (int) $bot->id }};
    var activeUrl = @json(route('admin.duel-bot.active'));
    var behaviorUrl = @json(route('admin.duel-bot.behavior'));
    var clearUrl = @json(route('admin.duel-bot.logs.clear'));

    function toast(msg, ok) {
        var el = document.getElementById('duelBotToast');
        if (!el) return;
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.textContent = msg;
        el.classList.remove('d-none');
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.classList.add('d-none'); }, 2500);
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body || {})
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }

    var activeInput = document.getElementById('is_active');
    var statusBadge = document.getElementById('duelBotStatusBadge');
    if (activeInput) {
        activeInput.addEventListener('change', function () {
            var on = !!activeInput.checked;
            activeInput.disabled = true;
            postJson(activeUrl, { is_active: on, user_id: botUserId }).then(function (res) {
                activeInput.disabled = false;
                if (!res.ok || !res.data.success) {
                    activeInput.checked = !on;
                    toast((res.data && res.data.message) || 'Kaydedilemedi', false);
                    return;
                }
                if (statusBadge) {
                    statusBadge.textContent = on ? 'Aktif' : 'Pasif';
                    statusBadge.className = 'badge ' + (on ? 'bg-success' : 'bg-secondary');
                }
                var card = document.querySelector('.duel-bot-card[data-bot-id="' + botUserId + '"]');
                if (card) {
                    var cardBadge = card.querySelector('.js-bot-card-status');
                    if (cardBadge) {
                        cardBadge.textContent = on ? 'Aktif' : 'Pasif';
                        cardBadge.className = 'badge js-bot-card-status ' + (on ? 'bg-success' : 'bg-secondary');
                    }
                    var cardSw = card.querySelector('.js-bot-card-active');
                    if (cardSw) cardSw.checked = !!on;
                    var wrap = card.querySelector('.duel-bot-card__power');
                    if (wrap) wrap.title = on ? 'Aktif — tıkla pasif yap' : 'Pasif — tıkla aktif yap';
                }
                toast(res.data.message || 'Kaydedildi', true);
            }).catch(function () {
                activeInput.disabled = false;
                activeInput.checked = !on;
                toast('Bağlantı hatası', false);
            });
        });
    }

    var difficulty = document.getElementById('difficulty');
    var waitInput = document.getElementById('match_wait_seconds');
    var diffBadge = document.getElementById('duelBotDifficultyBadge');
    var waitBadge = document.getElementById('duelBotWaitBadge');
    var behaviorTimer = null;

    var tierHelpMap = @json($tierHelpMap ?? []);
    var tierEx8Map = @json($tierEx8Map ?? []);

    function refreshTierUi(tier) {
        var help = document.getElementById('duelBotTierHelp');
        if (help && tierHelpMap[tier]) {
            help.innerHTML = String(tierHelpMap[tier]).replace(/\n/g, '<br>');
        }
        var accBadge = document.getElementById('duelBotAccBadge');
        if (accBadge && tierEx8Map[tier]) {
            accBadge.textContent = 'Bant ' + tierEx8Map[tier].band + (tierEx8Map[tier].ex8 ? (' · 8q ≈ ' + tierEx8Map[tier].ex8) : '');
        }
        document.querySelectorAll('tr[data-tier]').forEach(function (tr) {
            tr.classList.toggle('table-primary', tr.getAttribute('data-tier') === tier);
        });
    }

    function saveBehavior() {
        if (!difficulty || !waitInput) return;
        var payload = {
            user_id: botUserId,
            difficulty: difficulty.value,
            match_wait_seconds: parseInt(waitInput.value, 10) || 3
        };
        postJson(behaviorUrl, payload).then(function (res) {
            if (!res.ok || !res.data.success) {
                toast((res.data && res.data.message) || 'Davranış kaydedilemedi', false);
                return;
            }
            if (diffBadge) {
                diffBadge.textContent = String(payload.difficulty).toUpperCase();
                diffBadge.className = 'badge badge-tier-' + String(payload.difficulty || 'medium');
            }
            if (waitBadge) waitBadge.textContent = 'Bekleme ' + payload.match_wait_seconds + ' sn';
            refreshTierUi(payload.difficulty);
            toast('Davranış kaydedildi', true);
        }).catch(function () {
            toast('Bağlantı hatası', false);
        });
    }

    if (difficulty) difficulty.addEventListener('change', saveBehavior);
    if (waitInput) {
        waitInput.addEventListener('change', saveBehavior);
        waitInput.addEventListener('input', function () {
            clearTimeout(behaviorTimer);
            behaviorTimer = setTimeout(saveBehavior, 600);
        });
    }

    document.querySelectorAll('.duel-bot-avatar-option input').forEach(function (input) {
        input.addEventListener('change', function () {
            document.querySelectorAll('.duel-bot-avatar-option').forEach(function (el) {
                el.classList.toggle('is-selected', el.querySelector('input').checked);
            });
            var preview = document.getElementById('duelBotPreview');
            if (preview && input.dataset.url) preview.src = input.dataset.url;
        });
    });

    function paintLog(lines) {
        var el = document.getElementById('duelBotTerminal');
        if (!el) return;
        var html = (lines || []).map(function (line) {
            var cls = '';
            if (line.indexOf('EŞLEŞME') !== -1) cls = 'log-match';
            else if (line.indexOf('BAHİS TEKLİF') !== -1 || line.indexOf('BAHİS KARAR') !== -1) cls = 'log-ans';
            else if (line.indexOf('DOĞRU') !== -1) cls = 'log-ok';
            else if (line.indexOf('YANLIŞ') !== -1) cls = 'log-bad';
            else if (line.indexOf('HATA') !== -1 || line.indexOf('hata') !== -1 || line.indexOf('RED') !== -1) cls = 'log-bad';
            else if (line.indexOf('SONUÇ') !== -1 || line.indexOf('CEVAP') !== -1) cls = 'log-ans';
            return '<div class="' + cls + '">' + line.replace(/</g, '&lt;') + '</div>';
        }).join('');
        var stick = (el.scrollTop + el.clientHeight) >= (el.scrollHeight - 24);
        el.innerHTML = html || '<div class="dim">Log boş</div>';
        if (stick) el.scrollTop = el.scrollHeight;
    }

    var el = document.getElementById('duelBotTerminal');
    var meta = document.getElementById('duelBotLogMeta');
    var logFilterBot = @json((string) ($logBotFilter ?? ''));
    var logsBaseUrl = @json(route('admin.duel-bot.logs'));

    function logsUrl() {
        var u = logsBaseUrl + (logsBaseUrl.indexOf('?') >= 0 ? '&' : '?') + 'limit=200';
        if (logFilterBot) u += '&bot=' + encodeURIComponent(logFilterBot);
        return u;
    }

    function setLogFilterActive() {
        document.querySelectorAll('.js-log-filter').forEach(function (btn) {
            var id = btn.getAttribute('data-bot') || '';
            var on = id === String(logFilterBot || '');
            btn.classList.toggle('btn-primary', on);
            btn.classList.toggle('btn-outline-secondary', !on);
        });
    }

    if (el) {
        function refresh() {
            fetch(logsUrl(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.success) return;
                    paintLog(data.lines || []);
                    if (meta) {
                        var f = data.bot_filter ? ('filtre #' + data.bot_filter + ' · ') : 'tümü · ';
                        meta.textContent = f + (data.lines || []).length + ' satır · son: ' + (data.server_time || '');
                    }
                })
                .catch(function () {});
        }
        setInterval(refresh, 6000);
        refresh();
        setLogFilterActive();
    }

    document.querySelectorAll('.js-log-filter').forEach(function (btn) {
        btn.addEventListener('click', function () {
            logFilterBot = btn.getAttribute('data-bot') || '';
            setLogFilterActive();
            if (el) {
                fetch(logsUrl(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.success) return;
                        paintLog(data.lines || []);
                        if (meta) {
                            var f = data.bot_filter ? ('filtre #' + data.bot_filter + ' · ') : 'tümü · ';
                            meta.textContent = f + (data.lines || []).length + ' satır · son: ' + (data.server_time || '');
                        }
                    });
            }
        });
    });

    var clearBtn = document.getElementById('duelBotLogClear');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            clearBtn.disabled = true;
            postJson(clearUrl, { bot: logFilterBot || null }).then(function (res) {
                clearBtn.disabled = false;
                if (res.ok && res.data.success) {
                    paintLog(res.data.lines || []);
                    toast('Log temizlendi', true);
                } else {
                    toast('Temizlenemedi', false);
                }
            }).catch(function () {
                clearBtn.disabled = false;
                toast('Bağlantı hatası', false);
            });
        });
    }
})();
</script>
@endif

<script>
(function () {
    function esc(s) {
        return String(s == null ? '' : s).replace(/</g, '&lt;');
    }

    function paintMatchmakingDash(mm) {
        if (!mm) return;
        var mix = mm.match_mix || {};
        var h = document.getElementById('duelBotMixHuman');
        var b = document.getElementById('duelBotMixBot');
        var p = document.getElementById('duelBotMixPct');
        if (h) h.textContent = 'insan–insan ' + (mix.human || 0);
        if (b) b.textContent = 'bot ' + (mix.bot || 0);
        if (p) p.textContent = 'bot oranı ' + (mix.bot_pct != null ? ('%' + mix.bot_pct) : '—');

        var ops = mm.ops_stats || {};
        var fr = ops.forfeit_reasons || {};
        var afk = document.getElementById('duelBotOpsAfk');
        var leave = document.getElementById('duelBotOpsLeave');
        var restart = document.getElementById('duelBotOpsRestart');
        var afkN = (fr.answer_timeout || 0) + (fr.disconnect || 0) + (fr.afk_streak || 0);
        if (afk) afk.textContent = 'AFK/timeout ' + afkN;
        if (leave) leave.textContent = 'leave ' + (fr.leave || 0);
        if (restart) restart.textContent = 'worker restart ' + (ops.worker_restarts || 0);

        var cov = mm.tier_coverage || {};
        var tierBar = document.getElementById('duelBotTierBar');
        if (tierBar && Array.isArray(cov.tiers)) {
            tierBar.innerHTML = cov.tiers.map(function (tc) {
                return '<span class="' + tierBadgeClass(tc.tier) + '" data-tier="' + esc(tc.tier) + '">'
                    + String(tc.tier || '').toUpperCase() + ' '
                    + (tc.idle || 0) + '/' + (tc.active || 0) + ' boşta</span>';
            }).join(' ');
        }
        var tipsEl = document.getElementById('duelBotTierTips');
        if (tipsEl) {
            var tips = cov.tips || [];
            if (!tips.length) {
                tipsEl.classList.add('d-none');
                tipsEl.innerHTML = '';
            } else {
                tipsEl.classList.remove('d-none');
                tipsEl.innerHTML = tips.map(function (t) { return '<div>' + esc(t) + '</div>'; }).join('');
            }
        }

        var box = document.getElementById('duelBotPickLines');
        if (!box) return;
        var picks = mm.recent_picks || [];
        if (!picks.length) {
            box.innerHTML = '<div class="text-muted">Henüz pick yok</div>';
            return;
        }
        box.innerHTML = picks.map(function (row) {
            var cls = row.status === 'ok' ? 'text-success' : (row.status === 'cooldown' ? 'text-warning' : 'text-muted');
            return '<div class="' + cls + '">' + esc(String(row.at || '')) + ' · ' + esc(String(row.line || '')) + '</div>';
        }).join('');
    }

    function tierBadgeClass(tier) {
        var t = String(tier || '').toLowerCase();
        if (t === 'easy' || t === 'medium' || t === 'hard' || t === 'professor') {
            return 'badge badge-tier-' + t;
        }
        return 'badge bg-secondary';
    }

    function paintLive(bots) {
        var body = document.getElementById('duelBotLiveBody');
        var liveMeta = document.getElementById('duelBotLiveMeta');
        if (!body) return;
        body.innerHTML = (bots || []).map(function (s) {
            var status = !s.is_active
                ? '<span class="badge bg-secondary">Pasif</span>'
                : (s.busy
                    ? '<span class="badge bg-warning text-dark">Maçta #' + s.duel_id + '</span>'
                    : '<span class="badge bg-success">Boşta</span>');
            var opp = s.opponent_name ? ('#' + s.opponent_id + ' ' + s.opponent_name) : '—';
            var q = s.question_number ? ('Q' + s.question_number) : '—';
            var acc = s.answered > 0 ? (s.correct + '/' + s.answered + ' (%' + s.accuracy_pct + ')') : '—';
            var bet = s.pending_bet || '—';
            return '<tr data-bot-id="' + s.user_id + '">'
                + '<td><strong>' + esc(s.name) + '</strong> '
                + '<span class="' + tierBadgeClass(s.difficulty) + '">' + String(s.difficulty || '').toUpperCase() + '</span></td>'
                + '<td>' + status + '</td>'
                + '<td>' + esc(opp) + '</td>'
                + '<td>' + q + '</td>'
                + '<td>' + acc + '</td>'
                + '<td>' + bet + '</td>'
                + '<td>' + Number(s.coins || 0).toLocaleString('tr-TR') + '</td>'
                + '<td><button type="button" class="btn btn-sm btn-outline-primary js-bot-duels" data-bot-id="'
                + s.user_id + '" data-bot-name="' + esc(s.name).replace(/"/g, '&quot;') + '">Geçmiş</button></td>'
                + '</tr>';
        }).join('') || '<tr><td colspan="8" class="text-muted">Bot yok</td></tr>';
        if (liveMeta) liveMeta.textContent = 'canlı';
    }

    var liveUrl = @json(route('admin.duel-bot.live'));
    var duelsUrlTpl = @json(url('/admin/duel-bot'));
    function refreshLive() {
        fetch(liveUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) return;
                paintLive(data.bots || data.live || []);
                paintMatchmakingDash(data.matchmaking || null);
                var liveMeta = document.getElementById('duelBotLiveMeta');
                if (liveMeta) liveMeta.textContent = 'son: ' + (data.server_time || '');
            })
            .catch(function () {});
    }
    setInterval(refreshLive, 3000);
    refreshLive();

    function resultBadge(r) {
        if (r === 'galibiyet') return '<span class="badge bg-success">Galibiyet</span>';
        if (r === 'galibiyet_timeout') return '<span class="badge bg-success">Galibiyet · timeout</span>';
        if (r === 'galibiyet_disconnect') return '<span class="badge bg-success">Galibiyet · disconnect</span>';
        if (r === 'galibiyet_leave') return '<span class="badge bg-success">Galibiyet · leave</span>';
        if (r === 'mağlubiyet') return '<span class="badge bg-danger">Mağlubiyet</span>';
        if (r === 'maglubiyet_timeout') return '<span class="badge bg-danger">Mağlubiyet · timeout</span>';
        if (r === 'maglubiyet_disconnect') return '<span class="badge bg-danger">Mağlubiyet · disconnect</span>';
        if (r === 'maglubiyet_leave') return '<span class="badge bg-danger">Mağlubiyet · leave</span>';
        if (r === 'galibiyet_afk') return '<span class="badge bg-success">Galibiyet · AFK</span>';
        if (r === 'maglubiyet_afk') return '<span class="badge bg-danger">Mağlubiyet · AFK</span>';
        if (r === 'afk') return '<span class="badge bg-secondary">AFK</span>';
        if (r === 'timeout') return '<span class="badge bg-secondary">Timeout</span>';
        if (r === 'disconnect') return '<span class="badge bg-secondary">Disconnect</span>';
        if (r === 'leave') return '<span class="badge bg-secondary">Leave</span>';
        if (r === 'iptal') return '<span class="badge bg-secondary">İptal</span>';
        if (r === 'berabere') return '<span class="badge bg-secondary">İptal</span>';
        return '<span class="badge bg-warning text-dark">Devam</span>';
    }

    function openBotHistory(botId, botName) {
        var modalEl = document.getElementById('duelBotHistoryModal');
        var title = document.getElementById('duelBotHistoryTitle');
        var meta = document.getElementById('duelBotHistoryMeta');
        var body = document.getElementById('duelBotHistoryBody');
        if (!modalEl || !body) return;

        if (title) title.textContent = 'Düello geçmişi · #' + botId + ' ' + (botName || '');
        if (meta) meta.textContent = 'Yükleniyor…';
        body.innerHTML = '<tr><td colspan="12" class="text-muted">Yükleniyor…</td></tr>';

        var modal = window.bootstrap && bootstrap.Modal
            ? bootstrap.Modal.getOrCreateInstance(modalEl)
            : null;
        if (modal) modal.show();
        else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }

        fetch(duelsUrlTpl + '/' + botId + '/duels?limit=50', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    body.innerHTML = '<tr><td colspan="12" class="text-danger">Yüklenemedi</td></tr>';
                    return;
                }
                if (meta) {
                    meta.textContent = (data.bot && data.bot.difficulty ? String(data.bot.difficulty).toUpperCase() + ' · ' : '')
                        + (data.duels || []).length + ' maç · jeton '
                        + Number((data.bot && data.bot.coins) || 0).toLocaleString('tr-TR');
                }
                var rows = data.duels || [];
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="12" class="text-muted">Henüz düello yok</td></tr>';
                    return;
                }
                body.innerHTML = rows.map(function (d) {
                    var net = d.coins_net;
                    var netCls = net > 0 ? 'text-success' : (net < 0 ? 'text-danger' : '');
                    var netTxt = net == null ? '—' : ((net > 0 ? '+' : '') + net);
                    var bal = (d.coins_before != null && d.coins_after != null)
                        ? (d.coins_before + ' → ' + d.coins_after)
                        : '—';
                    var opp = d.opponent_name ? ('#' + d.opponent_id + ' ' + d.opponent_name) : '—';
                    return '<tr class="js-duel-row" style="cursor:pointer" data-bot-id="' + botId
                        + '" data-duel-id="' + d.duel_id + '" title="Soru detayı">'
                        + '<td>#' + d.duel_id + '</td>'
                        + '<td class="small">' + esc(d.finished_at || d.started_at || d.created_at || '') + '</td>'
                        + '<td>' + esc(opp) + '</td>'
                        + '<td>' + esc(d.multiplier || 'x1') + '</td>'
                        + '<td>' + resultBadge(d.result) + '</td>'
                        + '<td class="text-success">' + d.correct + '</td>'
                        + '<td class="text-danger">' + d.wrong + '</td>'
                        + '<td>' + (d.accuracy_pct != null ? ('%' + d.accuracy_pct) : '—') + '</td>'
                        + '<td class="text-success">+' + d.coins_gained + '</td>'
                        + '<td class="text-danger">−' + d.coins_lost + '</td>'
                        + '<td class="' + netCls + '">' + netTxt + '</td>'
                        + '<td class="small">' + bal + '</td>'
                        + '</tr>';
                }).join('');
            })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="12" class="text-danger">Bağlantı hatası</td></tr>';
            });
    }

    function ansCell(a) {
        if (!a) return '<span class="text-muted">—</span>';
        var ok = !!a.is_correct;
        var cls = ok ? 'text-success' : 'text-danger';
        var mark = ok ? '✓' : '✗';
        var coin = a.coins_change != null ? (' · ' + (a.coins_change > 0 ? '+' : '') + a.coins_change) : '';
        return '<span class="' + cls + '">' + mark + ' şık ' + esc(String(a.selected || '')) + coin + '</span>';
    }

    function openDuelDetail(botId, duelId) {
        var modalEl = document.getElementById('duelBotDetailModal');
        var title = document.getElementById('duelBotDetailTitle');
        var meta = document.getElementById('duelBotDetailMeta');
        var body = document.getElementById('duelBotDetailBody');
        if (!modalEl || !body) return;
        if (title) title.textContent = 'Soru detayı · düello #' + duelId;
        if (meta) meta.textContent = 'Yükleniyor…';
        body.innerHTML = '<tr><td colspan="5" class="text-muted">Yükleniyor…</td></tr>';
        var modal = window.bootstrap && bootstrap.Modal
            ? bootstrap.Modal.getOrCreateInstance(modalEl)
            : null;
        if (modal) modal.show();

        fetch(duelsUrlTpl + '/' + botId + '/duels/' + duelId, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success || !data.detail) {
                    body.innerHTML = '<tr><td colspan="5" class="text-danger">Yüklenemedi</td></tr>';
                    return;
                }
                var d = data.detail;
                if (meta) {
                    meta.textContent = (d.opponent_name ? ('Rakip #' + d.opponent_id + ' ' + d.opponent_name + ' · ') : '')
                        + (d.multiplier || 'x1')
                        + (d.finished_at ? (' · ' + d.finished_at) : '')
                        + (d.forfeit_reason ? (' · ' + d.forfeit_reason) : '');
                }
                var qs = d.questions || [];
                if (!qs.length) {
                    body.innerHTML = '<tr><td colspan="5" class="text-muted">Cevap yok</td></tr>';
                    return;
                }
                body.innerHTML = qs.map(function (q) {
                    return '<tr>'
                        + '<td>Q' + q.n + '</td>'
                        + '<td class="small" style="max-width:360px">' + esc(q.question || '') + '</td>'
                        + '<td>' + esc(String(q.correct_answer || '')) + '</td>'
                        + '<td>' + ansCell(q.bot) + '</td>'
                        + '<td>' + ansCell(q.human) + '</td>'
                        + '</tr>';
                }).join('');
            })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="5" class="text-danger">Bağlantı hatası</td></tr>';
            });
    }

    document.addEventListener('click', function (e) {
        var row = e.target.closest('.js-duel-row');
        if (row) {
            e.preventDefault();
            openDuelDetail(row.getAttribute('data-bot-id'), row.getAttribute('data-duel-id'));
            return;
        }
        var btn = e.target.closest('.js-bot-duels');
        if (!btn) return;
        e.preventDefault();
        openBotHistory(btn.getAttribute('data-bot-id'), btn.getAttribute('data-bot-name'));
    });
})();
</script>
@endpush
