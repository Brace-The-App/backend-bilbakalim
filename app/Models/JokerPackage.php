<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JokerPackage extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'price',
        'currency',
        'coin_amount',
        'fifty_fifty_jokers',
        'double_answer_jokers',
        'hint_jokers',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'coin_amount' => 'integer',
        'fifty_fifty_jokers' => 'integer',
        'double_answer_jokers' => 'integer',
        'hint_jokers' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
