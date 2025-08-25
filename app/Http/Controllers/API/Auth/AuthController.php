<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use App\Http\Custom\Response;
use App\Http\Repositories\UserRepository;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Http\Services\AuthServices;
use App\Models\Account;
use Illuminate\Support\Arr;

/**
 * @group Auth
 * @unauthenticated
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
     * Login
     *
     * Login the user with given data if valid.
     *
     * @bodyParam email    string The email of the user.
     * @bodyParam password string Password for the user.
     *
     * @param LoginRequest $request
     * @return void
     */
    /**
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
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
            $token = $user->createToken('ectaro');
            $user = $user->load('account');
      
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


    public function register(RegisterRequest $request)
    {
        $UserRepository = new UserRepository;
        $request['password'] = bcrypt($request['password']);
        $request['role'] = 'member';
        $request['status'] = 'active';
        $data = Arr::only($request->all(), ['role', 'password', 'status', 'account_id', 'email', 'phone', 'surname', 'name']);
        $user = $UserRepository->setNested(new Account(), $data['account_id'], 'account_id')->create($data);
        $request['accessToken'] = $user->createToken('ectaro')->plainTextToken;

        return $this->response->withData(true, "Kullanıcı başarılı bir şekilde oluşturuldu.", $request->all());
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
     * Profile
     *
     * Profile Detail
     *
     * @authenticated
     *
     * @return void
     *
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
}
