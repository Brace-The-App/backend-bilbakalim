<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRequest extends Model
{
    protected $fillable = [
        'user_id',
        'reward_type',
        'coins_earned',
        'reward_date',
        'status',
        'requested_at',
        'approved_at',
        'approved_by',
        'metadata'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'coins_earned' => 'integer',
        'reward_date' => 'date',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'approved_by' => 'integer',
        'metadata' => 'array'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getGiftCardStoreIdAttribute(): ?int
    {
        $id = $this->metadata['gift_card_store_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }

    public function getGiftCardStoreImageUrlAttribute(): ?string
    {
        return $this->metadata['gift_card_store_image_url'] ?? null;
    }

    public function getGiftCardStoreTypeAttribute(): ?string
    {
        return $this->metadata['gift_card_store_type'] ?? null;
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('reward_type', $type);
    }
}
