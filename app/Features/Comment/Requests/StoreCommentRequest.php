<?php

namespace App\Features\Comment\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Tenancy\TenantRule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CommentPolicy::create() checked in controller
    }

    public function rules(): array
    {
        return [
            'body'      => ['required', 'string', 'min:1', 'max:5000'],
            'parent_id' => ['nullable', 'integer', TenantRule::exists('comments')],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Comment body cannot be empty.',
            'body.max'      => 'Comment must not exceed 5,000 characters.',
            'parent_id.exists' => 'The comment you are replying to does not exist.',
        ];
    }
}
