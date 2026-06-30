<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Http\Custom\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Services\AuthServices;
use Illuminate\Support\Arr;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication endpoints"
 * )
 */
class AuthController extends Controller
{
    private $user = null;
    private $response = null;
    private $service = null;

    public function __construct()
    {
        $this->user = auth('sanctum')->user();
        $this->service = new AuthServices();
        $this->response = new Response();
    }

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="User Login",
     *     description="Authenticate user with email or phone and return access token",
     *     operationId="login",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"type","password"},
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     enum={"email", "phone"},
     *                     description="Login type: email or phone",
     *                     example="email"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     description="Email address (required when type is 'email')",
     *                     example="user@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     description="Phone number (required when type is 'phone')",
     *                     example="+905551234567"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tebrikler başarılı bir şekilde giriş yaptınız."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="accessToken", type="string", example="1|abc123...")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lütfen bilgileri kontrol ediniz."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $type = $request->input('type', 'email');
        $password = $request->password;

        // Type'a göre kullanıcıyı bul
        if ($type === 'email') {
            $user = User::where('email', $request->email)->first();
        } else {
            // Telefon numarası ile arama - farklı formatları kontrol et
            $phone = $request->phone;
            $user = User::where(function($query) use ($phone) {
                $query->where('phone', $phone)
                    ->orWhere('phone', '+' . $phone) // +90 veya +05 formatı
                    ->orWhere('phone', ltrim($phone, '0')); // 05 ise 5 ile başlayan format

                // 90 ile başlıyorsa 0 ekleyerek de ara
                if (strpos($phone, '90') === 0) {
                    $query->orWhere('phone', '0' . substr($phone, 2));
                }
                // 05 ile başlıyorsa 90 ekleyerek de ara
                if (strpos($phone, '05') === 0) {
                    $query->orWhere('phone', '90' . substr($phone, 1));
                }
            })->first();
        }

        // Kullanıcı bulunamadıysa veya şifre yanlışsa
        if (!$user || !Hash::check($password, $user->password)) {
            $errorField = $type === 'email' ? 'email' : 'phone';
            return Response::error([
                $errorField => ["Incorrect"],
                'password' => ["Incorrect"]
            ], "Lütfen bilgileri kontrol ediniz.");
        }

        // Kullanıcıyı manuel olarak authenticate et
        Auth::login($user);
        $token = $user->createToken('bilbakalim');

        $responseData = [];
        $responseData['user'] = UserResource::make($user);
        $responseData['accessToken'] = $token->plainTextToken;
        return Response::withData(true, "Tebrikler başarılı bir şekilde giriş yaptınız.", $responseData);
    }


    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="User Registration",
     *     description="Register a new user account",
     *     operationId="register",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"phone"},
     *                 @OA\Property(property="phone", type="string", description="Telefon numarası (zorunlu)", example="05551234567"),
     *                 @OA\Property(property="name", type="string", description="İsim (opsiyonel)", example="John"),
     *                 @OA\Property(property="email", type="string", format="email", description="E-posta (opsiyonel)", example="john@example.com"),
     *                 @OA\Property(property="password", type="string", format="password", description="Şifre (opsiyonel)", example="password123"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", description="Şifre tekrar (opsiyonel, password varsa zorunlu)", example="password123"),
     *                 @OA\Property(property="device_id", type="string", description="FCM Device ID (opsiyonel)", example="fcm-device-token-12345"),
     *                 @OA\Property(property="referral_code", type="string", description="Referans kodu (opsiyonel, 8 karakter)", example="ABC12345")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string", description="Telefon numarası (zorunlu)", example="05551234567"),
     *             @OA\Property(property="name", type="string", description="İsim (opsiyonel)", example="John"),
     *             @OA\Property(property="email", type="string", format="email", description="E-posta (opsiyonel)", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", description="Şifre (opsiyonel)", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", description="Şifre tekrar (opsiyonel, password varsa zorunlu)", example="password123"),
     *             @OA\Property(property="device_id", type="string", description="FCM Device ID (opsiyonel)", example="fcm-device-token-12345"),
     *             @OA\Property(property="referral_code", type="string", description="Referans kodu (opsiyonel, 8 karakter)", example="ABC12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Kullanıcı başarılı bir şekilde oluşturuldu."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="accessToken", type="string", example="1|abc123...")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = new User();
        $user->phone = $request->phone;
        $user->role_id = 3;
        $user->account_status = 'active';
        $registrationBonus = 2000;
        $user->coins = $registrationBonus;

        // Unique referral kodu oluştur
        $user->referral_code = User::generateReferralCode();
        $user->has_used_referral = false;

        // Optional alanları ekle
        if ($request->has('name') && $request->name) {
            $user->name = $request->name;
        }

        if ($request->has('email') && $request->email) {
            $user->email = $request->email;
        }

        if ($request->has('password') && $request->password) {
            $user->password = bcrypt($request->password);
        }

        if ($request->has('device_id') && $request->device_id) {
            $user->device_id = $request->device_id;
        }

        $user->save();

        \App\Models\CoinHistory::create([
            'user_id' => $user->id,
            'coin_amount' => $registrationBonus,
            'transaction_type' => 'bonus',
            'status' => 'completed',
            'description' => 'Kayıt bonusu',
            'metadata' => [
                'reward_type' => 'registration_bonus',
            ],
            'balance_before' => 0,
            'balance_after' => $registrationBonus,
        ]);

        // Referral kod kontrolü ve coin ekleme
        if ($request->has('referral_code') && $request->referral_code) {
            $referrer = User::where('referral_code', $request->referral_code)->first();

            if ($referrer && $referrer->id !== $user->id) {
                $referralReward = 50;

                // Yeni kullanıcıya coin ekle
                $user->increment('coins', $referralReward);
                $user->has_used_referral = true;
                $user->save();

                // Referans veren kullanıcıya coin ekle
                $referrer->increment('coins', $referralReward);

                // Coin history kayıtları
                // Kullanıcı zaten 2000 coin ile kaydedildi, sonra 50 eklendi
                $userBalanceBefore = $user->coins - $referralReward;
                $userBalanceAfter = $user->coins;

                \App\Models\CoinHistory::create([
                    'user_id' => $user->id,
                    'coin_amount' => $referralReward,
                    'transaction_type' => 'bonus',
                    'status' => 'completed',
                    'description' => 'Referans kodu kullanımı',
                    'metadata' => [
                        'referrer_id' => $referrer->id,
                        'referral_code' => $request->referral_code
                    ],
                    'balance_before' => $userBalanceBefore,
                    'balance_after' => $userBalanceAfter
                ]);

                $referrerBalanceBefore = $referrer->coins - $referralReward;
                $referrerBalanceAfter = $referrer->coins;

                \App\Models\CoinHistory::create([
                    'user_id' => $referrer->id,
                    'coin_amount' => $referralReward,
                    'transaction_type' => 'bonus',
                    'status' => 'completed',
                    'description' => 'Referans kodu ile yeni kullanıcı kazandırma',
                    'metadata' => [
                        'referred_user_id' => $user->id,
                        'referral_code' => $request->referral_code
                    ],
                    'balance_before' => $referrerBalanceBefore,
                    'balance_after' => $referrerBalanceAfter
                ]);
            }
        }

        // Assign default role
        $user->assignRole('uye');

        $token = $user->createToken('bilbakalim')->plainTextToken;

        return $this->response->withData(true, "Kullanıcı başarılı bir şekilde oluşturuldu.", [
            'user' => UserResource::make($user),
            'accessToken' => $token
        ]);
    }
    /**
     * @OA\Post(
     *     path="/api/me/update",
     *     summary="Update User Profile",
     *     description="Update authenticated user's profile information",
     *     operationId="updateProfile",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", description="İsim (opsiyonel)", example="John"),
     *                 @OA\Property(property="email", type="string", format="email", description="E-posta (opsiyonel)", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", description="Telefon numarası (opsiyonel)", example="+905551234567"),
     *                 @OA\Property(property="password", type="string", format="password", description="Şifre (opsiyonel)", example="newpassword123"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", description="Şifre tekrar (opsiyonel, password varsa zorunlu)", example="newpassword123"),
     *                 @OA\Property(property="profile_image", type="string", format="binary", description="Profil resmi (opsiyonel, max: 2MB). Dosya olarak gönderilebilir veya base64 string formatında gönderilebilir (data:image/png;base64,...)", example=""),
     *                 @OA\Property(property="avatar_id", type="integer", description="Avatar ID (opsiyonel). Eğer avatar_id gönderilirse, avatar'ın görseli profile_image olarak ayarlanır", example=1),
     *                 @OA\Property(property="device_id", type="string", description="FCM Device ID (opsiyonel)", example="fcm-device-token-12345")
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", description="İsim (opsiyonel)", example="John"),
     *                 @OA\Property(property="email", type="string", format="email", description="E-posta (opsiyonel)", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", description="Telefon numarası (opsiyonel)", example="+905551234567"),
     *                 @OA\Property(property="password", type="string", format="password", description="Şifre (opsiyonel)", example="newpassword123"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", description="Şifre tekrar (opsiyonel, password varsa zorunlu)", example="newpassword123"),
     *                 @OA\Property(property="profile_image", type="string", description="Profil resmi base64 string formatında (opsiyonel, max: 2MB). Format: data:image/png;base64,... veya sadece base64 string", example="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA..."),
     *                 @OA\Property(property="avatar_id", type="integer", description="Avatar ID (opsiyonel). Eğer avatar_id gönderilirse, avatar'ın görseli profile_image olarak ayarlanır", example=1),
     *                 @OA\Property(property="device_id", type="string", description="FCM Device ID (opsiyonel)", example="fcm-device-token-12345")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", description="İsim (opsiyonel)", example="John"),
     *             @OA\Property(property="email", type="string", format="email", description="E-posta (opsiyonel)", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", description="Telefon numarası (opsiyonel)", example="+905551234567"),
     *             @OA\Property(property="password", type="string", format="password", description="Şifre (opsiyonel)", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", description="Şifre tekrar (opsiyonel, password varsa zorunlu)", example="newpassword123"),
     *             @OA\Property(property="avatar_id", type="integer", description="Avatar ID (opsiyonel). Eğer avatar_id gönderilirse, avatar'ın görseli profile_image olarak ayarlanır", example=1),
     *             @OA\Property(property="device_id", type="string", description="FCM Device ID (opsiyonel)", example="fcm-device-token-12345")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Kullanıcı bilgilerini bir şekilde güncellediniz."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function edit(UserUpdateRequest $request)
    {
        $user = $this->service->edit($request);

        return $this->response->withData(
            true,
            "Kullanıcı bilgilerini bir şekilde güncellediniz.",
            [
                'user' => UserResource::make($user)
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/auth/me",
     *     summary="User Detail",
     *     description="Get user detail",
     *     operationId="detail",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Kullanıcı detayı başarılı bir şekilde listelendi."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Test User"),
     *                 @OA\Property(property="coins", type="integer", example=1200),
     *                 @OA\Property(property="diamonds", type="integer", example=10),
     *                 @OA\Property(property="is_premium", type="boolean", example=true, description="RevenueCat sonucuna göre hesaplanan premium durumu"),
     *                 @OA\Property(property="revenuecat", type="object",
     *                     @OA\Property(property="checked", type="boolean", example=true),
     *                     @OA\Property(property="is_premium", type="boolean", example=true),
     *                     @OA\Property(property="entitlements", type="array", @OA\Items(type="string", example="premium")),
     *                     @OA\Property(property="error", type="string", nullable=true, example=null)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function detail()
    {
        if (!$this->user) {
            return $this->response->withData(
                false,
                "Kullanıcı bulunamadı.",
                []
            );
        }

        $resourceData = UserResource::make($this->user)->resolve();
        $revenueCat = $this->fetchRevenueCatPremiumStatus($this->user);

        // RevenueCat sonucu geldiyse premium bilgisini onunla güncelle
        if ($revenueCat['checked'] === true) {
            $resourceData['is_premium'] = $revenueCat['is_premium'];
        }

        $resourceData['revenuecat'] = $revenueCat;

        return $this->response->withData(true, "Kullanıcı detayı başarılı bir şekilde listelendi.", $resourceData);
    }

    private function fetchRevenueCatPremiumStatus(User $user): array
    {
        $apiKey = config('services.revenuecat.api_key');

        if (empty($apiKey)) {
            return [
                'checked' => false,
                'is_premium' => (bool) $user->is_premium,
                'entitlements' => [],
                'error' => 'RevenueCat API key tanimli degil.',
            ];
        }

        try {
            $appUserId = (string) $user->id;
            $response = Http::timeout(8)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                ])
                ->get("https://api.revenuecat.com/v1/subscribers/{$appUserId}");


            if (!$response->successful()) {
                return [
                    'checked' => false,
                    'is_premium' => (bool) $user->is_premium,
                    'entitlements' => [],
                    'error' => 'RevenueCat response status: ' . $response->json(),
                ];
            }

            $body = $response->json();
            $activeEntitlements = data_get($body, 'subscriber.entitlements', []);
            $activeEntitlementKeys = array_keys(is_array($activeEntitlements) ? $activeEntitlements : []);
            $isPremium = !empty($activeEntitlementKeys);

            return [
                'checked' => true,
                'is_premium' => $isPremium,
                'entitlements' => $activeEntitlementKeys,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('RevenueCat me check failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'checked' => false,
                'is_premium' => (bool) $user->is_premium,
                'entitlements' => [],
                'error' => 'RevenueCat baglanti hatasi.',
            ];
        }
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="User Logout",
     *     description="Logout authenticated user and revoke all access tokens",
     *     operationId="logout",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Başarılı bir şekilde çıkış yapıldı."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function logout()
    {
        $this->user->tokens()->delete();

        return $this->response->withData(
            true,
            "Başarılı bir şekilde çıkış yapıldı.",
            []
        );
    }

    /**
     * Web Login for Admin Panel
     */
    public function login_post(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');
        $remember = $request->has('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            // Check if user has admin or staff role
            if (!$user->hasAnyRole(['admin', 'personel'])) {
                Auth::logout();
                return redirect()->back()->with('error', 'Bu panele erişim yetkiniz bulunmamaktadır.');
            }

            $user->last_login_at = now();
            $user->save();

            return redirect()->intended(route('admin.dashboard'));
        }

        return redirect()->back()
            ->withInput($request->only('email'))
            ->with('error', 'Email adresi veya şifre hatalı.');
    }

    /**
     * @OA\Get(
     *     path="/api/referral/my-code",
     *     summary="Kullanıcının kendi referral kodunu getir",
     *     description="Kullanıcının kendi unique referral kodunu döndürür",
     *     tags={"Referral"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="referral_code", type="string", example="ABC12345")
     *             )
     *         )
     *     )
     * )
     */
    public function getMyReferralCode(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Kullanıcı bulunamadı.'
            ], 401);
        }

        // Eğer referral kodu yoksa oluştur
        if (!$user->referral_code) {
            $user->referral_code = User::generateReferralCode();
            $user->save();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/referral/can-use",
     *     summary="Referral kod kullanabilir mi kontrolü",
     *     description="Kullanıcının daha önce referral kod kullanıp kullanmadığını kontrol eder. Eğer false dönerse, referral kod girişi gösterilmez.",
     *     tags={"Referral"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="can_use", type="boolean", example=false, description="false ise referral kod girişi gösterilmez")
     *             )
     *         )
     *     )
     * )
     */
    public function canUseReferralCode(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Kullanıcı bulunamadı.'
            ], 401);
        }

        // has_used_referral false ise referral kod kullanabilir
        $canUse = !$user->has_used_referral;

        return response()->json([
            'success' => true,
            'data' => [
                'can_use' => $canUse
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/auth/delete-account",
     *     summary="Hesap Silme",
     *     description="Kullanıcının hesabını kalıcı olarak siler. Tüm token'lar iptal edilir ve kullanıcı verileri silinir.",
     *     operationId="deleteAccount",
     *     tags={"Auth"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="password", type="string", format="password", description="Şifre doğrulama (opsiyonel)", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Hesap başarıyla silindi",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Hesabınız başarıyla silindi."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Şifre yanlış",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Şifre yanlış.")
     *         )
     *     )
     * )
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Kullanıcı bulunamadı.'
            ], 401);
        }

        // Şifre doğrulama (opsiyonel ama önerilir)
        if ($request->has('password')) {
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Şifre yanlış.'
                ], 400);
            }
        }

        // Kullanıcının tüm token'larını sil
        $user->tokens()->delete();

        // Kullanıcıyı sil
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Hesabınız başarıyla silindi.',
        ]);
    }
}
