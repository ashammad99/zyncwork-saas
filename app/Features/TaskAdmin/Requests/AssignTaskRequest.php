<?php

namespace App\Features\TaskAdmin\Requests;

use App\Core\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'assignee_id' => ['nullable', 'integer', TenantRule::exists('users')],
        ];
    }
}
