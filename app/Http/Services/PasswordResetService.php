<?php

namespace App\Http\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PasswordResetService
{
    use VerificationTrait;

    /**
     * Send password reset code via email
     *
     * @param string $email
     * @return array
     */
    public function sendPasswordResetCode(string $email): array
    {
        try {
            // Check if user exists
            $user = User::where('email', $email)->first();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı.',
                    'code' => 404
                ];
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

    /**
     * Send password reset code via phone
     *
     * @param string $phone
     * @return array
     */
    public function sendPasswordResetCodeToPhone(string $phone): array
    {
        try {
            // Check if user exists
            $user = User::where('phone', $phone)->first();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Bu telefon numarası ile kayıtlı kullanıcı bulunamadı.',
                    'code' => 404
                ];
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
            Log::info("Password reset code for phone {$phone}: {$code}");

            return [
                'success' => true,
                'message' => 'Şifre sıfırlama kodu telefon numaranıza gönderildi.',
                'code' => 200,
                'debug_code' => $code // Remove this in production
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

    /**
     * Verify password reset code
     *
     * @param string $identifier
     * @param string $code
     * @param string $type
     * @return array
     */
    public function verifyPasswordResetCode(string $identifier, string $code, string $type = 'email'): array
    {
        try {
            $verificationResult = $this->verifyCode($identifier, $code, $type);
            
            if (!$verificationResult['success']) {
                return [
                    'success' => false,
                    'message' => $verificationResult['message'],
                    'code' => 400,
                    'attempts_remaining' => $verificationResult['attempts_remaining']
                ];
            }

            // Generate reset token
            $resetToken = $this->generateSecureToken();
            $this->storeResetToken($identifier, $resetToken, $type);

            return [
                'success' => true,
                'message' => 'Doğrulama başarılı. Şifre sıfırlama işlemini tamamlayabilirsiniz.',
                'code' => 200,
                'reset_token' => $resetToken
            ];

        } catch (\Exception $e) {
            Log::error("Password reset code verification failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Doğrulama sırasında bir hata oluştu.',
                'code' => 500
            ];
        }
    }

    /**
     * Reset password with token
     *
     * @param string $identifier
     * @param string $resetToken
     * @param string $newPassword
     * @param string $type
     * @return array
     */
    public function resetPassword(string $identifier, string $resetToken, string $newPassword, string $type = 'email'): array
    {
        try {
            // Verify reset token
            if (!$this->verifyResetToken($identifier, $resetToken, $type)) {
                return [
                    'success' => false,
                    'message' => 'Geçersiz veya süresi dolmuş sıfırlama token\'ı.',
                    'code' => 400
                ];
            }

            // Find user
            $user = $type === 'email' 
                ? User::where('email', $identifier)->first()
                : User::where('phone', $identifier)->first();

            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı.',
                    'code' => 404
                ];
            }

            // Update password
            $user->password = Hash::make($newPassword);
            $user->save();

            // Remove reset token
            $this->removeResetToken($identifier, $type);

            Log::info("Password reset successful for user: {$user->email}");

            return [
                'success' => true,
                'message' => 'Şifreniz başarıyla güncellendi.',
                'code' => 200
            ];

        } catch (\Exception $e) {
            Log::error("Password reset failed: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Şifre sıfırlama sırasında bir hata oluştu.',
                'code' => 500
            ];
        }
    }

    /**
     * Check if identifier exists (email or phone)
     *
     * @param string $identifier
     * @param string $type
     * @return array
     */
    public function checkIdentifierExists(string $identifier, string $type = 'email'): array
    {
        try {
            $user = $type === 'email' 
                ? User::where('email', $identifier)->first()
                : User::where('phone', $identifier)->first();

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
}
