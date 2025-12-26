<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\User;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();
        $ignore = $user ? $user->id : 'NULL';

        return [
            'name' => ['required','string','max:20'],
            'email' => ['required','email','unique:users,email,' . $ignore],
            'phone' => ['required','numeric','digits_between:10,15'],
            'key' => ['required'],
            'password' => ['sometimes','string','min:8','confirmed','regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W]).+$/']
        ];
    }
}
