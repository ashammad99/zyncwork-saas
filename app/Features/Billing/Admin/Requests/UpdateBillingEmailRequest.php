<?php

namespace App\Features\Billing\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:admin on the route
    }

    public function rules(): array
    {
        return [
            // Not unique: several companies may share one accountant.
            'billing_email' => ['required', 'string', 'email:rfc', 'max:255'],
        ];
    }
}
