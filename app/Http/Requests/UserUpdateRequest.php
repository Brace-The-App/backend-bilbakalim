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
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Boş string'leri null'a çevir
        if ($this->has('name') && $this->name === '') {
            $this->merge(['name' => null]);
        }
        
        if ($this->has('email') && $this->email === '') {
            $this->merge(['email' => null]);
        }
        
        if ($this->has('phone') && $this->phone === '') {
            $this->merge(['phone' => null]);
        }
        
        if ($this->has('password') && $this->password === '') {
            $this->merge(['password' => null]);
        }
        
        if ($this->has('password_confirmation') && $this->password_confirmation === '') {
            $this->merge(['password_confirmation' => null]);
        }
        
        if ($this->has('device_id') && $this->device_id === '') {
            $this->merge(['device_id' => null]);
        }
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
            'phone' => 'nullable|string',
            'password' => 'nullable|string|min:6',
            'password_confirmation' => 'nullable|required_with:password|same:password',
            // profile_image hem dosya hem de base64 string olarak kabul edilebilir
            'profile_image' => 'nullable',
            'device_id' => 'nullable|string|max:255',
        ];
    }
    
    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->sometimes('profile_image', 'image|max:2048', function ($input) {
            // Eğer profile_image bir dosya ise (multipart/form-data)
            return request()->hasFile('profile_image');
        });
    }
}
