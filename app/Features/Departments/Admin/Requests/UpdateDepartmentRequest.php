<?php

namespace App\Features\Departments\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Core\Tenancy\TenantRule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $departmentId = $this->route('department')?->id ?? $this->route('department');

        return [
            'manager_id' => ['nullable', TenantRule::exists('users')],
            'name' => [
                'required',
                'string',
                'max:255',

                TenantRule::unique('departments', 'name')->ignore($departmentId),
            ],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
