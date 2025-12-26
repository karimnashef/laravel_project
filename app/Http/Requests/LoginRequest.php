<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable','string','max:20','required_without:email'],
            'email' => ['nullable','email','required_without:name'],
            'password' => ['required','string','min:8'],
        ];
    }
}
