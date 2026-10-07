<?php

namespace App\Features\TimeLog\Requests;

use App\Core\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkApproveTimeLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'time_log_ids' => ['required', 'array', 'min:1', 'max:500'],
            'time_log_ids.*' => ['required', 'integer', TenantRule::exists('time_logs')],
        ];
    }

    public function messages(): array
    {
        return [
            'time_log_ids.required' => 'Select at least one time entry to approve.',
            'time_log_ids.min' => 'Select at least one time entry to approve.',
            'time_log_ids.max' => 'You can approve at most 500 entries at once.',
            'time_log_ids.*.exists' => 'One or more selected entries no longer exist.',
        ];
    }
}
