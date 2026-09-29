<?php

namespace App\Features\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Access is checked by the route's can:platform.suspend_tenants middleware. */
class SuspendTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
