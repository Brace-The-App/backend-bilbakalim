<?php

namespace App\Http\Services;

use App\Http\Custom\Response;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserUpdateRequest;
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
     * @param UserUpdateRequest $request
     * @return User
     */
    public function edit(UserUpdateRequest $request)
    {
        $user = User::findOrFail(Auth::user()->id);

        $input = $request->only(['name', 'email', 'phone', 'password', 'device_id']);

        if (!empty($input['password'])) {
            $input['password'] = Hash::make($input['password']);
        } else {
            unset($input['password']);
        }

        // Profile image upload
        if ($request->hasFile('profile_image')) {
            $image = $request->file('profile_image');
            $imageName = time() . '_' . $image->getClientOriginalName();
            $image->storeAs('public/profile_images', $imageName);
            $input['profile_image'] = 'profile_images/' . $imageName;
        }

        $user->update($input);

        return $user;
    }



}
