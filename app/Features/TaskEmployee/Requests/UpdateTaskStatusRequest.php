<?php

namespace App\Features\TaskEmployee\Requests;

use App\Features\TaskAdmin\Models\Task;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskStatusRequest extends FormRequest
{
    /**
     * Authorization is handled by TaskPolicy via $this->authorize()
     * in the controller. This request only validates request shape.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                // Employees can only submit these three target statuses.
                // Backend service (EmployeeTaskService) enforces which
                // transitions are valid from the current status.
                'in:'.implode(',', [
                    Task::STATUS_TODO,
                    Task::STATUS_IN_PROGRESS,
                    Task::STATUS_IN_REVIEW,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Please select a target status.',
            'status.in' => 'You can only move tasks to To Do, In Progress, or In Review.',
        ];
    }
}
