<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceLedgerEntry extends Model
{
    public const DIRECTION_INCOME = 'income';
    public const DIRECTION_EXPENSE = 'expense';

    public const SOURCE_IAP = 'iap_sale'; // nadiren snapshot; genelde payments canlı
    public const SOURCE_AD_REVENUE = 'ad_revenue';
    public const SOURCE_OTHER_INCOME = 'other_income';
    public const SOURCE_GIFT = 'gift_payout';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_KDV = 'kdv';

    public const PAYOUT_METHODS = [
        'multinet' => 'Multinet',
        'papara' => 'Papara',
        'havale' => 'Havale',
        'parsela' => 'Parsela',
        'other' => 'Diğer',
    ];

    protected $fillable = [
        'direction',
        'source',
        'category_id',
        'entry_date',
        'amount_try',
        'currency',
        'label',
        'note',
        'payout_method',
        'reference_type',
        'reference_id',
        'meta',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount_try' => 'float',
        'category_id' => 'integer',
        'reference_id' => 'integer',
        'meta' => 'array',
        'created_by' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceExpenseCategory::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payoutMethodLabel(): string
    {
        return self::PAYOUT_METHODS[$this->payout_method] ?? ($this->payout_method ?: '—');
    }
}
