<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
// use App\Http\Services\NetGsmService; // eski SMS (NetGSM)
use App\Http\Services\SmsVitriniService;

class VerificationCodeService
{
    use VerificationTrait;

    /**
     * Send verification code via email
     *
     * @param string $email
     * @param string $purpose (registration, login, update)
     * @return array
     */
    public function sendEmailVerificationCode(string $email, string $purpose = 'registration'): array
    {
        try {
            // For registration purpose, we don't need to check if user exists
            // For other purposes, check if user exists
            if ($purpose !== 'registration') {
                $user = \App\Models\User::where('email', $email)->first();
                if (!$user) {
                    return [
                        'success' => false,
                        'message' => 'Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı.',
                        'code' => 404
                    ];
                }
            }

            // Check rate limit
            $rateLimitCheck = $this->canRequestNewCode($email, 'email');
            if (!$rateLimitCheck['can_request']) {
                return $this->normalizeRateLimitPayload($rateLimitCheck);
            }

            // Generate verification code
            $code = $this->generateVerificationCode($email, 'email');

            // Set rate limit
            $this->setRateLimit($email, 'email');

            // Send email
            $subject = $this->getEmailSubject($purpose);
            $purposeTitle = $this->getPurposeTitle($purpose);
            $messageText = $this->getEmailMessage($code, $purpose);

            Mail::send('emails.verification-code', [
                'code' => $code,
                'subject' => $subject,
                'purposeTitle' => $purposeTitle,
                'messageText' => $messageText,
                'purpose' => $purpose
            ], function ($mailMessage) use ($email, $subject) {
                $mailMessage->to($email)->subject($subject);
            });

            Log::info("Verification code sent to email: {$email} for purpose: {$purpose}");

            return [
                'success' => true,
                'message' => 'Doğrulama kodu e-posta adresinize gönderildi.',
                'code' => 200
            ];

        } catch (\Exception $e) {
            Log::error("Email verification code sending failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Kod gönderilirken bir hata oluştu. Lütfen tekrar deneyin.',
                'code' => 500
            ];
        }
    }

    /**
     * Send verification code via phone
     *
     * @param string $phone
     * @param string $purpose
     * @return array
     */
    public function sendPhoneVerificationCode(string $phone, string $purpose = 'registration'): array
    {
        try {
            // For registration purpose, we don't need to check if user exists
            // For other purposes, check if user exists
            if ($purpose !== 'registration') {
                $user = \App\Models\User::where('phone', $phone)->first();
                if (!$user) {
                    return [
                        'success' => false,
                        'message' => 'Bu telefon numarası ile kayıtlı kullanıcı bulunamadı.',
                        'code' => 404
                    ];
                }
            }

            // Check rate limit
            $rateLimitCheck = $this->canRequestNewCode($phone, 'phone');
            if (!$rateLimitCheck['can_request']) {
                return $this->normalizeRateLimitPayload($rateLimitCheck);
            }

            // Generate verification code
            $code = $this->generateVerificationCode($phone, 'phone');

            // Set rate limit
            $this->setRateLimit($phone, 'phone');

            $smsMessage = $this->getSmsMessage($code, $purpose);

            // Eski NetGSM (silinmedi — geri almak için):
            // $netGsmService = new NetGsmService();
            // $smsResult = $netGsmService->sendSms($phone, $smsMessage);

            $smsService = new SmsVitriniService;
            $smsResult = $smsService->sendSms($phone, $smsMessage);

            if (! $smsResult['success']) {
                Log::error("SMS (doğrulama) gönderilemedi, telefon {$phone}: ".($smsResult['message'] ?? 'Unknown error'));

                return [
                    'success' => false,
                    'message' => 'SMS gönderilirken bir hata oluştu. Lütfen tekrar deneyin.',
                    'code' => 500
                ];
            }

            Log::info("Verification code sent via SMS to phone {$phone} for purpose: {$purpose}");

            return [
                'success' => true,
                'message' => 'Doğrulama kodu telefon numaranıza gönderildi.',
                'code' => 200
            ];

        } catch (\Exception $e) {
            Log::error("Phone verification code sending failed: " . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Kod gönderilirken bir hata oluştu. Lütfen tekrar deneyin.',
                'code' => 500
            ];
        }
    }

