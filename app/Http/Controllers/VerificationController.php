<?php

namespace App\Http\Controllers;

use App\Http\Services\VerificationCodeService;
use App\Http\Requests\SendVerificationCodeRequest;
use App\Http\Requests\VerifyCodeRequest;
use App\Http\Requests\ResendCodeRequest;
use Illuminate\Http\JsonResponse;

class VerificationController extends Controller
{
    protected VerificationCodeService $verificationService;

    public function __construct(VerificationCodeService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * @OA\Post(
     *     path="/api/verification/send-code",
     *     summary="Send verification code",
     *     description="Send verification code via email or phone for various purposes",
     *     tags={"Verification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"identifier","type","purpose"},
     *                 @OA\Property(property="identifier", type="string", example="user@example.com", description="Email or phone number"),
     *                 @OA\Property(property="type", type="string", enum={"email","phone"}, example="email", description="Type of identifier"),
     *                 @OA\Property(property="purpose", type="string", enum={"registration","login","update","password_reset"}, example="registration", description="Purpose of verification")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Doğrulama kodu e-posta adresinize gönderildi."),
     *             @OA\Property(property="code", type="integer", example=200)
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
    public function sendCode(SendVerificationCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        if ($data['type'] === 'email') {
            $result = $this->verificationService->sendEmailVerificationCode(
                $data['identifier'],
                $data['purpose']
            );
        } else {
            $result = $this->verificationService->sendPhoneVerificationCode(
                $data['identifier'],
                $data['purpose']
            );
        }

        return response()->json($result, $result['code']);
    }

    /**
     * @OA\Post(
     *     path="/api/verification/verify",
     *     summary="Verify code",
     *     description="Verify the sent verification code",
     *     tags={"Verification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"identifier","code","type","purpose"},
     *                 @OA\Property(property="identifier", type="string", example="user@example.com", description="Email or phone number"),
     *                 @OA\Property(property="code", type="string", example="123456", description="6-digit verification code"),
     *                 @OA\Property(property="type", type="string", enum={"email","phone"}, example="email", description="Type of identifier"),
     *                 @OA\Property(property="purpose", type="string", enum={"registration","login","update","password_reset"}, example="registration", description="Purpose of verification")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Doğrulama başarılı."),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="verification_token", type="string", example="abc123...")
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
    public function verify(VerifyCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $result = $this->verificationService->verifyCode(
            $data['identifier'],
            $data['code'],
            $data['type'],
            $data['purpose']
        );

        return response()->json($result, $result['code']);
    }

    /**
     * @OA\Post(
     *     path="/api/verification/resend",
     *     summary="Resend verification code",
     *     description="Resend verification code if not received or expired",
     *     tags={"Verification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"identifier","type","purpose"},
     *                 @OA\Property(property="identifier", type="string", example="user@example.com", description="Email or phone number"),
     *                 @OA\Property(property="type", type="string", enum={"email","phone"}, example="email", description="Type of identifier"),
     *                 @OA\Property(property="purpose", type="string", enum={"registration","login","update","password_reset"}, example="registration", description="Purpose of verification")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code resent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Doğrulama kodu tekrar gönderildi."),
     *             @OA\Property(property="code", type="integer", example=200)
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
    public function resend(ResendCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        $result = $this->verificationService->resendCode(
            $data['identifier'],
            $data['type'],
            $data['purpose']
        );

        return response()->json($result, $result['code']);
    }

    /**
     * @OA\Get(
     *     path="/api/verification/status/{identifier}",
     *     summary="Check verification status",
     *     description="Check if identifier is verified for specific purpose",
     *     tags={"Verification"},
     *     @OA\Parameter(
     *         name="identifier",
     *         in="path",
     *         required=true,
     *         description="Email or phone number",
     *         @OA\Schema(type="string", example="user@example.com")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=true,
     *         description="Type of identifier",
     *         @OA\Schema(type="string", enum={"email","phone"}, example="email")
     *     ),
     *     @OA\Parameter(
     *         name="purpose",
     *         in="query",
     *         required=true,
     *         description="Purpose of verification",
     *         @OA\Schema(type="string", enum={"registration","login","update","password_reset"}, example="registration")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status checked",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="verified", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Doğrulama tamamlanmış."),
     *             @OA\Property(property="code", type="integer", example=200)
     *         )
     *     )
     * )
     */
    public function checkStatus(string $identifier): JsonResponse
    {
        $type = request()->query('type', 'email');
        $purpose = request()->query('purpose', 'registration');
        
        $isVerified = $this->verificationService->isVerified($identifier, $type, $purpose);
        
        return response()->json([
            'success' => true,
            'verified' => $isVerified,
            'message' => $isVerified ? 'Doğrulama tamamlanmış.' : 'Doğrulama tamamlanmamış.',
            'code' => 200
        ]);
    }
}