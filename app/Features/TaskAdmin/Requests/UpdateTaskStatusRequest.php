<?php

namespace App\Features\TaskAdmin\Requests;

use App\Features\TaskAdmin\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Task::STATUSES)],
        ];
    }
}
