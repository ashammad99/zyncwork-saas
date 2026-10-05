<?php

namespace App\Features\Projects\Manager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $this->user()->role === 'manager'
            && $this->user()->department_id === $project->department_id;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,on_hold,archived'],
            'deadline' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'status.required' => 'Project status is required.',
            'status.in' => 'Status must be active, on_hold, or archived.',
        ];
    }
}
