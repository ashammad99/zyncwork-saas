<?php

namespace App\Features\Projects\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Core\Tenancy\TenantRule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['required', TenantRule::exists('departments')],
            'owner_id' => ['required', TenantRule::exists('users')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'on_hold', 'archived'])],
            'deadline' => ['nullable', 'date'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', TenantRule::exists('users')],
        ];
    }
}
