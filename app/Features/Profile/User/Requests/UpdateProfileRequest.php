<?php

namespace App\Features\Profile\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
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
            'address.required' => 'Address is required.',
            'profile_image.required' => 'Profile image is required.',
            'profile_image.image' => 'Profile image must be an image.',
            'profile_image.mimes' => 'Profile image must be a valid image type.',
            'profile_image.max' => 'Profile image must be less than 2MB.',
        ];
    }
}
