<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'title',
        'image_path',
        'link',
        'cta_text',
        'video_path',
        'is_active',
        'sort_order',
        'reward_coins',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'reward_coins' => 'integer',
    ];

    public const DEFAULT_REWARD_COINS = 1;
    public const DEFAULT_CTA_TEXT = 'Daha fazla bilgi al';
    public const MAX_VIDEO_SECONDS = 10;
    /** Video-only reklamlar için liste/DB placeholder (görsel yüklenmez). */
    public const VIDEO_PLACEHOLDER_PATH = 'ads/video-placeholder.png';

    public function isVideoAd(): bool
    {
        return !empty($this->video_path);
    }

    public function mediaType(): string
    {
        return $this->isVideoAd() ? 'video' : 'image';
    }

    public function resolvedRewardCoins(): int
    {
        $coins = (int) ($this->reward_coins ?? self::DEFAULT_REWARD_COINS);

        return max(1, min(1000, $coins));
    }

    public function resolvedCtaText(): string
    {
        $text = trim((string) ($this->cta_text ?? ''));

        return $text !== '' ? $text : self::DEFAULT_CTA_TEXT;
    }

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
