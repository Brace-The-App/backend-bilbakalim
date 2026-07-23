<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

trait VerificationTrait
{
    /**
     * Generate and store verification code
     *
     * @param string $identifier (email or phone)
     * @param string $type (email or phone)
     * @return string
     */
    protected function generateVerificationCode(string $identifier, string $type = 'email'): string
    {
        $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = "verification_code_{$type}_{$identifier}";
        
        // Store code for 15 minutes
        Cache::put($cacheKey, [
            'code' => $code,
            'attempts' => 0,
            'created_at' => now()
        ], now()->addMinutes(15));
        
        Log::info("Verification code generated for {$type}: {$identifier}");
        
        return $code;
    }

    /**
     * Verify the provided code
     *
     * @param string $identifier
     * @param string $code
     * @param string $type
     * @return array
     */
    protected function verifyCodeFromTrait(string $identifier, string $code, string $type = 'email'): array
    {
        $cacheKey = "verification_code_{$type}_{$identifier}";
        $cachedData = Cache::get($cacheKey);
        
        if (!$cachedData) {
            return [
                'success' => false,
                'message' => 'Doğrulama kodu bulunamadı veya süresi dolmuş.',
                'attempts_remaining' => 0
            ];
        }
        
        // Check attempts limit
        if ($cachedData['attempts'] >= 5) {
            Cache::forget($cacheKey);
            return [
                'success' => false,
                'message' => 'Çok fazla yanlış deneme yapıldı. Yeni kod talep edin.',
                'attempts_remaining' => 0
            ];
        }
        
        // Verify code
        if ($cachedData['code'] === $code) {
            Cache::forget($cacheKey);
            return [
                'success' => true,
                'message' => 'Doğrulama başarılı.',
                'attempts_remaining' => 0
            ];
        }
        
        // Increment attempts
        $cachedData['attempts']++;
        Cache::put($cacheKey, $cachedData, now()->addMinutes(15));
        
        return [
            'success' => false,
            'message' => 'Yanlış doğrulama kodu.',
            'attempts_remaining' => 5 - $cachedData['attempts']
        ];
    }

    /**
     * Check if user can request new code
     *
     * @param string $identifier
     * @param string $type
     * @return array
     */
    protected function canRequestNewCode(string $identifier, string $type = 'email'): array
    {
        $rateLimitKey = "rate_limit_{$type}_{$identifier}";
        $lastRequest = Cache::get($rateLimitKey);

        // Check if there's an existing verification code that hasn't expired
        $cacheKey = "verification_code_{$type}_{$identifier}";
        $existingCode = Cache::get($cacheKey);

        if ($existingCode && isset($existingCode['created_at'])) {
            $createdAt = $existingCode['created_at'] instanceof \Carbon\Carbon
                ? $existingCode['created_at']
                : \Carbon\Carbon::parse($existingCode['created_at']);

            if ($createdAt->gt(now()->subMinutes(15))) {
                $expiryTime = $createdAt->copy()->addMinutes(15);
                $remainingSeconds = $this->secondsUntil($expiryTime);

                return [
                    'can_request' => false,
                    'message' => 'Mevcut kod henüz geçerli. Yeni kod talep etmek için ' . $this->formatWaitDuration($remainingSeconds) . ' bekleyin.',
                    'remaining_seconds' => (int) $remainingSeconds,
                    'remaining_formatted' => $this->formatWaitClock($remainingSeconds),
                ];
            }
        }

        // Check rate limit (2 minutes between requests)
        if ($lastRequest) {
            $lastRequestAt = $lastRequest instanceof \Carbon\Carbon
                ? $lastRequest
                : \Carbon\Carbon::parse($lastRequest);

            if ($lastRequestAt->gt(now()->subMinutes(2))) {
                $nextAllowedTime = $lastRequestAt->copy()->addMinutes(2);
                $remainingSeconds = $this->secondsUntil($nextAllowedTime);

                return [
                    'can_request' => false,
                    'message' => 'Yeni kod talep etmek için ' . $this->formatWaitDuration($remainingSeconds) . ' bekleyin.',
                    'remaining_seconds' => (int) $remainingSeconds,
                    'remaining_formatted' => $this->formatWaitClock($remainingSeconds),
                ];
            }
        }

        return [
            'can_request' => true,
            'message' => 'Yeni kod talep edilebilir.',
            'remaining_seconds' => 0,
            'remaining_formatted' => '00:00',
        ];
    }

    /**
     * Kalan süreyi tam saniye (int) olarak hesapla.
     */
    protected function secondsUntil(\DateTimeInterface $target): int
    {
        $diff = $target->getTimestamp() - now()->getTimestamp();

        return (int) max(0, (int) ceil((float) $diff));
    }

    /**
     * Rate-limit cevap alanlarını normalize et.
     */
    protected function normalizeRateLimitPayload(array $rateLimitCheck): array
    {
        $seconds = (int) round((float) ($rateLimitCheck['remaining_seconds'] ?? 0));

        return [
            'success' => false,
            'message' => (string) ($rateLimitCheck['message'] ?? 'Lütfen bekleyin.'),
            'code' => 429,
            'remaining_seconds' => $seconds,
            'remaining_formatted' => (string) ($rateLimitCheck['remaining_formatted'] ?? $this->formatWaitClock($seconds)),
        ];
    }

    /**
     * İnsan okunabilir bekleme süresi: "36 saniye" / "1 dakika 36 saniye"
     */
    protected function formatWaitDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;

        if ($minutes >= 1) {
            if ($secs === 0) {
                return $minutes === 1 ? '1 dakika' : "{$minutes} dakika";
            }

            return "{$minutes} dakika {$secs} saniye";
        }

        return $seconds === 1 ? '1 saniye' : "{$seconds} saniye";
    }

    /**
     * Saat formatı: 01:36 veya 00:18
     */
    protected function formatWaitClock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    /**
     * Set rate limit for code requests
     *
     * @param string $identifier
     * @param string $type
     * @return void
     */
    protected function setRateLimit(string $identifier, string $type = 'email'): void
    {
        $rateLimitKey = "rate_limit_{$type}_{$identifier}";
        Cache::put($rateLimitKey, now(), now()->addMinutes(2));
    }

    /**
     * Generate secure token
     *
     * @return string
     */
    protected function generateSecureToken(): string
    {
        return Str::random(64);
    }

    /**
     * Store reset token
     *
     * @param string $identifier
     * @param string $token
     * @param string $type
     * @return void
     */
    protected function storeResetToken(string $identifier, string $token, string $type = 'email'): void
    {
        $cacheKey = "reset_token_{$type}_{$identifier}";
        Cache::put($cacheKey, [
            'token' => $token,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(15));
    }

    /**
     * Verify reset token
     */
    protected function verifyResetToken(string $identifier, string $token, string $type = 'email'): bool
    {
        $cacheKey = "reset_token_{$type}_{$identifier}";
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData || empty($cachedData['token'])) {
            return false;
        }

        return hash_equals((string) $cachedData['token'], (string) $token);
    }

    /**
     * Remove reset token
     *
     * @param string $identifier
     * @param string $type
     * @return void
     */
    protected function removeResetToken(string $identifier, string $type = 'email'): void
    {
        $cacheKey = "reset_token_{$type}_{$identifier}";
        Cache::forget($cacheKey);
    }
}
