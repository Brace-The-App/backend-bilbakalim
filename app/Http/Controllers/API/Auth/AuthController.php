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
     *                 required={"name","email","phone","password","password_confirmation"},
     *                 @OA\Property(property="name", type="string", example="John"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+905551234567"),
     *                 @OA\Property(property="password", type="string", format="password", example="password123"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *                 @OA\Property(property="device_id", type="string", description="FCM Device ID (optional)", example="fcm-device-token-12345")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="+905551234567"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="device_id", type="string", description="FCM Device ID (optional)", example="fcm-device-token-12345")
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
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'device_id' => 'nullable|string|max:255',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->role_id = 3;
        $user->account_status = 'active';
        $user->password = bcrypt($request->password);
        $user->coins = 1000;
        if ($request->has('device_id') && $request->device_id) {
            $user->device_id = $request->device_id;
        }
        $user->save();

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
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string", example="John"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="phone", type="string", example="+905551234567"),
     *                 @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123"),
     *                 @OA\Property(property="profile_image", type="string", format="binary", description="Profile image file (max: 2MB)"),
     *                 @OA\Property(property="device_id", type="string", description="FCM Device ID (optional)", example="fcm-device-token-12345")
     *             )
     *         ),
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="phone", type="string", example="+905551234567"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="device_id", type="string", description="FCM Device ID (optional)", example="fcm-device-token-12345")
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
     *                 @OA\Property(property="user", type="object")
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

        return $this->response->withData(
            true,
            "Kullanıcı detayı başarılı bir şekilde listelendi.",
            UserResource::make($this->user)
        );
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
}
