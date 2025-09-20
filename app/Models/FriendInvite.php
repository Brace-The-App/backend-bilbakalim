<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FriendInvite extends Model
{
    protected $fillable = [
        'inviter_id',
        'invited_id',
        'invite_code',
        'invite_link',
        'phone_number',
        'email',
        'status',
        'reward_coins',
        'bonus_coins',
        'accepted_at',
        'expires_at',
        'metadata'
    ];

    protected $casts = [
        'reward_coins' => 'integer',
        'bonus_coins' => 'integer',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array'
    ];

    // Relationships
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invited(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_id');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('invite_code', $code);
    }

    public function scopeByInviter($query, $userId)
    {
        return $query->where('inviter_id', $userId);
    }

    public function scopeByInvited($query, $userId)
    {
        return $query->where('invited_id', $userId);
    }

    // Accessors
    public function getIsExpiredAttribute()
    {
        return $this->expires_at < now();
    }

    public function getIsValidAttribute()
    {
        return $this->status === 'pending' && !$this->is_expired;
    }

    // Methods
    public static function generateInviteCode()
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('invite_code', $code)->exists());

        return $code;
    }

    public static function generateInviteLink($code)
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/invite/{$code}";
    }

    public function accept($userId = null)
    {
        if (!$this->is_valid) {
            return false;
        }

        $this->update([
            'status' => 'accepted',
            'invited_id' => $userId,
            'accepted_at' => now()
        ]);

        return true;
    }

    public function expire()
    {
        $this->update(['status' => 'expired']);
    }

    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }
}