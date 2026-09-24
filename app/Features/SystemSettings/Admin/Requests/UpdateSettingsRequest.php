<?php

namespace App\Features\SystemSettings\Admin\Requests;

use App\Features\SystemSettings\Admin\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{


    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        $rules = [];
        $fields = SettingsService::fieldsMap();

        foreach ($fields as $key => $config) {
            // The page posts flat keys like "app.name"; escape the dots so the
            // validator doesn't read them as nested paths (app → name).
            $rules[str_replace('.', '\.', $key)] = match ($config['type']) {
                'number' => ['required', 'numeric', 'min:' . ($config['min'] ?? 0), 'max:' . ($config['max'] ?? PHP_INT_MAX)],
                'boolean' => ['required', 'boolean'],
                'email' => ['required', 'email', 'max:255'],
                'select' => ['required', 'string', Rule::in($config['options'] ?? [])],
                default => ['required', 'string', 'max:255'],
            };
        }

        return $rules;
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated();

        foreach (SettingsService::fieldsMap() as $fieldKey => $config) {
            if ($config['type'] === 'boolean' && array_key_exists($fieldKey, $data)) {
                $data[$fieldKey] = $data[$fieldKey] ? '1' : '0';
            }
        }

        return $data;
    }
}
