<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingNews extends Model
{
    use HasFactory;

    protected $table = 'landing_news';

    protected $fillable = [
        'title',
        'img',
        'description',
    ];
}


