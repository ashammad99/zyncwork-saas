<?php

namespace App\Features\TaskAdmin\Requests;

use App\Features\TaskAdmin\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'project_id'          => ['sometimes', 'integer', 'exists:projects,id'],
            'assignee_id'         => ['nullable', 'integer', 'exists:users,id'],
            'depends_on_task_id'  => ['nullable', 'integer', 'exists:tasks,id',
                Rule::notIn([$this->route('task')->id]), // cannot depend on itself
            ],
            'title'               => ['sometimes', 'required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'status'              => ['sometimes', 'required', Rule::in(Task::STATUSES)],

            'priority'            => ['sometimes', 'required', Rule::in(Task::PRIORITIES)],
            'impact'              => ['sometimes', 'required', Rule::in(['low', 'high'])],
            'effort'              => ['sometimes', 'required', Rule::in(['low', 'high'])],
            'estimated_hours'     => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'due_date'            => ['nullable', 'date'],
            'is_recurring'        => ['boolean'],
            'recurrence_rule'     => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'recurrence_ends_at'  => ['nullable', 'date', 'after:today'],
        ];
    }
}
