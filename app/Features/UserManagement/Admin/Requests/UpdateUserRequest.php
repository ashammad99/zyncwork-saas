<?php

namespace App\Features\UserManagement\Admin\Requests;

use App\Core\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$this->route('user')->id,
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,manager,employee',
            'is_active' => 'required|boolean',
            'daily_capacity_hours' => ['required', 'integer', 'min:1', 'max:12'],
            'department_id' => ['nullable', TenantRule::exists('departments')],

        ];
    }
}
