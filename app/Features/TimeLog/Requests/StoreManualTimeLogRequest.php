<?php

namespace App\Features\TimeLog\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreManualTimeLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['admin', 'manager', 'employee']);
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:' . Carbon::today()->toDateString()],
            'started_at' => ['required', 'date_format:H:i'],
            'ended_at' => ['required', 'date_format:H:i', 'after:started_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.before_or_equal' => 'Cannot log future dates.',
            'started_at.date_format' => 'Start time must be in HH:MM format.',
            'ended_at.date_format' => 'End time must be in HH:MM format.',
            'ended_at.after' => 'End time must be after start time.',
        ];
    }
}
