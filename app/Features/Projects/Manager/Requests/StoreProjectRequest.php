<?php

namespace App\Features\Projects\Manager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'manager' && $this->user()->department_id !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,on_hold,archived'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'status.required' => 'Project status is required.',
            'status.in' => 'Status must be active, on_hold, or archived.',
            'deadline.after_or_equal' => 'Deadline cannot be in the past.',
        ];
    }
}
