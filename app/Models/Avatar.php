<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Avatar extends Model
{
    protected $fillable = [
        'image_path',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'avatar');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Accessor for full image URL
    public function getImageUrlAttribute()
    {
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return $this->image_path;
        }
        return asset('storage/' . $this->image_path);
    }

    public function getImageExistsAttribute(): bool
    {
        if (filter_var($this->image_path, FILTER_VALIDATE_URL)) {
            return true;
        }

        if (! filled($this->image_path)) {
            return false;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->exists($this->image_path);
    }
}
