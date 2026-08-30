@php $tz = $timezone ?? \App\Services\NotificationFlowHelper::timezone(); @endphp
@forelse($schedules as $sch)
    @php
        if (! $sch->is_active) {
            $status = 'paused';
            $statusLabel = 'Duraklatıldı';
            $statusClass = 'nlf-status-paused';
        } elseif ($sch->last_sent_at) {
            $status = 'sent';
            $statusLabel = 'Gönderildi';
            $statusClass = 'nlf-status-sent';
        } else {
            $status = 'pending';
            $statusLabel = 'Bekliyor';
            $statusClass = 'nlf-status-pending';
        }
    @endphp
    <div class="nlf-sched-item" data-schedule-id="{{ $sch->id }}" data-template-id="{{ $sch->notification_template_id }}">
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <strong style="font-size:.88rem">{{ $sch->template?->name ?? '—' }}</strong>
                <span class="nlf-status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            <div class="time">{{ $sch->schedule_label }}</div>
            @if($sch->last_sent_at)
                <div class="time">Son gönderim: {{ $sch->last_sent_at->timezone($tz)->format('d.m.Y H:i') }}</div>
            @else
                <div class="time text-muted">Henüz gönderilmedi</div>
            @endif
        </div>
        <div class="btn-group btn-group-sm flex-shrink-0">
            <button type="button" class="btn btn-outline-secondary nlf-toggle-schedule" data-id="{{ $sch->id }}" title="{{ $sch->is_active ? 'Duraklat' : 'Aktifleştir' }}">{{ $sch->is_active ? '⏸' : '▶' }}</button>
            <button type="button" class="btn btn-outline-danger nlf-delete-schedule" data-id="{{ $sch->id }}">×</button>
        </div>
    </div>
@empty
    <div class="nlf-empty">{{ !empty($emptyMessage) ? $emptyMessage : 'Zamanlama yok' }}</div>
@endforelse
