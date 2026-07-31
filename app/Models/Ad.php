<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'title',
        'image_path',
        'link',
        'video_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->publicUrlFor($this->image_path);
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->publicUrlFor($this->video_path);
    }

    private function publicUrlFor(?string $path): ?string
    {
        if (!$path || $path === '0') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset('storage/' . $path);
    }
}
