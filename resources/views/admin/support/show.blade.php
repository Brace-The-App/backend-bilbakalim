@extends('admin.layouts.app')

@section('title', 'Destek #' . $message->id)

@section('content')
<div class="page-title" style="margin-top:1rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">Mesaj #{{ $message->id }}</h3>
            <div class="text-muted small">
                {{ tr_time($message->created_at) }}
                · {{ $message->sourceLabel() }}
                · {{ $message->typeLabel() }}
            </div>
        </div>
        <a href="{{ route('admin.support.index') }}" class="btn btn-sm btn-outline-secondary">Listeye dön</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-body">
                @if($message->subject)
                    <h5 class="mb-3">{{ $message->subject }}</h5>
                @endif
                <div style="white-space:pre-wrap;word-break:break-word;line-height:1.55;font-size:1.05rem">{{ $message->message }}</div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
            <div class="card-body">
                <div class="small text-muted text-uppercase mb-2">Gönderen</div>
                <div class="fw-semibold">{{ $message->name ?: '—' }}</div>
                <div class="small">{{ $message->email ?: '—' }}</div>
                <div class="small">{{ $message->phone ?: '—' }}</div>
                @if($message->user_id)
                    <hr>
                    <div class="small text-muted">Oyuncu hesabı</div>
                    <div>#{{ $message->user_id }}
                        @if($message->user)
                            · {{ $message->user->name }}
                            <div class="small text-muted">{{ $message->user->email }}</div>
                        @endif
                    </div>
                @endif
                <hr>
                <div class="small text-muted">Platform: {{ $message->platform ?: '—' }}</div>
                <div class="small text-muted text-truncate" title="{{ $message->user_agent }}">UA: {{ $message->user_agent ?: '—' }}</div>
                <div class="small text-muted">IP: {{ $message->ip_address ?: '—' }}</div>
                <div class="small text-muted">Durum: {{ $message->statusLabel() }}
                    @if($message->read_at) · okundu {{ tr_time($message->read_at) }} @endif
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-body">
                <form method="post" action="{{ route('admin.support.status', $message->id) }}">
                    @csrf
                    <label class="form-label">Durum</label>
                    <select name="status" class="form-select mb-2">
                        @foreach(\App\Models\SupportMessage::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected($message->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-text mb-2">
                        Mesaj açılınca <strong>Yeni</strong> otomatik <strong>Okundu</strong> olur.
                        Sonra bakmak için <strong>Sonra bak</strong> seç.
                    </div>
                    <label class="form-label">Admin notu</label>
                    <textarea name="admin_note" class="form-control mb-2" rows="3" maxlength="2000">{{ $message->admin_note }}</textarea>
                    <button type="submit" class="btn btn-primary w-100">Kaydet</button>
                </form>
                <form method="post" action="{{ route('admin.support.destroy', $message->id) }}" class="mt-2"
                      onsubmit="return confirm('Bu mesaj silinsin mi?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger w-100">Sil</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
