<?php

namespace App\Http\Services;

use App\Http\Custom\Response;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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

        $input = [];

        // Sadece gönderilen ve dolu alanları ekle
        if ($request->filled('name')) {
            $input['name'] = $request->name;
        }

        if ($request->filled('email')) {
            $input['email'] = $request->email;
        }

        if ($request->filled('phone')) {
            $input['phone'] = $request->phone;
        }

        if ($request->filled('password')) {
            $input['password'] = Hash::make($request->password);
        }

        if ($request->filled('device_id')) {
            $input['device_id'] = $request->device_id;
        }

        // Önce dosya olarak gönderilmiş mi kontrol et (multipart/form-data)
        if ($request->hasFile('profile_image')) {
            // Eski profil resmini sil
            if ($user->profile_image && Storage::disk('public')->exists($user->profile_image)) {
                Storage::disk('public')->delete($user->profile_image);
            }

            $image = $request->file('profile_image');
            $path = $image->store('profile_images', 'public');
            $input['profile_image'] = $path;
        }


        // Sadece dolu input varsa güncelle
        if (!empty($input)) {
            $user->update($input);
        }

        return $user->fresh();
    }



}
