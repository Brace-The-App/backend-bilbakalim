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
     *     description="Authenticate user and return access token",
     *     operationId="login",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 required={"email","password"},
     *                 @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *                 @OA\Property(property="password", type="string", format="password", example="password123")
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
        // $user = $this->service->login($request);
        if (Auth::attemptWhen([
            'email' => $request->email,
            'password' => $request->password,
        ], function (User $user) {
            return true;
        })) {
            $user = $request->user();
            $token = $user->createToken('bilbakalim');
      
            $responseData = [];
            $responseData['user'] = UserResource::make($user);
            $responseData['accessToken'] = $token->plainTextToken;
            return Response::withData(true, "Tebrikler başarılı bir şekilde giriş yaptınız.", $responseData);
        } else {
            return Response::error([
                'email' => ["Incorrect"],
                'password' => ["Incorrect"]
            ], "Lütfen bilgileri kontrol ediniz.");
        }
        // if (!Auth::attempt($request->only('email', 'password'))) {
        //     return Response::withoutData(false, "Lütfen bilgileri kontrol ediniz.");
        // }


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
     *                 @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
     *             )
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
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
        ]);

        // Assign default role
        $user->assignRole('uye');

        $token = $user->createToken('bilbakalim')->plainTextToken;

        return $this->response->withData(true, "Kullanıcı başarılı bir şekilde oluşturuldu.", [
            'user' => UserResource::make($user),
            'accessToken' => $token
        ]);
    }
    /**
     * Profile Update
     *
     * Profile Update the user with given data if valid.
     *
     * @bodyParam name string Name for the user.
     * @bodyParam surname string Surname for the user.
     * @bodyParam phone string Phone for the user.
     * @bodyParam email    string The email of the user.
     * @bodyParam password string Password for the user.
     *
     * @param Request $request
     * @return void
     * @authenticated
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
     * Logout
     *
     * Logout
     *
     * @authenticated
     *
     * @return void
     *
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
