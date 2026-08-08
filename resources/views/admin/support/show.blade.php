@extends('admin.layouts.app')

@section('title', 'Destek #' . $message->id)

@section('content')
@php
    $recipientEmail = $message->recipientEmail();
    $selectedAccount = old('mail_account', $defaultAccount ?? 'destek');
    $senderName = trim((string) ($message->name ?: $message->user?->name ?: ''));
    $senderEmail = trim((string) ($message->email ?: $message->user?->email ?: ''));
    $initial = mb_strtoupper(mb_substr($senderName !== '' ? $senderName : ($senderEmail !== '' ? $senderEmail : '?'), 0, 1));
@endphp

<div class="page-title" style="margin-top:1rem">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">Destek talebi #{{ $message->id }}</h3>
            <div class="text-muted small">
                {{ tr_time($message->created_at) }}
                · {{ $message->sourceLabel() }}
                · {{ $message->typeLabel() }}
                · {{ $message->statusLabel() }}
            </div>
        </div>
        <a href="{{ route('admin.support.index') }}" class="btn btn-sm btn-outline-secondary">Listeye dön</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius:12px">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3 mb-3 pb-3 border-bottom">
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle text-white fw-bold"
                         style="width:48px;height:48px;background:#1a2744;font-size:1.1rem">
                        {{ $initial }}
                    </div>
                    <div class="min-w-0">
                        <div class="small text-muted text-uppercase mb-1">Bu kullanıcı yazdı</div>
                        <div class="fw-semibold fs-5 mb-0">{{ $senderName !== '' ? $senderName : 'İsimsiz' }}</div>
                        @if($senderEmail !== '')
                            <div class="text-muted">{{ $senderEmail }}</div>
                        @endif
                        @if($message->user_id)
                            <div class="small mt-1">
                                Oyuncu hesabı:
                                <a href="{{ route('admin.users.show', $message->user_id) }}">#{{ $message->user_id }}{{ $message->user?->name ? ' · '.$message->user->name : '' }}</a>
                            </div>
                        @else
                            <div class="small text-muted mt-1">Kayıtlı oyuncu hesabı bağlı değil (misafir / form)</div>
                        @endif
                    </div>
                </div>

                <div class="mb-2">
                    <div class="small text-muted text-uppercase mb-1">Konu</div>
                    <div class="fw-semibold">{{ $message->subject ?: 'Konu belirtilmemiş' }}</div>
                </div>

                <div class="mt-3">
                    <div class="small text-muted text-uppercase mb-2">Mesajı</div>
                    <div class="p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;white-space:pre-wrap;word-break:break-word;line-height:1.6;font-size:1.05rem;min-height:4rem">
                        @if(trim((string) $message->message) !== '')
                            {{ $message->message }}
                        @else
                            <span class="text-muted">Mesaj metni boş.</span>
                        @endif
                    </div>
                </div>

                @if($message->phone)
                    <div class="small text-muted mt-3">Telefon: {{ $message->phone }}</div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3" style="border-radius:12px">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h5 class="mb-0">E-posta ile cevap</h5>
                    @if($message->email_replied_at)
                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                            Son mail: {{ tr_time($message->email_replied_at) }}
                        </span>
                    @endif
                </div>

                @if(!$recipientEmail)
                    <div class="alert alert-warning mb-0">Bu talepte geçerli e-posta yok; mail gönderilemez.</div>
                @else
                    <form method="post" action="{{ route('admin.support.reply', $message->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Alıcı</label>
                            <input type="text" class="form-control" value="{{ $recipientEmail }}" readonly>
                            <div class="form-text">Talepteki e-posta; yoksa oyuncu hesabındaki adres kullanılır.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gönderen hesap</label>
                            <select name="mail_account" class="form-select" required>
                                @foreach(($mailAccounts ?? []) as $acc)
                                    <option value="{{ $acc['id'] }}" @selected($selectedAccount === $acc['id'])>
                                        {{ $acc['label'] }} — {{ $acc['from_address'] }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Şimdilik tek hesap; ileride birden fazla eklenebilir.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mesaj</label>
                            <textarea name="reply_body" class="form-control @error('reply_body') is-invalid @enderror" rows="7" maxlength="5000" required placeholder="Kullanıcıya gidecek cevap…">{{ old('reply_body') }}</textarea>
                            @error('reply_body')
                                <div class="invalid-feedback">{{ $errors->first('reply_body') }}</div>
                            @enderror
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="mark_read" value="1" id="markRead" @checked(old('mark_read', true))>
                            <label class="form-check-label" for="markRead">Gönderince durumu Okundu yap</label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-1"></i> Mail gönder
                        </button>
                    </form>
                @endif

                @if($message->last_email_reply)
                    <hr class="my-4">
                    <div class="small text-muted text-uppercase mb-2">Son gönderilen cevap</div>
                    <div class="small text-muted mb-2">
                        @if($message->last_email_from) {{ $message->last_email_from }} · @endif
                        {{ $message->email_replied_at ? tr_time($message->email_replied_at) : '' }}
                    </div>
                    <div class="p-3 rounded-3 bg-light" style="white-space:pre-wrap;word-break:break-word">{{ $message->last_email_reply }}</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3" style="border-radius:12px">
            <div class="card-body">
                <div class="small text-muted text-uppercase mb-2">Talep bilgisi</div>
                <div class="small mb-1"><span class="text-muted">Kaynak:</span> {{ $message->sourceLabel() }}</div>
                <div class="small mb-1"><span class="text-muted">Tür:</span> {{ $message->typeLabel() }}</div>
                <div class="small mb-1"><span class="text-muted">Platform:</span> {{ $message->platform ?: '—' }}</div>
                <div class="small mb-1 text-truncate" title="{{ $message->user_agent }}"><span class="text-muted">UA:</span> {{ $message->user_agent ?: '—' }}</div>
                <div class="small mb-1"><span class="text-muted">IP:</span> {{ $message->ip_address ?: '—' }}</div>
                <div class="small"><span class="text-muted">Durum:</span> {{ $message->statusLabel() }}
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
