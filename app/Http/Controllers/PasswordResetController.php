<?php

namespace App\Http\Controllers;

use App\Http\Services\PasswordResetService;
use App\Http\Requests\SendPasswordResetCodeRequest;
use App\Http\Requests\VerifyPasswordResetCodeRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\CheckIdentifierRequest;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    protected PasswordResetService $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * @OA\Post(
     *     path="/api/password-reset/send-code",
     *     summary="Send password reset code",
     *     description="Send password reset verification code via email or phone",
     *     tags={"Password Reset"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"identifier","type"},
     *                 @OA\Property(property="identifier", type="string", example="user@example.com", description="Email or phone number"),
     *                 @OA\Property(property="type", type="string", enum={"email","phone"}, example="email", description="Type of identifier")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Şifre sıfırlama kodu e-posta adresinize gönderildi."),
     *             @OA\Property(property="code", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Bu e-posta adresi ile kayıtlı kullanıcı bulunamadı."),
     *             @OA\Property(property="code", type="integer", example=404)
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Yeni kod talep etmek için 45 saniye bekleyin."),
     *             @OA\Property(property="code", type="integer", example=429),
     *             @OA\Property(property="remaining_seconds", type="integer", example=45)
     *         )
     *     )
     * )
     */
    public function sendCode(SendPasswordResetCodeRequest $request): JsonResponse
    {
        $identifier = $request->validated()['identifier'];
        $type = $request->validated()['type'];

        if ($type === 'email') {
            $result = $this->passwordResetService->sendPasswordResetCode($identifier);
        } else {
            $result = $this->passwordResetService->sendPasswordResetCodeToPhone($identifier);
        }

        return response()->json($result, $result['code']);
    }

    /**
     * @OA\Post(
     *     path="/api/password-reset/verify-code",
     *     summary="Verify password reset code",
     *     description="Verify the password reset code and get reset token",
     *     tags={"Password Reset"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"identifier","code","type"},
     *                 @OA\Property(property="identifier", type="string", example="user@example.com", description="Email or phone number"),
     *                 @OA\Property(property="code", type="string", example="123456", description="6-digit verification code"),
     *                 @OA\Property(property="type", type="string", enum={"email","phone"}, example="email", description="Type of identifier")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Doğrulama başarılı. Şifre sıfırlama işlemini tamamlayabilirsiniz."),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="reset_token", type="string", example="abc123...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid code",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Yanlış doğrulama kodu."),
     *             @OA\Property(property="code", type="integer", example=400),
     *             @OA\Property(property="attempts_remaining", type="integer", example=4)
     *         )
     *     )
     * )
     */
    public function verifyCode(VerifyPasswordResetCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $result = $this->passwordResetService->verifyPasswordResetCode(
            $data['identifier'],
            $data['code'],
            $data['type']
        );

        return response()->json($result, $result['code']);
    }

    /**
     * @OA\Post(
     *     path="/api/password-reset/reset",
     *     summary="Reset password",
     *     description="Reset password using the reset token",
     *     tags={"Password Reset"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"identifier","reset_token","new_password","new_password_confirmation","type"},
     *                 @OA\Property(property="identifier", type="string", example="user@example.com", description="Email or phone number"),
     *                 @OA\Property(property="reset_token", type="string", example="abc123...", description="Reset token from verify code"),
     *                 @OA\Property(property="new_password", type="string", example="newPassword123", description="New password"),
     *                 @OA\Property(property="new_password_confirmation", type="string", example="newPassword123", description="New password confirmation"),
     *                 @OA\Property(property="type", type="string", enum={"email","phone"}, example="email", description="Type of identifier")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Şifreniz başarıyla güncellendi."),
     *             @OA\Property(property="code", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid reset token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Geçersiz veya süresi dolmuş sıfırlama token'ı."),
     *             @OA\Property(property="code", type="integer", example=400)
     *         )
     *     )
     * )
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $result = $this->passwordResetService->resetPassword(
            $data['identifier'],
            $data['reset_token'],
            $data['new_password'],
            $data['type']
        );

        return response()->json($result, $result['code']);
    }

    /**
     * @OA\Post(
     *     path="/api/password-reset/check-identifier",
     *     summary="Check if identifier exists",
     *     description="Check if email or phone number exists in the system",
     *     tags={"Password Reset"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"identifier","type"},
     *                 @OA\Property(property="identifier", type="string", example="user@example.com", description="Email or phone number"),
     *                 @OA\Property(property="type", type="string", enum={"email","phone"}, example="email", description="Type of identifier")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Check completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="exists", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Kullanıcı bulundu."),
     *             @OA\Property(property="code", type="integer", example=200)
     *         )
     *     )
     * )
     */
    public function checkIdentifier(CheckIdentifierRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $result = $this->passwordResetService->checkIdentifierExists(
            $data['identifier'],
            $data['type']
        );

        return response()->json($result, $result['code']);
    }
}