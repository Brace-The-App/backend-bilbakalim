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
        'send_at',
        'is_active',
        'created_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'send_at' => 'datetime',
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
            'info' => 'primary',
            'success' => 'success',
            'warning' => 'warning',
            'error' => 'danger',
            default => 'secondary'
        };
    }

    // Tip ikonları
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'info' => 'info',
            'success' => 'check-circle',
            'warning' => 'alert-triangle',
            'error' => 'x-circle',
            default => 'bell'
        };
    }

    // Kısa içerik
    public function getShortContentAttribute(): string
    {
        return \Str::limit($this->content, 100);
    }
}