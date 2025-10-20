<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingAbout extends Model
{
    use HasFactory;

    protected $table = 'landing_abouts';

    protected $fillable = [
        'img',
        'title',
        'description',
    ];
}


