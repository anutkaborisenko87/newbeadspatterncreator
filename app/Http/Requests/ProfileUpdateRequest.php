<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'firstName' => 'sometimes|string|min:2|max:255',
            'lastName' => 'sometimes|string|min:2|max:255',
            'login' => 'sometimes|string|min:5|max:255|unique:users,login,' . Auth::user()->id,
            'email' => 'sometimes|email|unique:users,email,' . Auth::user()->id,
            'oldPassword' => [
                'sometimes',
                'string',
                function ($attribute, $value, $fail) {
                    if (!Hash::check($value, Auth::user()->password)) {
                        $fail(__('main.profile_wrong_password'));
                    }
                },
            ],
            'password' => 'required_with:oldPassword|string|min:8|max:50',
            'confirmPassword' => 'required_with:password|string|min:8|max:50|same:password',
        ];
    }
}
