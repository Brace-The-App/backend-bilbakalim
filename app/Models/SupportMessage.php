<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    /** Şimdilik sadece bu panel kullanıcısı Destek menüsünü görür. */
    public const ALLOWED_VIEWER_USER_ID = 15;

    public const SOURCES = ['landing', 'app', 'web_player'];

    public const TYPES = [
        'contact' => 'İletişim',
        'complaint' => 'Şikayet',
        'suggestion' => 'Öneri',
        'job' => 'İş / işbirliği',
        'other' => 'Diğer',
    ];

    public const STATUSES = [
        'new' => 'Yeni',
        'read' => 'Okundu',
        'later' => 'Sonra bak',
        'archived' => 'Arşiv',
    ];

    protected $fillable = [
        'user_id',
        'source',
        'type',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'platform',
        'user_agent',
        'ip_address',
        'admin_note',
        'read_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'read_at' => 'datetime',
    ];

    public static function canAccess(?User $user): bool
    {
        return $user !== null && (int) $user->id === self::ALLOWED_VIEWER_USER_ID;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function sourceLabel(): string
    {
        return match ($this->source) {
            'landing' => 'Landing (web)',
            'app' => 'Uygulama',
            'web_player' => 'Web oyuncu',
            default => $this->source,
        };
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function scopeUnread($query)
    {
        return $query->where('status', 'new');
    }
}
