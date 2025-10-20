<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LandingTestimonial extends Model
{
    use HasFactory;

    protected $table = 'landing_testimonials';

    protected $fillable = [
        'profile_img',
        'user_name',
        'comment',
    ];
}


