<?php

namespace App\Features\Profile\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users,email,' . Auth::id(),
            'phone'         => 'nullable|string|max:20',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email is invalid.',
            'phone.required' => 'Phone is required.',
            'profile_image.required' => 'Profile image is required.',
            'profile_image.image' => 'Profile image must be an image.',
            'profile_image.mimes' => 'Profile image must be a valid image type.',
            'profile_image.max' => 'Profile image must be less than 2MB.',
        ];
    }
}
