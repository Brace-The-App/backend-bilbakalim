<?php

namespace App\Http\Services;

use App\Http\Custom\Response;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthServices
{
    /**
     * @param LoginRequest $request
     * @return void
     */
    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->only('email', 'password'))) {
            return Response::withoutData(false, "Lütfen bilgileri kontrol ediniz.");
        }

        return ['success' => '', 'status' => true];
    }


    /**
     * @param LoginRequest $request
     * @return array
     */
    public function edit(LoginRequest $request)
    {
        $user = User::findOrFail(Auth::user()->id);

        $input = $request->only(['name', 'surname', 'email', 'phone', 'password']);

        if (!empty($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        } else {
            unset($input['password']);
        }

        $user->update($input);

        return $user;
    }



}
