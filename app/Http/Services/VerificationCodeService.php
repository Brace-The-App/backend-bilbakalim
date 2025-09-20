<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

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
                return [
                    'success' => false,
                    'message' => $rateLimitCheck['message'],
                    'code' => 429,
                    'remaining_seconds' => $rateLimitCheck['remaining_seconds']
                ];
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
                return [
                    'success' => false,
                    'message' => $rateLimitCheck['message'],
                    'code' => 429,
                    'remaining_seconds' => $rateLimitCheck['remaining_seconds']
                ];
            }

            // Generate verification code
            $code = $this->generateVerificationCode($phone, 'phone');
            
            // Set rate limit
            $this->setRateLimit($phone, 'phone');

            // Here you would integrate with SMS service
            // For now, we'll just log it
            Log::info("Verification code for phone {$phone}: {$code} for purpose: {$purpose}");

            return [
                'success' => true,
                'message' => 'Doğrulama kodu telefon numaranıza gönderildi.',
                'code' => 200,
                'debug_code' => $code // Remove this in production
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
            $verificationResult = $this->verifyCodeFromTrait($identifier, $code, $type);
            
            if (!$verificationResult['success']) {
                return [
                    'success' => false,
                    'message' => $verificationResult['message'],
                    'code' => 400,
                    'attempts_remaining' => $verificationResult['attempts_remaining']
                ];
            }

            // Store verification success for specific purpose
            $verificationKey = "verified_{$type}_{$purpose}_{$identifier}";
            Cache::put($verificationKey, true, now()->addMinutes(15)); // 15 minutes validity

            Log::info("Verification successful for {$type}: {$identifier} purpose: {$purpose}");

            return [
                'success' => true,
                'message' => 'Doğrulama başarılı.',
                'code' => 200,
                'verification_token' => $this->generateSecureToken()
            ];

        } catch (\Exception $e) {
            Log::error("Code verification failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Doğrulama sırasında bir hata oluştu.',
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
