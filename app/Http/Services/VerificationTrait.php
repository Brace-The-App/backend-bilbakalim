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
        
        if ($existingCode && $existingCode['created_at'] > now()->subMinutes(15)) {
            $expiryTime = $existingCode['created_at']->copy()->addMinutes(15);
            $remainingSeconds = max(0, now()->diffInSeconds($expiryTime, false));
            $remainingMinutes = floor($remainingSeconds / 60);
            $remainingSecondsOnly = $remainingSeconds % 60;
            
            if ($remainingMinutes >= 1) {
                $message = "Mevcut kod henüz geçerli. Yeni kod talep etmek için {$remainingMinutes} dakika {$remainingSecondsOnly} saniye bekleyin.";
            } else {
                $message = "Mevcut kod henüz geçerli. Yeni kod talep etmek için {$remainingSeconds} saniye bekleyin.";
            }
            
            return [
                'can_request' => false,
                'message' => $message,
                'remaining_seconds' => $remainingSeconds
            ];
        }
        
        // Check rate limit (2 minutes between requests)
        if ($lastRequest && $lastRequest > now()->subMinutes(2)) {
            $nextAllowedTime = $lastRequest->copy()->addMinutes(2);
            $remainingSeconds = max(0, now()->diffInSeconds($nextAllowedTime, false));
            $remainingMinutes = floor($remainingSeconds / 60);
            $remainingSecondsOnly = $remainingSeconds % 60;
            
            if ($remainingMinutes >= 1) {
                $message = "Yeni kod talep etmek için {$remainingMinutes} dakika {$remainingSecondsOnly} saniye bekleyin.";
            } else {
                $message = "Yeni kod talep etmek için {$remainingSeconds} saniye bekleyin.";
            }
            
            return [
                'can_request' => false,
                'message' => $message,
                'remaining_seconds' => $remainingSeconds
            ];
        }
        
        return [
            'can_request' => true,
            'message' => 'Yeni kod talep edilebilir.',
            'remaining_seconds' => 0
        ];
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
            'created_at' => now()
        ], now()->addMinutes(15)); // 15 minutes expiry
    }

    /**
     * Verify reset token
     *
     * @param string $identifier
     * @param string $token
     * @param string $type
     * @return bool
     */
    protected function verifyResetToken(string $identifier, string $token, string $type = 'email'): bool
    {
        $cacheKey = "reset_token_{$type}_{$identifier}";
        $cachedData = Cache::get($cacheKey);
        
        if (!$cachedData || $cachedData['token'] !== $token) {
            return false;
        }
        
        return true;
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
