<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinHistory extends Model
{
    protected $table = 'coin_history';

    protected $fillable = [
        'user_id',
        'coin_amount',
        'transaction_type',
        'status',
        'description',
        'metadata',
        'balance_before',
        'balance_after'
    ];

    protected $casts = [
        'user_id' => 'integer',
        'coin_amount' => 'integer',
        'transaction_type' => 'string',
        'status' => 'string',
        'metadata' => 'array',
        'balance_before' => 'integer',
        'balance_after' => 'integer'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeEarned($query)
    {
        return $query->where('coin_amount', '>', 0);
    }

    public function scopeSpent($query)
    {
        return $query->where('coin_amount', '<', 0);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
