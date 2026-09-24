<?php

namespace App\Features\TaskAdmin\Requests;

use App\Features\TaskAdmin\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'project_id'          => ['required', 'integer', 'exists:projects,id'],
            'assignee_id'         => ['nullable', 'integer', 'exists:users,id'],
            'depends_on_task_id'  => ['nullable', 'integer', 'exists:tasks,id'],
            'title'               => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'status'              => ['required', Rule::in(Task::STATUSES)],

            'priority'            => ['required', Rule::in(Task::PRIORITIES)],
            'impact'              => ['required', Rule::in(['low', 'high'])],
            'effort'              => ['required', Rule::in(['low', 'high'])],
            'estimated_hours'     => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'due_date'            => ['nullable', 'date'],
            'is_recurring'        => ['boolean'],
            'recurrence_rule'     => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_ends_at'  => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required'  => 'Please select a project.',
            'title.required'       => 'Task title is required.',
            'priority.in'          => 'Invalid priority value.',
            'status.required'     => 'Please select a status.',
            'status.in'           => 'Invalid status value.',
        ];
    }
}
