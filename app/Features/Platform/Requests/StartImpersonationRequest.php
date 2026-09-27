<?php

namespace App\Features\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Access is checked by the route's can:platform.impersonate middleware.
 * user_id is resolved inside the route's tenant (ImpersonationService), so
 * a user from any other tenant is simply not found.
 */
class StartImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            // Shown to the tenant in its activity log.
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
