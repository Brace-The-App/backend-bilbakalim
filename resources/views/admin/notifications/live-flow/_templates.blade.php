@forelse($templates as $tpl)
    <div class="nlf-template-item{{ (string) ($selectedId ?? '') === (string) $tpl->id ? ' is-selected' : '' }}"
         data-template-id="{{ $tpl->id }}">
        <div class="d-flex justify-content-between align-items-start gap-2">
            <div class="flex-grow-1 min-w-0">
                <div class="name text-truncate">{{ $tpl->name }}</div>
                <div class="meta">
                    <span class="nlf-badge {{ $tpl->source === 'preset' ? 'nlf-badge-preset' : 'nlf-badge-admin' }}">{{ $tpl->source_label }}</span>
                    <span>{{ $tpl->channel_label }}</span>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger nlf-delete-template py-0 px-1" data-id="{{ $tpl->id }}" title="Sil">&times;</button>
        </div>
    </div>
@empty
    <div class="nlf-empty">
        @if(!empty($search))
            Arama sonucu yok.
        @else
            Henüz kayıtlı şablon yok.<br><small>Otomatik getir, düzenle ve kaydet.</small>
        @endif
    </div>
@endforelse
