<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceMonthLock extends Model
{
    protected $fillable = [
        'year',
        'month',
        'locked_by',
        'note',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'locked_by' => 'integer',
    ];

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function label(): string
    {
        return sprintf('%02d.%04d', $this->month, $this->year);
    }
}
