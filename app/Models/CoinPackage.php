<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoinPackage extends Model
{
    protected $fillable = [
        'name',
        'description',
        'coin_amount',
        'price',
        'currency',
        'bonus_coins',
        'is_popular',
        'is_active',
        'color_code',
        'icon',
        'sort_order'
    ];

    protected $casts = [
        'coin_amount' => 'integer',
        'price' => 'decimal:2',
        'bonus_coins' => 'integer',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    // Relationships
    public function coinPurchases(): HasMany
    {
        return $this->hasMany(CoinPurchase::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    // Accessors
    public function getTotalCoinsAttribute()
    {
        return $this->coin_amount + $this->bonus_coins;
    }

    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2) . ' ' . $this->currency;
    }

    public function getCoinPerLiraAttribute()
    {
        return $this->price > 0 ? round($this->total_coins / $this->price, 2) : 0;
    }

    public function getIsPopularAttribute()
    {
        return $this->is_popular;
    }

    public function getIsActiveAttribute()
    {
        return $this->is_active;
    }
}