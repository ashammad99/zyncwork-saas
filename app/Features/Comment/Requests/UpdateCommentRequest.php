<?php

namespace App\Features\Comment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CommentPolicy::update() checked in controller
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Comment body cannot be empty.',
            'body.max'      => 'Comment must not exceed 5,000 characters.',
        ];
    }
}







