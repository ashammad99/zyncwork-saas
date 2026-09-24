<?php

namespace App\Features\SystemSettings\Admin\Services;

use App\Core\BaseService;
use App\Features\SystemSettings\Admin\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService extends BaseService
{
    /**
     * Settings schema — drives both UI rendering and update validation.
     * Add/remove fields here; no other file changes needed.
     */
    public const SCHEMA = [
    'general' => [
        'label'       => 'General',
        'description' => 'Application identity and locale defaults.',
        'fields' => [
            'app.name'     => ['label' => 'Application Name', 'type' => 'text'],
            'app.timezone' => ['label' => 'Default Timezone', 'type' => 'select', 'options' => [
                'UTC', 'Asia/Hebron', 'Asia/Dubai', 'Europe/London', 'America/New_York', 'America/Los_Angeles',
            ]],
            'app.locale'   => ['label' => 'Default Locale', 'type' => 'select', 'options' => ['en', 'ar', 'fr']],
        ],
    ],
    'notifications' => [
        'label'       => 'Notifications',
        'description' => 'In-app polling and delivery cadence.',
        'fields' => [
            'notifications.poll_interval_seconds' => ['label' => 'Bell Poll Interval (seconds)', 'type' => 'number', 'min' => 10, 'max' => 600],
        ],
    ],
    'time_tracking' => [
        'label'       => 'Time Tracking',
        'description' => 'Active timer behavior and idle detection.',
        'fields' => [
            'timer.idle_threshold_hours' => ['label' => 'Idle Threshold (hours)',   'type' => 'number', 'min' => 1, 'max' => 24],
            'timer.auto_cap_hours'       => ['label' => 'Auto-Cap Duration (hours)', 'type' => 'number', 'min' => 1, 'max' => 24],
        ],
    ],
    'capacity' => [
        'label'       => 'Capacity & Workload',
        'description' => 'Daily capacity defaults and over-allocation thresholds.',
        'fields' => [
            'capacity.default_daily_hours'    => ['label' => 'Default Daily Capacity (hours)', 'type' => 'number', 'min' => 1,  'max' => 24],
            'capacity.warning_threshold_pct'  => ['label' => 'Warning Threshold (%)',          'type' => 'number', 'min' => 50, 'max' => 200],
            'capacity.overload_threshold_pct' => ['label' => 'Overload Threshold (%)',         'type' => 'number', 'min' => 50, 'max' => 300],
        ],
    ],
    'reports' => [
        'label'       => 'Reports & Export',
        'description' => 'Reporting features and export limits.',
        'fields' => [
            'export.max_date_range_days' => ['label' => 'Max Export Range (days)', 'type' => 'number', 'min' => 1, 'max' => 730],
            'reports.burndown_enabled'   => ['label' => 'Enable Burndown Charts',  'type' => 'boolean'],
        ],
    ],
    'uploads' => [
        'label'       => 'File Uploads',
        'description' => 'Allowed file types and size limits for attachments.',
        'fields' => [
            'file_upload.max_size_mb'   => ['label' => 'Max Upload Size (MB)',                 'type' => 'number', 'min' => 1, 'max' => 100],
            'file_upload.allowed_mimes' => ['label' => 'Allowed MIME Types (comma-separated)', 'type' => 'text'],
        ],
    ],
    'email' => [
        'label'       => 'Email',
        'description' => 'Outbound email configuration.',
        'fields' => [
            'email.from_address' => ['label' => 'From Address', 'type' => 'email'],
        ],
    ],
    'security' => [
        'label'       => 'Security',
        'description' => 'Authentication and account protection.',
        'fields' => [
            'mfa.enabled' => ['label' => 'Enable Multi-Factor Authentication', 'type' => 'boolean'],
        ],
    ],
];

    public function grouped(): array
{
    $values = Setting::allKeyed();

    return collect(self::SCHEMA)->map(function (array $group) use ($values) {
        $group['fields'] = collect($group['fields'])->map(function (array $field, string $key) use ($values) {
            $field['key']   = $key;
            $field['value'] = $values[$key] ?? '';
            return $field;
        })->values()->all();

        return $group;
    })->all();
}

    public function flat(): array
{
    $values = Setting::allKeyed();

    return collect(self::SCHEMA)
        ->flatMap(fn ($group) => $group['fields'])
        ->keys()
        ->mapWithKeys(fn ($key) => [$key => $values[$key] ?? ''])
        ->all();
}

    public function update(array $payload): void
{
    $rows = [];
    foreach ($payload as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        $rows[] = ['setting_key' => $key, 'setting_value' => (string) $value];
    }

    if ($rows === []) {
        return;
    }

    // Single atomic upsert on the unique setting_key; updateOrCreate's
    // select-then-insert could hit a duplicate-key error under concurrent saves.
    Setting::upsert($rows, ['setting_key'], ['setting_value']);

    // upsert() bypasses model events, so flush the cache the saved() hook would have.
    Cache::forget(Setting::CACHE_KEY);
}

    public static function fieldsMap(): array
{
    return collect(self::SCHEMA)
        ->flatMap(fn ($group) => $group['fields'])
        ->all();
}
}
