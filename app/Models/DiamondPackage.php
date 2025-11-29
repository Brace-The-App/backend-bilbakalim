<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiamondPackage extends Model
{
    protected $fillable = [
        'name',
        'diamond_amount',
        'price',
        'gross_price',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'diamond_amount' => 'integer',
        'price' => 'decimal:2',
        'gross_price' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Aktif paketleri getir
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Sıralı paketleri getir
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
