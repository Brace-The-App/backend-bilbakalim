<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'type' => ['required', 'string', 'in:email,phone'],
            'purpose' => ['required', 'string', 'in:registration,login,update,password_reset']
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
            'code.required' => 'Doğrulama kodu gereklidir.',
            'code.string' => 'Doğrulama kodu metin formatında olmalıdır.',
            'code.size' => 'Doğrulama kodu 6 haneli olmalıdır.',
            'code.regex' => 'Doğrulama kodu sadece rakamlardan oluşmalıdır.',
            'type.required' => 'Tür seçimi gereklidir.',
            'type.string' => 'Tür metin formatında olmalıdır.',
            'type.in' => 'Tür email veya phone olmalıdır.',
            'purpose.required' => 'Amaç seçimi gereklidir.',
            'purpose.string' => 'Amaç metin formatında olmalıdır.',
            'purpose.in' => 'Amaç registration, login, update veya password_reset olmalıdır.'
        ];
    }
}
