<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Boş string'leri null'a çevir
        if ($this->has('email') && $this->email === '') {
            $this->merge(['email' => null]);
        }
        
        if ($this->has('phone') && $this->phone === '') {
            $this->merge(['phone' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'type'     => 'required|string|in:email,phone',
            'email'    => 'required_if:type,email|nullable|string|email',
            'phone'    => [
                'required_if:type,phone',
                'nullable',
                'string',
                'regex:/^(05[0-9]{9}|90[0-9]{10})$/'
            ],
            'password' => 'required|string|min:6',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'phone.regex' => 'Telefon numarası 05 ile başlayan 11 haneli veya 90 ile başlayan 12 haneli olmalıdır.',
        ];
    }
}
