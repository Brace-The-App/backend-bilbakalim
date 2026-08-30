<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

class NotificationPresetService
{
    public function presetsPath(): string
    {
        return (string) config('notifications.presets_path', config_path('notification_presets.json'));
    }

    /** @return array<int, array<string, mixed>> */
    public function allPresets(): array
    {
        $path = $this->presetsPath();

        if (! File::exists($path)) {
            return [];
        }

        $mtime = (int) @filemtime($path);
        $cacheKey = 'notification_presets:v1:' . md5($path) . ':' . $mtime;

        return Cache::remember($cacheKey, 86400, function () use ($path) {
            $decoded = json_decode(File::get($path), true);

            if (! is_array($decoded) || ! isset($decoded['presets']) || ! is_array($decoded['presets'])) {
                return [];
            }

            return array_values(array_filter($decoded['presets'], function ($item) {
                return is_array($item)
                    && filled($item['channel'] ?? null)
                    && filled($item['title'] ?? null)
                    && filled($item['content'] ?? null);
            }));
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function presetsForChannel(string $channel): array
    {
        return array_values(array_filter(
            $this->allPresets(),
            fn (array $p) => ($p['channel'] ?? '') === $channel
        ));
    }

    /** @return array<string, mixed>|null */
    public function findByKey(string $key): ?array
    {
        foreach ($this->allPresets() as $preset) {
            if (($preset['key'] ?? '') === $key) {
                return $preset;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function randomPreset(?string $channel = null): array
    {
        $pool = $channel ? $this->presetsForChannel($channel) : $this->allPresets();

        if ($pool === []) {
            throw new RuntimeException('Şablon havuzunda uygun örnek bulunamadı.');
        }

        return $pool[array_rand($pool)];
    }
}
