<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $userId = auth()->id();
        
        return [
            'name' => 'nullable|string',
            'email' => 'nullable|email|unique:users,email,' . $userId,
            'password' => 'nullable|string|min:6|confirmed',
            'phone' => 'nullable|string',
            'profile_image' => 'nullable|image|max:2048',
            'device_id' => 'nullable|string|max:255',
        ];
    }
}
