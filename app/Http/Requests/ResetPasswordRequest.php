<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'reset_token' => ['required', 'string', 'size:64'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'type' => ['required', 'string', 'in:email,phone']
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifier.required' => 'E-posta veya telefon numarası gereklidir.',
            'identifier.string' => 'E-posta veya telefon numarası metin formatında olmalıdır.',
            'identifier.max' => 'E-posta veya telefon numarası en fazla 255 karakter olabilir.',
            'reset_token.required' => 'Sıfırlama token\'ı gereklidir.',
            'reset_token.string' => 'Sıfırlama token\'ı metin formatında olmalıdır.',
            'reset_token.size' => 'Sıfırlama token\'ı 64 karakter olmalıdır.',
            'new_password.required' => 'Yeni şifre gereklidir.',
            'new_password.string' => 'Yeni şifre metin formatında olmalıdır.',
            'new_password.confirmed' => 'Şifre onayı eşleşmiyor.',
            'new_password.min' => 'Şifre en az 8 karakter olmalıdır.',
            'new_password.letters' => 'Şifre en az bir harf içermelidir.',
            'new_password.mixed_case' => 'Şifre büyük ve küçük harf içermelidir.',
            'new_password.numbers' => 'Şifre en az bir rakam içermelidir.',
            'new_password.symbols' => 'Şifre en az bir özel karakter içermelidir.',
            'type.required' => 'Tür seçimi gereklidir.',
            'type.string' => 'Tür metin formatında olmalıdır.',
            'type.in' => 'Tür email veya phone olmalıdır.'
        ];
    }
}
