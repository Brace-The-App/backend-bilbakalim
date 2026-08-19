@forelse($notifications as $notification)
<tr>
    <td>
        <div class="notif-title-cell fw-semibold">{{ $notification->title }}</div>
    </td>
    <td>
        <div class="notif-content-cell" title="{{ $notification->content }}">{{ $notification->content }}</div>
    </td>
    <td>
        <span class="notif-type-pill notif-type-{{ $notification->type }}">
            <i data-feather="{{ $notification->type_icon }}"></i>
            {{ $notification->type_label }}
        </span>
    </td>
    <td>
        @if($notification->is_sent)
            <span class="badge notif-status-sent">Gönderildi</span>
        @elseif($notification->is_active)
            <span class="badge notif-status-active">Aktif</span>
        @else
            <span class="badge notif-status-inactive">Pasif</span>
        @endif
    </td>
    <td class="d-none d-lg-table-cell">
        <div class="notif-creator">
            <span class="notif-creator-avatar">{{ strtoupper(substr($notification->creator->name ?? '?', 0, 1)) }}</span>
            <span>{{ $notification->creator->name ?? '—' }}</span>
        </div>
    </td>
    <td>
        @if($notification->send_at)
            <div class="notif-date-main">{{ $notification->send_at->format('d.m.Y H:i') }}</div>
            <div class="notif-date-sub">{{ $notification->send_at->diffForHumans() }}</div>
            @if(($notification->sent_count ?? 0) > 0)
                <div class="mt-1"><span class="notif-sent-count-badge">{{ number_format($notification->sent_count, 0, ',', '.') }} kişi</span></div>
            @endif
        @else
            <div class="notif-date-main text-muted">Hemen</div>
        @endif
    </td>
    <td class="text-end">
        {{-- Masaüstü: doğrudan butonlar --}}
        <div class="notif-actions notif-actions-desktop d-none d-md-inline-flex">
            <button type="button" class="btn btn-sm btn-outline-primary notif-row-btn"
                    data-bs-toggle="modal" data-bs-target="#notificationShowModal"
                    data-id="{{ $notification->id }}"
                    data-title="{{ e($notification->title) }}"
                    data-content="{{ e($notification->content) }}"
                    data-type="{{ $notification->type }}"
                    data-type-label="{{ $notification->type_label }}"
                    data-send-at="{{ $notification->send_at?->toIso8601String() }}"
                    data-created-at="{{ $notification->created_at?->toIso8601String() }}"
                    data-is-sent="{{ $notification->is_sent ? 1 : 0 }}"
                    data-creator="{{ e($notification->creator->name ?? '—') }}"
                    data-sent-count="{{ $notification->sent_count ?? 0 }}"
                    title="Görüntüle">
                <i data-feather="eye"></i>
                <span class="d-none d-xl-inline ms-1">Görüntüle</span>
            </button>
            @can('delete notifications')
            <button type="button" class="btn btn-sm btn-outline-danger notif-row-btn notif-delete-btn"
                    data-id="{{ $notification->id }}"
                    data-title="{{ e($notification->title) }}"
                    title="Sil">
                <i data-feather="trash-2"></i>
                <span class="d-none d-xl-inline ms-1">Sil</span>
            </button>
            @endcan
        </div>

        {{-- Mobil: tek ⋮ menü --}}
        <div class="dropdown notif-actions notif-actions-mobile d-md-none">
            <button class="btn btn-sm btn-light border notif-action-btn" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="İşlemler">
                <span class="notif-dots" aria-hidden="true">&#8942;</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button type="button" class="dropdown-item"
                            data-bs-toggle="modal" data-bs-target="#notificationShowModal"
                            data-id="{{ $notification->id }}"
                            data-title="{{ e($notification->title) }}"
                            data-content="{{ e($notification->content) }}"
                            data-type="{{ $notification->type }}"
                            data-type-label="{{ $notification->type_label }}"
                            data-send-at="{{ $notification->send_at?->toIso8601String() }}"
                            data-created-at="{{ $notification->created_at?->toIso8601String() }}"
                            data-is-sent="{{ $notification->is_sent ? 1 : 0 }}"
                            data-creator="{{ e($notification->creator->name ?? '—') }}"
                            data-sent-count="{{ $notification->sent_count ?? 0 }}">
                        Görüntüle
                    </button>
                </li>
                @can('delete notifications')
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item text-danger notif-delete-btn"
                            data-id="{{ $notification->id }}"
                            data-title="{{ e($notification->title) }}">
                        Sil
                    </button>
                </li>
                @endcan
            </ul>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="7" class="p-0 border-0">
        <div class="notif-empty">
            <div class="notif-empty-icon"><i data-feather="bell-off"></i></div>
            <h5>Henüz bildirim yok</h5>
            <p class="text-muted mb-3">Filtreleri temizleyin veya yeni bir bildirim gönderin.</p>
            @can('create notifications')
                <button type="button" class="btn notif-send-btn" data-bs-toggle="modal" data-bs-target="#notificationSendModal">
                    <i data-feather="send" class="me-1"></i> Bildirim Gönder
                </button>
            @endcan
        </div>
    </td>
</tr>
@endforelse
