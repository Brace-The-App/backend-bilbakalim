<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'title',
        'content',
        'type',
        'target_users',
        'send_at',
        'is_active',
        'sent_count',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'send_at' => 'datetime',
        'target_users' => 'array',
        'sent_count' => 'integer',
    ];

    // Bildirimi oluşturan kullanıcı
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Bildirimi alan kullanıcılar
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notification_user')
                    ->withPivot('read_at')
                    ->withTimestamps();
    }

    // Tip renkleri
    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'email' => 'info',
            'sms' => 'warning',
            'fcm' => 'dark',
            default => 'secondary'
        };
    }

    // Tip ikonları
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'email' => 'mail',
            'sms' => 'message-circle',
            'fcm' => 'smartphone',
            default => 'bell'
        };
    }

    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'email' => 'E-posta',
            'sms' => 'SMS',
            'fcm' => 'Push (FCM)',
            default => ucfirst($this->type),
        };
    }

    public function getIsSentAttribute(): bool
    {
        if (($this->sent_count ?? 0) > 0) {
            return true;
        }

        return $this->send_at !== null && $this->send_at->isPast();
    }

    // Kısa içerik
    public function getShortContentAttribute(): string
    {
        return \Str::limit($this->content, 100);
    }
}