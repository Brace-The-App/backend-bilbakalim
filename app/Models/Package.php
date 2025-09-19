<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'price',
        'features',
        'time_limit_days',
        'question_limit',
        'tournament_limit',
        'ads_free',
        'is_active',
        'color_code',
        'sort_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'time_limit_days' => 'integer',
        'question_limit' => 'integer',
        'tournament_limit' => 'integer',
        'ads_free' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeFree($query)
    {
        return $query->where('price', 0);
    }

    public function scopePremium($query)
    {
        return $query->where('price', '>', 0);
    }
}
