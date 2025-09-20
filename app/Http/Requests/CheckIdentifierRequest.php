<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckIdentifierRequest extends FormRequest
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
            'type.required' => 'Tür seçimi gereklidir.',
            'type.string' => 'Tür metin formatında olmalıdır.',
            'type.in' => 'Tür email veya phone olmalıdır.'
        ];
    }
}
