<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use App\Http\Services\SmsVitriniService;
use Carbon\Carbon;

class PasswordResetService
{
    use VerificationTrait;

    private const TOKEN_TTL_MINUTES = 30;

    public function sendPasswordResetCode(string $email): array
    {
        try {
            $email = $this->normalizeEmail($email);
            $user = User::where('email', $email)->first();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı.',
                    'code' => 404
                ];
            }

            $rateLimitCheck = $this->canRequestNewCode($email, 'email');
            if (!$rateLimitCheck['can_request']) {
                return $this->normalizeRateLimitPayload($rateLimitCheck);
            }

            $code = $this->generateVerificationCode($email, 'email');
            $this->setRateLimit($email, 'email');

            Mail::send('emails.password-reset', [
                'code' => $code,
                'email' => $email
            ], function ($message) use ($email) {
                $message->to($email)
                    ->subject('Şifre Sıfırlama Kodu - Bilbakalim');
            });

            Log::info("Password reset code sent to: {$email}");

            return [
                'success' => true,
                'message' => 'Şifre sıfırlama kodu e-posta adresinize gönderildi.',
                'code' => 200
            ];
        } catch (\Exception $e) {
            Log::error("Password reset code sending failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Kod gönderilirken bir hata oluştu. Lütfen tekrar deneyin.',
                'code' => 500
            ];
        }
    }

    public function sendPasswordResetCodeToPhone(string $phone): array
    {
        try {
            $user = $this->findUserByPhone($phone);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Bu telefon numarası ile kayıtlı kullanıcı bulunamadı.',
                    'code' => 404
                ];
            }

            $identifier = $this->normalizePhone($phone);

            $rateLimitCheck = $this->canRequestNewCode($identifier, 'phone');
            if (!$rateLimitCheck['can_request']) {
                return $this->normalizeRateLimitPayload($rateLimitCheck);
            }

            $code = $this->generateVerificationCode($identifier, 'phone');
            $this->setRateLimit($identifier, 'phone');

            $smsMessage = $this->getSmsMessage($code);
            $smsService = new SmsVitriniService;
            $smsResult = $smsService->sendSms($identifier, $smsMessage);

            if (! $smsResult['success']) {
                Log::error("SMS Vitrini (şifre sıfırlama) gönderilemedi, telefon {$identifier}: ".($smsResult['message'] ?? 'Unknown error'));

                return [
                    'success' => false,
                    'message' => 'SMS gönderilirken bir hata oluştu. Lütfen tekrar deneyin.',
                    'code' => 500
                ];
            }

            Log::info("Password reset code sent via SMS to phone {$identifier}");

            return [
                'success' => true,
                'message' => 'Şifre sıfırlama kodu telefon numaranıza gönderildi.',
                'code' => 200
            ];
        } catch (\Exception $e) {
            Log::error("Password reset code sending to phone failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Kod gönderilirken bir hata oluştu. Lütfen tekrar deneyin.',
                'code' => 500
            ];
        }
    }

    public function verifyPasswordResetCode(string $identifier, string $code, string $type = 'email'): array
    {
        try {
            $user = $this->findUserByIdentifier($identifier, $type);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı.',
                    'code' => 404,
                    'attempts_remaining' => 0
                ];
            }

            $cacheIdentifier = $type === 'phone'
                ? $this->normalizePhone($identifier)
                : $this->normalizeEmail($identifier);

            $verificationResult = $this->verifyCodeFromTrait($cacheIdentifier, $code, $type);

            if (!$verificationResult['success']) {
                return [
                    'success' => false,
                    'message' => $verificationResult['message'],
                    'code' => 400,
                    'attempts_remaining' => $verificationResult['attempts_remaining']
                ];
            }

            $resetToken = $this->generateSecureToken();
            $this->persistResetToken($resetToken, (int) $user->id, $type, $identifier);

            return [
                'success' => true,
                'message' => 'Doğrulama başarılı. Şifre sıfırlama işlemini tamamlayabilirsiniz.',
                'code' => 200,
                'reset_token' => $resetToken,
                'verification_token' => $resetToken,
                'token_saved' => true,
                'user_id' => (int) $user->id,
            ];
        } catch (\Exception $e) {
            Log::error("Password reset code verification failed: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => 'Doğrulama sırasında bir hata oluştu.',
                'code' => 500
            ];
        }
    }

    public function resetPassword(string $identifier, string $resetToken, string $newPassword, string $type = 'email'): array
    {
        try {
            $user = $this->findUserByIdentifier($identifier, $type);

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı.',
                    'code' => 404
                ];
            }

            if (!$this->validatePersistedResetToken($resetToken, (int) $user->id, $type, $identifier)) {
                Log::warning('Password reset token mismatch', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'identifier' => $identifier,
                    'token_len' => strlen($resetToken),
                    'token_prefix' => substr($resetToken, 0, 8),
                ]);

                return [
                    'success' => false,
                    'message' => 'Geçersiz veya süresi dolmuş sıfırlama token\'ı.',
                    'code' => 400
                ];
            }

            $user->password = Hash::make($newPassword);
            $user->save();

            $this->deletePersistedResetToken((int) $user->id, $type, $identifier);

            Log::info("Password reset successful for user: {$user->id}");

            return [
                'success' => true,
                'message' => 'Şifreniz başarıyla güncellendi.',
                'code' => 200
            ];
        } catch (\Exception $e) {
            Log::error("Password reset failed: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => 'Şifre sıfırlama sırasında bir hata oluştu.',
                'code' => 500
            ];
        }
    }

    public function checkIdentifierExists(string $identifier, string $type = 'email'): array
    {
        try {
            $user = $this->findUserByIdentifier($identifier, $type);

            return [
                'success' => true,
                'exists' => $user !== null,
                'message' => $user ? 'Kullanıcı bulundu.' : 'Kullanıcı bulunamadı.',
                'code' => 200
            ];
        } catch (\Exception $e) {
            Log::error("Identifier check failed: " . $e->getMessage());

            return [
                'success' => false,
                'exists' => false,
                'message' => 'Kontrol sırasında bir hata oluştu.',
                'code' => 500
            ];
        }
    }

    private function getSmsMessage(string $code): string
    {
        return "Şifre sıfırlama kodunuz: {$code} Bu kod 15 dakika geçerlidir.";
    }

    private function findUserByIdentifier(string $identifier, string $type): ?User
    {
        if ($type === 'email') {
            return User::where('email', $this->normalizeEmail($identifier))->first();
        }

        return $this->findUserByPhone($identifier);
    }

    private function findUserByPhone(string $phone): ?User
    {
        $normalized = $this->normalizePhone($phone);
        $variants = array_values(array_unique(array_filter([
            $phone,
            $normalized,
            '+' . $normalized,
            strlen($normalized) >= 12 ? '0' . substr($normalized, 2) : null,
            strlen($normalized) >= 12 ? substr($normalized, 2) : null,
        ])));

        return User::whereIn('phone', $variants)->first();
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '90' . substr($digits, 1);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '5')) {
            $digits = '90' . $digits;
        }

        return $digits;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * Token sakla: user:{id} ve/veya phone:{normalized} / email:{normalized}
     */
    public function persistResetToken(
        string $token,
        ?int $userId = null,
        ?string $type = null,
        ?string $identifier = null
    ): void {
        $hash = hash('sha256', $token);
        $expiresAt = now()->addMinutes(self::TOKEN_TTL_MINUTES);
        $keys = $this->buildTokenKeys($userId, $type, $identifier);

        if (empty($keys)) {
            throw new \RuntimeException('Token kaydı için user_id veya identifier gerekli.');
        }

        $dbOk = false;

        foreach ($keys as $key) {
            Cache::put('pwd_reset_token_' . md5($key), $hash, $expiresAt);

            try {
                if (!Schema::hasTable('password_reset_tokens')) {
                    throw new \RuntimeException('password_reset_tokens tablosu yok');
                }

                DB::table('password_reset_tokens')->where('email', $key)->delete();
                DB::table('password_reset_tokens')->insert([
                    'email' => $key,
                    'token' => $hash,
                    'created_at' => now(),
                ]);

                $saved = DB::table('password_reset_tokens')->where('email', $key)->value('token');
                if (is_string($saved) && hash_equals($saved, $hash)) {
                    $dbOk = true;
                }
            } catch (\Throwable $e) {
                Log::error('password_reset_tokens yazılamadı: ' . $e->getMessage(), [
                    'key' => $key,
                    'user_id' => $userId,
                ]);
            }
        }

        if ($userId
            && Schema::hasColumn('users', 'password_reset_token')
            && Schema::hasColumn('users', 'password_reset_expires_at')
        ) {
            try {
                User::where('id', $userId)->update([
                    'password_reset_token' => $hash,
                    'password_reset_expires_at' => $expiresAt,
                ]);
                $dbOk = true;
            } catch (\Throwable $e) {
                Log::warning('users password_reset kolonları yazılamadı: ' . $e->getMessage());
            }
        }

        if ($userId && Schema::hasTable('user_password_resets')) {
            try {
                DB::table('user_password_resets')->updateOrInsert(
                    ['user_id' => $userId],
                    [
                        'token_hash' => $hash,
                        'expires_at' => $expiresAt,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
                $dbOk = true;
            } catch (\Throwable $e) {
                Log::warning('user_password_resets yazılamadı: ' . $e->getMessage());
            }
        }

        // Geriye dönük alias
        if ($userId) {
            Cache::put('pwd_reset_token_' . $userId, $hash, $expiresAt);
        }

        if (!$dbOk && empty(array_filter($keys, fn ($k) => Cache::has('pwd_reset_token_' . md5($k))))) {
            throw new \RuntimeException('Sıfırlama token\'ı kaydedilemedi.');
        }

        Log::info('Password reset token saved', [
            'user_id' => $userId,
            'keys' => $keys,
            'db_ok' => $dbOk,
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);
    }

    /** @deprecated Use persistResetToken() */
    public function persistResetTokenForUser(int $userId, string $token): void
    {
        $this->persistResetToken($token, $userId);
    }

    public function validatePersistedResetToken(
        string $token,
        ?int $userId = null,
        ?string $type = null,
        ?string $identifier = null
    ): bool {
        $hash = hash('sha256', $token);
        $keys = $this->buildTokenKeys($userId, $type, $identifier);

        foreach ($keys as $key) {
            $cachedHash = Cache::get('pwd_reset_token_' . md5($key));
            if (is_string($cachedHash) && hash_equals($cachedHash, $hash)) {
                return true;
            }

            if ($userId) {
                $legacyCache = Cache::get('pwd_reset_token_' . $userId);
                if (is_string($legacyCache) && hash_equals($legacyCache, $hash)) {
                    return true;
                }
            }

            try {
                $row = DB::table('password_reset_tokens')->where('email', $key)->first();
                if ($row && !empty($row->token) && !empty($row->created_at)) {
                    $notExpired = Carbon::parse($row->created_at)
                        ->gte(now()->subMinutes(self::TOKEN_TTL_MINUTES));

                    if ($notExpired && hash_equals((string) $row->token, $hash)) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('password_reset_tokens okunamadı: ' . $e->getMessage());
            }
        }

        if ($userId
            && Schema::hasColumn('users', 'password_reset_token')
            && Schema::hasColumn('users', 'password_reset_expires_at')
        ) {
            $user = User::query()
                ->select(['id', 'password_reset_token', 'password_reset_expires_at'])
                ->find($userId);

            if ($user && !empty($user->password_reset_token) && !empty($user->password_reset_expires_at)) {
                if ($user->password_reset_expires_at->isFuture()
                    && hash_equals((string) $user->password_reset_token, $hash)
                ) {
                    return true;
                }
            }
        }

        if ($userId && Schema::hasTable('user_password_resets')) {
            $row = DB::table('user_password_resets')->where('user_id', $userId)->first();
            if ($row && !empty($row->token_hash) && !empty($row->expires_at)) {
                if (!Carbon::parse($row->expires_at)->isPast()
                    && hash_equals((string) $row->token_hash, $hash)
                ) {
                    return true;
                }
            }
        }

        Log::warning('Password reset token not found', [
            'user_id' => $userId,
            'keys' => $keys,
        ]);

        return false;
    }

    public function deletePersistedResetToken(
        ?int $userId = null,
        ?string $type = null,
        ?string $identifier = null
    ): void {
        $keys = $this->buildTokenKeys($userId, $type, $identifier);

        foreach ($keys as $key) {
            Cache::forget('pwd_reset_token_' . md5($key));
            try {
                DB::table('password_reset_tokens')->where('email', $key)->delete();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($userId) {
            Cache::forget('pwd_reset_token_' . $userId);

            if (Schema::hasColumn('users', 'password_reset_token')
                && Schema::hasColumn('users', 'password_reset_expires_at')
            ) {
                User::where('id', $userId)->update([
                    'password_reset_token' => null,
                    'password_reset_expires_at' => null,
                ]);
            }

            if (Schema::hasTable('user_password_resets')) {
                DB::table('user_password_resets')->where('user_id', $userId)->delete();
            }
        }
    }

    private function buildTokenKeys(?int $userId, ?string $type, ?string $identifier): array
    {
        $keys = [];

        if ($userId) {
            $keys[] = 'user:' . $userId;
        }

        if ($type && $identifier) {
            $normalized = $type === 'phone'
                ? $this->normalizePhone($identifier)
                : $this->normalizeEmail($identifier);

            if ($normalized !== '') {
                $keys[] = $type . ':' . $normalized;
            }
        }

        return array_values(array_unique($keys));
    }
}