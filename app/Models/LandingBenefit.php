<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingBenefit extends Model
{
    use HasFactory;

    protected $table = 'landing_benefits';

    protected $fillable = [
        'title',
        'description',
    ];
}


