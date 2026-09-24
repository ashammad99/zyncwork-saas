<?php

namespace App\Features\CacheManagement\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClearCacheRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:all,view,config,route,application'
        ];
    }
}
