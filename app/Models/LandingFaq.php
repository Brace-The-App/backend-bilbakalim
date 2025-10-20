<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingFaq extends Model
{
    use HasFactory;

    protected $table = 'landing_faqs';

    protected $fillable = [
        'question',
        'answer',
    ];
}


