<?php

namespace App\Features\TaskManager\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:backlog,todo,in_progress,in_review,completed'],
        ];
    }
}
