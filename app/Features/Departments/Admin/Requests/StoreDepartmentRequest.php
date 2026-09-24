<?php

namespace App\Features\Departments\Admin\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manager_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }
}
