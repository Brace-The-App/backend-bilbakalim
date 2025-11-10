<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends BaseRequest
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
        if ($this->has('name') && $this->name === '') {
            $this->merge(['name' => null]);
        }

        if ($this->has('email') && $this->email === '') {
            $this->merge(['email' => null]);
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
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'phone' => [
                'required',
                'regex:/^(05[0-9]{9}|90[0-9]{10})$/',
                'unique:users,phone',
            ],
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'password' => [
                'nullable',
                'sometimes',
                'min:8',
                'regex:/^.*(?=.{3,})(?=.*[a-zA-Z])(?=.*[0-9]).*$/'
            ],
            'password_confirmation' => 'nullable|required_with:password|same:password',
            'device_id' => 'nullable|string|max:255',
        ];
    }

    public function messages()
    {
        return [
            'password.regex' => 'En az 8 karakter, 1 rakam ve bir büyük harf girmelisiniz.',
            'phone.regex' => 'Geçerli bir telefon numarası giriniz.',
            'phone.unique' => 'Bu telefon numarası zaten kayıtlı.',
            'email.unique' => 'Bu email adresi zaten kayıtlı.',
        ];
    }
}
