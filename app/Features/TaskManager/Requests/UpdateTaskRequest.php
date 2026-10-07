<?php

namespace App\Features\TaskManager\Requests;

use App\Core\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taskId = $this->route('task')?->id;

        return [
            'project_id' => ['sometimes', 'integer', TenantRule::exists('projects')],
            'assignee_id' => ['nullable', 'integer', TenantRule::exists('users')],
            'depends_on_task_id' => ['nullable', 'integer', TenantRule::exists('tasks'), "not_in:{$taskId}"],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'impact' => ['required', 'in:low,high'],
            'effort' => ['required', 'in:low,high'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'due_date' => ['nullable', 'date'],
            'is_recurring' => ['boolean'],
            'recurrence_rule' => ['nullable', 'required_if:is_recurring,true', 'in:daily,weekly,monthly'],
            'recurrence_ends_at' => ['nullable', 'date'],
        ];
    }
}
