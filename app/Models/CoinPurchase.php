<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'coin_package_id',
        'payment_id',
        'coin_amount',
        'bonus_coins',
        'price',
        'currency',
        'status',
        'completed_at',
        'refunded_at',
        'failure_reason'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'coin_package_id' => 'integer',
        'payment_id' => 'integer',
        'coin_amount' => 'integer',
        'bonus_coins' => 'integer',
        'price' => 'decimal:2',
        'completed_at' => 'datetime',
        'refunded_at' => 'datetime'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coinPackage(): BelongsTo
    {
        return $this->belongsTo(CoinPackage::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refunded');
    }

    // Accessors
    public function getTotalCoinsAttribute()
    {
        return $this->coin_amount + $this->bonus_coins;
    }

    public function getIsCompletedAttribute()
    {
        return $this->status === 'completed';
    }

    public function getIsPendingAttribute()
    {
        return $this->status === 'pending';
    }

    public function getIsFailedAttribute()
    {
        return $this->status === 'failed';
    }

    public function getIsRefundedAttribute()
    {
        return $this->status === 'refunded';
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }
}