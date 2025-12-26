<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public registration allowed
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:20'],
            'email' => ['required','email','unique:users,email'],
            'phone' => ['required','numeric','digits_between:10,15'],
            'password' => ['required','string','min:8','confirmed','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W]).+$/']
        ];
    }
}
