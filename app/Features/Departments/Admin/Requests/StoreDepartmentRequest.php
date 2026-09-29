<?php

namespace App\Features\Departments\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Core\Tenancy\TenantRule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manager_id' => ['nullable', TenantRule::exists('users')],
            'name' => ['required', 'string', 'max:255', TenantRule::unique('departments', 'name')],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
