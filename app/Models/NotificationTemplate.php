<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'name',
        'channel',
        'title',
        'content',
        'source',
        'preset_key',
        'created_by',
        'is_active',
        'target_users',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'target_users' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(NotificationSchedule::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'notification_template_id');
    }

    public function getChannelLabelAttribute(): string
    {
        return match ($this->channel) {
            'email' => 'E-posta',
            'sms' => 'SMS',
            'fcm' => 'Push (FCM)',
            default => ucfirst((string) $this->channel),
        };
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            'preset' => 'JSON şablon',
            'admin' => 'Admin oluşturdu',
            default => $this->source,
        };
    }
}
