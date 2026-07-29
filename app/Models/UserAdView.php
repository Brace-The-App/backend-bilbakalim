<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAdView extends Model
{
    public const MAX_VIEWS = 3;
    public const WINDOW_HOURS = 24;

    protected $fillable = [
        'user_id',
        'view_count',
        'last_viewed_at',
        'window_started_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'view_count' => 'integer',
        'last_viewed_at' => 'datetime',
        'window_started_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['view_count' => 0]
        );
    }

    /**
     * İlk izlemeden itibaren 24 saat dolmuşsa sayacı sıfırla.
     */
    public function resetWindowIfExpired(): void
    {
        $windowStart = $this->window_started_at;

        if (!$windowStart && (int) $this->view_count > 0) {
            $windowStart = $this->created_at;
            $this->window_started_at = $windowStart;
            $this->save();
        }

        if ($windowStart && $windowStart->lte(now()->subHours(self::WINDOW_HOURS))) {
            $this->view_count = 0;
            $this->window_started_at = null;
            $this->save();
        }
    }

    public function remaining(): int
    {
        return max(0, self::MAX_VIEWS - (int) $this->view_count);
    }

    public function isExhausted(): bool
    {
        return (int) $this->view_count >= self::MAX_VIEWS;
    }

    public function windowResetsAt(): ?Carbon
    {
        if (!$this->window_started_at) {
            return null;
        }

        return $this->window_started_at->copy()->addHours(self::WINDOW_HOURS);
    }

    /**
     * Gerçek izleme (ödül) sonrası hakkı düş.
     */
    public function consumeOne(): void
    {
        if ((int) $this->view_count === 0 || !$this->window_started_at) {
            $this->window_started_at = now();
        }

        $this->view_count = (int) $this->view_count + 1;
        $this->last_viewed_at = now();
        $this->save();
    }

    public function statusPayload(): array
    {
        return [
            'count' => (int) $this->view_count,
            'max' => self::MAX_VIEWS,
            'remaining' => $this->remaining(),
            'resets_at' => $this->windowResetsAt()?->toIso8601String(),
        ];
    }
}
