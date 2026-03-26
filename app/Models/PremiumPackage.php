<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PremiumPackage extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'duration_days',
        'price',
        'currency',
        'gift_coins',
        'fifty_fifty_jokers',
        'double_answer_jokers',
        'hint_jokers',
        'is_best',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'price' => 'decimal:2',
        'gift_coins' => 'integer',
        'fifty_fifty_jokers' => 'integer',
        'double_answer_jokers' => 'integer',
        'hint_jokers' => 'integer',
        'is_best' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
