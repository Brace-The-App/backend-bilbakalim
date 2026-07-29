<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAdView extends Model
{
    protected $fillable = [
        'user_id',
        'view_count',
        'last_viewed_at',
        'window_started_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'view_count' => 'integer',
        'last_viewed_at' => 'datetime',
        'window_started_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
