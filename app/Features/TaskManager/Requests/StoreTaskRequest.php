<?php

namespace App\Features\TaskManager\Requests;

use App\Core\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', TenantRule::exists('projects')],
            'assignee_id' => ['nullable', 'integer', TenantRule::exists('users')],
            'depends_on_task_id' => ['nullable', 'integer', TenantRule::exists('tasks'), 'different:project_id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'impact' => ['required', 'in:low,high'],
            'effort' => ['required', 'in:low,high'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'is_recurring' => ['boolean'],
            'recurrence_rule' => ['nullable', 'required_if:is_recurring,true', 'in:daily,weekly,monthly'],
            'recurrence_ends_at' => ['nullable', 'date', 'after:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'Please select a project.',
            'title.required' => 'Task title is required.',
            'priority.in' => 'Invalid priority value.',
            'recurrence_rule.required_if' => 'Recurrence rule is required for recurring tasks.',
        ];
    }
}
