<?php

namespace App\Features\UserManagement\Admin\Requests;

use App\Core\Tenancy\TenantRule;
use App\Features\Onboarding\Models\UserInvitation;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', UserInvitation::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => Str::lower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required', 'string', 'email', 'max:255',
                // Unique across the whole platform: one account per email.
                'unique:users,email',
                function (string $attribute, mixed $value, Closure $fail) {
                    // Tenant-scoped: an open invite elsewhere doesn't block this one.
                    if (UserInvitation::open()->where('email', $value)->exists()) {
                        $fail('An invitation to this email address is already pending. Resend it instead.');
                    }
                },
            ],
            'role' => ['required', 'in:admin,manager,employee'],
            // Managers without a department fail most project and task policies.
            'department_id' => ['nullable', 'required_if:role,manager', TenantRule::exists('departments')],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email address already has an account.',
            'department_id.required_if' => 'Managers need a department.',
        ];
    }
}
