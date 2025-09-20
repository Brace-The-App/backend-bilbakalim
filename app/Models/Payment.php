<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'payment_id',
        'payment_method',
        'payment_provider',
        'amount',
        'currency',
        'status',
        'transaction_id',
        'payment_data',
        'metadata',
        'paid_at',
        'refunded_at',
        'failure_reason'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'decimal:2',
        'payment_data' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coinPurchases(): HasMany
    {
        return $this->hasMany(CoinPurchase::class);
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

    public function scopeByProvider($query, $provider)
    {
        return $query->where('payment_provider', $provider);
    }

    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    // Accessors
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

    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }
}