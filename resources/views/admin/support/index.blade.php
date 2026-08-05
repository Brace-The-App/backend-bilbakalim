@extends('admin.layouts.app')

@section('title', 'Destek')

@push('styles')
<style>
.sup-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #334155 100%);
    border-radius: 14px; color: #fff; padding: 1.25rem 1.5rem; margin-bottom: 1rem;
}
.sup-hero h3 { color: #fff !important; margin: 0 0 .35rem; font-weight: 650; }
.sup-hero p { margin: 0; color: rgba(255,255,255,.8); }
.sup-kpi { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: .65rem; margin-bottom: 1rem; }
@media (max-width: 992px) { .sup-kpi { grid-template-columns: repeat(3, minmax(0,1fr)); } }
@media (max-width: 576px) { .sup-kpi { grid-template-columns: repeat(2, minmax(0,1fr)); } }
.sup-kpi a, .sup-kpi .box {
    display: block; text-decoration: none; color: inherit;
    background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem;
}
.sup-kpi a.is-on { outline: 2px solid #0f172a; }
.sup-kpi .k { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: #64748b; }
.sup-kpi .v { font-size: 1.45rem; font-weight: 700; margin-top: .15rem; font-variant-numeric: tabular-nums; }
.sup-msg { white-space: pre-wrap; word-break: break-word; max-width: 420px; line-height: 1.4; }
.sup-row-new { background: #fef2f2 !important; font-weight: 650; }
.sup-row-new td { border-color: #fecaca !important; }
.sup-row-later { background: #fff7ed !important; }
.sup-row-later td { border-color: #fed7aa !important; }
.sup-row-archived { opacity: .72; }
</style>
@endpush

@section('content')
<div class="page-title" style="margin-top:1rem">
    <div class="sup-hero">
        <h3>Destek kutusu</h3>
        <p>Landing, uygulama ve web oyuncudan gelen iletişim / şikayet / öneri / iş talepleri.</p>
    </div>
</div>

@php
    $base = array_filter([
        'q' => $q !== '' ? $q : null,
        'source' => $source !== '' ? $source : null,
        'type' => $type !== '' ? $type : null,
    ], fn ($v) => $v !== null);
@endphp

<div class="sup-kpi">
    <a href="{{ route('admin.support.index', $base) }}" class="{{ $status === '' ? 'is-on' : '' }}">
        <div class="k">Tümü</div><div class="v">{{ $counts['all'] }}</div>
    </a>
    <a href="{{ route('admin.support.index', array_merge($base, ['status' => 'new'])) }}" class="{{ $status === 'new' ? 'is-on' : '' }}">
        <div class="k">Yeni</div><div class="v" style="color:#dc2626">{{ $counts['new'] }}</div>
    </a>
    <a href="{{ route('admin.support.index', array_merge($base, ['status' => 'later'])) }}" class="{{ $status === 'later' ? 'is-on' : '' }}">
        <div class="k">Sonra bak</div><div class="v" style="color:#ea580c">{{ $counts['later'] ?? 0 }}</div>
    </a>
    <a href="{{ route('admin.support.index', array_merge($base, ['status' => 'read'])) }}" class="{{ $status === 'read' ? 'is-on' : '' }}">
        <div class="k">Okundu</div><div class="v">{{ $counts['read'] }}</div>
    </a>
    <a href="{{ route('admin.support.index', array_merge($base, ['status' => 'archived'])) }}" class="{{ $status === 'archived' ? 'is-on' : '' }}">
        <div class="k">Arşiv</div><div class="v" style="color:#64748b">{{ $counts['archived'] }}</div>
    </a>
</div>

<div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Ara</label>
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="isim, e-posta, mesaj…">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Kaynak</label>
                <select name="source" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    <option value="landing" @selected($source === 'landing')>Landing</option>
                    <option value="app" @selected($source === 'app')>Uygulama</option>
                    <option value="web_player" @selected($source === 'web_player')>Web oyuncu</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Tür</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Tümü</option>
                    @foreach(\App\Models\SupportMessage::TYPES as $key => $label)
                        <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if($status !== '')
                <input type="hidden" name="status" value="{{ $status }}">
            @endif
            <div class="col-md-2">
                <button class="btn btn-sm btn-primary w-100" type="submit">Filtrele</button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('admin.support.index') }}" class="btn btn-sm btn-outline-secondary w-100">Temizle</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:12px">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Zaman</th>
                        <th>Kaynak / Tür</th>
                        <th>Kimden</th>
                        <th>Mesaj</th>
                        <th>Durum</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($messages as $m)
                        @php
                            $rowClass = match ($m->status) {
                                'new' => 'sup-row-new',
                                'later' => 'sup-row-later',
                                'archived' => 'sup-row-archived',
                                default => '',
                            };
                            $badge = match ($m->status) {
                                'new' => 'danger',
                                'later' => 'warning text-dark',
                                'read' => 'success',
                                'archived' => 'secondary',
                                default => 'light',
                            };
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td>{{ $m->id }}</td>
                            <td class="small text-nowrap">{{ tr_time($m->created_at, 'd.m H:i') }}</td>
                            <td>
                                <div class="small">{{ $m->sourceLabel() }}</div>
                                <span class="badge bg-secondary">{{ $m->typeLabel() }}</span>
                            </td>
                            <td>
                                <div>{{ $m->name ?: '—' }}</div>
                                <div class="small text-muted">{{ $m->email ?: '—' }}</div>
                                @if($m->user_id)
                                    <div class="small">Oyuncu #{{ $m->user_id }}
                                        @if($m->user) · {{ $m->user->name }} @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($m->subject)
                                    <div class="small fw-semibold">{{ $m->subject }}</div>
                                @endif
                                <div class="sup-msg small">{{ \Illuminate\Support\Str::limit($m->message, 160) }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $badge }}">{{ $m->statusLabel() }}</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.support.show', $m->id) }}" class="btn btn-sm btn-outline-dark">Aç</a>
                                    <form method="post" action="{{ route('admin.support.destroy', $m->id) }}"
                                          onsubmit="return confirm('Bu mesaj silinsin mi?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">Henüz mesaj yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($messages->hasPages())
        <div class="card-footer">{{ $messages->links() }}</div>
    @endif
</div>
@endsection
