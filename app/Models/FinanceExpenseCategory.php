<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceExpenseCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(FinanceLedgerEntry::class, 'category_id');
    }
}
