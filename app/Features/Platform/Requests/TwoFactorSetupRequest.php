<?php

namespace App\Features\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
        ];
    }
}
