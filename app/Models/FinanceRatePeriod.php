<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceRatePeriod extends Model
{
    protected $fillable = [
        'effective_from',
        'effective_to',
        'store_fee_pct',
        'income_tax_pct',
        'kdv_pct',
        'gift_payout_try',
        'coin_to_try',
        'ad_click_floor_try',
        'note',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'store_fee_pct' => 'float',
        'income_tax_pct' => 'float',
        'kdv_pct' => 'float',
        'gift_payout_try' => 'float',
        'coin_to_try' => 'float',
        'ad_click_floor_try' => 'float',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function covers(\DateTimeInterface|string $date): bool
    {
        $d = \Carbon\Carbon::parse($date)->startOfDay();
        if ($d->lt($this->effective_from->copy()->startOfDay())) {
            return false;
        }
        if ($this->effective_to && $d->gt($this->effective_to->copy()->endOfDay())) {
            return false;
        }

        return true;
    }
}
