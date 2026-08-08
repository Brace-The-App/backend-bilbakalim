<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
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
        'email_replied_at',
        'last_email_reply',
        'last_email_from',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'read_at' => 'datetime',
        'email_replied_at' => 'datetime',
    ];

    /** Talebin veya bağlı hesabın e-posta adresi */
    public function recipientEmail(): ?string
    {
        $ticketEmail = trim((string) ($this->email ?? ''));
        if ($ticketEmail !== '' && filter_var($ticketEmail, FILTER_VALIDATE_EMAIL)) {
            return $ticketEmail;
        }

        $userEmail = trim((string) ($this->user?->email ?? ''));
        if ($userEmail !== '' && filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return $userEmail;
        }

        return null;
    }

    /** `view support` izni olan panel kullanıcıları. */
    public static function canAccess(?User $user): bool
    {
        return $user !== null && $user->can('view support');
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
