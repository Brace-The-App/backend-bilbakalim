<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSchedule extends Model
{
    protected $fillable = [
        'notification_template_id',
        'schedule_type',
        'send_at',
        'send_time',
        'target_users',
        'is_active',
        'last_sent_at',
        'created_by',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'target_users' => 'array',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getScheduleLabelAttribute(): string
    {
        $tz = \App\Services\NotificationFlowHelper::timezone();

        if ($this->schedule_type === 'once' && $this->send_at) {
            return $this->send_at->timezone($tz)->format('d.m.Y H:i') . ' (tek sefer)';
        }

        if ($this->schedule_type === 'daily' && $this->send_time) {
            $time = is_string($this->send_time)
                ? substr($this->send_time, 0, 5)
                : $this->send_time->format('H:i');

            return 'Her gün ' . $time . ' (TR)';
        }

        return '—';
    }
}
