<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'nickname.regex'  => 'O nickname só pode ter letras, números, _ e -.',
            'nickname.unique' => 'Este nickname já está em uso.',
            'nickname.min'    => 'O nickname deve ter pelo menos 3 caracteres.',
            'nickname.max'    => 'O nickname pode ter no máximo 30 caracteres.',
        ];
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'min:3', 'max:30', 'unique:users,nickname', 'regex:/^[a-zA-Z0-9_\-]+$/'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
