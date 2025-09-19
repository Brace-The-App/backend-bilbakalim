<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Award extends Model
{
    protected $fillable = [
        'brand_name',
        'user_id',
        'award_value',
        'award_type',
        'status',
        'description',
        'image',
        'valid_until',
        'delivery_info'
    ];

    protected $casts = [
        'award_value' => 'decimal:2',
        'award_type' => 'string',
        'status' => 'string',
        'valid_until' => 'date',
        'delivery_info' => 'array',
        'user_id' => 'integer'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('award_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('valid_until', '>=', now())
                    ->orWhereNull('valid_until');
    }
}