    /**
     * Verify code
     *
     * @param string $identifier
     * @param string $code
     * @param string $type
     * @param string $purpose
     * @return array
     */
    public function verifyCode(string $identifier, string $code, string $type = 'email', string $purpose = 'registration'): array
    {
        try {
            // Telefon format farkı için hem ham hem normalize dene
            $verificationResult = $this->verifyCodeFromTrait($identifier, $code, $type);
            if (!$verificationResult['success'] && $type === 'phone') {
                $normalizedPhone = $this->normalizePhoneDigits($identifier);
                if ($normalizedPhone !== $identifier) {
                    $verificationResult = $this->verifyCodeFromTrait($normalizedPhone, $code, $type);
                }
            }

            if (!$verificationResult['success']) {
                return [
                    'success' => false,
                    'message' => $verificationResult['message'],
                    'code' => 400,
                    'attempts_remaining' => $verificationResult['attempts_remaining']
                ];
            }

            $verificationKey = "verified_{$type}_{$purpose}_{$identifier}";
            Cache::put($verificationKey, true, now()->addMinutes(15));

            $verificationToken = $this->generateSecureToken();
            $tokenSaved = false;
            $userId = null;

            $user = $type === 'email'
                ? User::where('email', strtolower(trim($identifier)))->first()
                : $this->findUserByPhoneFlexible($identifier);

            if ($user) {
                $userId = (int) $user->id;
            }

            // Token her zaman identifier ile kaydedilir (registration dahil)
            // Böylece verify → password-reset/reset akışı çalışır
            app(PasswordResetService::class)->persistResetToken(
                $verificationToken,
                $userId,
                $type,
                $identifier
            );
            $tokenSaved = true;

            Log::info('Verification token persisted', [
                'user_id' => $userId,
                'purpose' => $purpose,
                'type' => $type,
                'identifier' => $identifier,
            ]);

            Log::info("Verification successful for {$type}: {$identifier} purpose: {$purpose}");

            return [
                'success' => true,
                'message' => 'Doğrulama başarılı.',
                'code' => 200,
                'verification_token' => $verificationToken,
                'reset_token' => $verificationToken,
                'token_saved' => $tokenSaved,
                'user_id' => $userId,
                'purpose_received' => $purpose,
            ];

        } catch (\Exception $e) {
            Log::error("Code verification failed: " . $e->getMessage(), [
                'exception' => $e,
            ]);

            return [
                'success' => false,
                'message' => 'Doğrulama sırasında bir hata oluştu: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * Check if identifier is verified for specific purpose
     *
     * @param string $identifier
     * @param string $type
     * @param string $purpose
     * @return bool
     */
    public function isVerified(string $identifier, string $type = 'email', string $purpose = 'registration'): bool
    {
        $verificationKey = "verified_{$type}_{$purpose}_{$identifier}";
        return Cache::has($verificationKey);
    }

    private function findUserByPhoneFlexible(string $phone): ?User
    {
        $digits = $this->normalizePhoneDigits($phone);

        $variants = array_values(array_unique(array_filter([
            $phone,
            $digits,
            '+' . $digits,
            strlen($digits) >= 12 ? '0' . substr($digits, 2) : null,
            strlen($digits) >= 12 ? substr($digits, 2) : null,
        ])));

        return User::whereIn('phone', $variants)->first();
    }

    private function normalizePhoneDigits(string $phone): string
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

    /**
     * Get email subject based on purpose
     *
     * @param string $purpose
     * @return string
     */
    private function getEmailSubject(string $purpose): string
    {
        return match($purpose) {
            'registration' => 'Hesap Doğrulama Kodu - Bilbakalim',
            'login' => 'Giriş Doğrulama Kodu - Bilbakalim',
            'update' => 'Bilgi Güncelleme Doğrulama Kodu - Bilbakalim',
            'password_reset' => 'Şifre Sıfırlama Kodu - Bilbakalim',
            default => 'Doğrulama Kodu - Bilbakalim'
        };
    }

    /**
     * Get purpose title based on purpose
     *
     * @param string $purpose
     * @return string
     */
    private function getPurposeTitle(string $purpose): string
    {
        return match($purpose) {
            'registration' => 'Hesap Doğrulama',
            'login' => 'Giriş Doğrulama',
            'update' => 'Bilgi Güncelleme Doğrulama',
            'password_reset' => 'Şifre Sıfırlama',
            default => 'Doğrulama'
        };
    }

    /**
     * Get SMS message based on purpose
     *
     * @param string $code
     * @param string $purpose
     * @return string
     */
    private function getSmsMessage(string $code, string $purpose): string
    {
        $purposeText = match($purpose) {
            'registration' => 'Hesap doğrulama',
            'login' => 'Giriş doğrulama',
            'update' => 'Bilgi güncelleme',
            'password_reset' => 'Şifre sıfırlama',
            default => 'Doğrulama'
        };

        return "{$purposeText} kodunuz: {$code} Bu kod 15 dakika geçerlidir.";
    }

    /**
     * Get email message based on purpose
     *
     * @param string $code
     * @param string $purpose
     * @return string
     */
    private function getEmailMessage(string $code, string $purpose): string
    {
        $baseMessage = "Doğrulama kodunuz: {$code}\n\nBu kod 15 dakika geçerlidir.\n\nGüvenliğiniz için bu kodu kimseyle paylaşmayın.";

        return match($purpose) {
            'registration' => "Hesabınızı doğrulamak için kullanacağınız kod:\n\n{$baseMessage}",
            'login' => "Giriş işleminizi doğrulamak için kullanacağınız kod:\n\n{$baseMessage}",
            'update' => "Bilgilerinizi güncellemek için kullanacağınız kod:\n\n{$baseMessage}",
            'password_reset' => "Şifrenizi sıfırlamak için kullanacağınız kod:\n\n{$baseMessage}",
            default => $baseMessage
        };
    }

    /**
     * Resend verification code
     *
     * @param string $identifier
     * @param string $type
     * @param string $purpose
     * @return array
     */
    public function resendCode(string $identifier, string $type = 'email', string $purpose = 'registration'): array
    {
        if ($type === 'email') {
            return $this->sendEmailVerificationCode($identifier, $purpose);
        } else {
            return $this->sendPhoneVerificationCode($identifier, $purpose);
        }
    }

    /**
     * Clear verification data
     *
     * @param string $identifier
     * @param string $type
     * @param string $purpose
     * @return void
     */
    public function clearVerificationData(string $identifier, string $type = 'email', string $purpose = 'registration'): void
    {
        $cacheKey = "verification_code_{$type}_{$identifier}";
        $verificationKey = "verified_{$type}_{$purpose}_{$identifier}";

        Cache::forget($cacheKey);
        Cache::forget($verificationKey);
    }
}
