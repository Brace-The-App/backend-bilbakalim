<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Abbasudo\Purity\Traits\Filterable;
use Abbasudo\Purity\Traits\Sortable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, HasPermissions;
    use ModelTrait;
    use Filterable;
    use Sortable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // Account ID kontrolü kaldırıldı
    protected $fillable = [
        'name',
        'surname',
        'phone',
        'email',
        'password',
        'status',
        'role',
        'profile_image',
        'auto_question',
        'game_sound',
        'face_id',
        'fingerprint',
        'package_id',
        'role_id',
        'total_coins',
        'last_login_at',
        'device_token',
        'device_id',
        'account_status'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'auto_question' => 'boolean',
        'game_sound' => 'boolean',
        'face_id' => 'boolean',
        'fingerprint' => 'boolean',
        'package_id' => 'integer',
        'role_id' => 'integer',
        'total_coins' => 'integer',
        'last_login_at' => 'datetime',
        'account_status' => 'string'
    ];

    // Account relationship kaldırıldı

    // Quiz App Relationships
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function answers()
    {
        return $this->hasMany(Answer::class);
    }

    public function awards()
    {
        return $this->hasMany(Award::class);
    }

    public function coinHistory()
    {
        return $this->hasMany(CoinHistory::class);
    }

    public function tournaments()
    {
        return $this->belongsToMany(Tournament::class, 'tournament_users')
                    ->withPivot(['joker_hakki', 'score', 'correct_answers', 'wrong_answers', 'total_time_seconds', 'status', 'joined_at', 'finished_at', 'answers_detail', 'rank'])
                    ->withTimestamps();
    }

    public function tournamentUsers()
    {
        return $this->hasMany(TournamentUser::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('account_status', 'active');
    }

    public function scopeWithPackage($query)
    {
        return $query->whereNotNull('package_id');
    }

    // Accessors
    public function getCorrectAnswersCountAttribute()
    {
        return $this->answers()->where('is_correct', true)->count();
    }

    public function getTotalAnswersCountAttribute()
    {
        return $this->answers()->count();
    }

    public function getAccuracyRateAttribute()
    {
        $total = $this->total_answers_count;
        return $total > 0 ? round(($this->correct_answers_count / $total) * 100, 2) : 0;
    }

    /**
     * Get the FCM token for the user.
     */
    public function routeNotificationForFcm()
    {
        return $this->device_token;
    }

    // Account relationships kaldırıldı
}
