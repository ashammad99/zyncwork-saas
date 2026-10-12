<?php

namespace App\Features\TenantExport\Services;

use App\Core\BaseService;
use App\Core\Support\CsvCell;
use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Comment\Models\Comment;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Projects\Admin\Models\Project;
use App\Features\SystemSettings\Admin\Models\Setting;
use App\Features\TaskAdmin\Models\Task;
use App\Features\Tenancy\Models\Tenant;
use App\Features\TenantExport\Models\TenantExport;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Writes one tenant's data as a ZIP of CSVs on the private local disk.
 *
 * Every file is an allow-list of columns, so a column added later (or a
 * secret: password hashes, remember and invitation tokens, IP addresses,
 * stored file paths) is never exported unless someone adds it here on purpose.
 *
 * Model-backed tables are read through their models, so the tenant scope
 * applies (and fails closed with no tenant). The two tables without a model
 * (project_members, attachments) filter on tenant_id explicitly.
 * Rows are streamed (lazyById / lazy), so memory stays flat at any size.
 */
class TenantExportBuilder extends BaseService
{
    private const CHUNK = 1000;

    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    /**
     * @return array{path: string, size: int, row_counts: array<string, int>}
     */
    public function build(TenantExport $export): array
    {
        $tenantId = $this->tenantContext->idOrFail();

        if ((int) $export->tenant_id !== $tenantId) {
            throw new RuntimeException("Export {$export->id} belongs to another tenant than the current one.");
        }

        $disk = Storage::disk('local');
        $workDir = $disk->path("exports/tmp/{$export->id}");
        File::ensureDirectoryExists($workDir);

        try {
            $rowCounts = [];
            foreach ($this->datasets($tenantId) as $name => [$columns, $query]) {
                $rowCounts[$name] = $this->writeCsv("{$workDir}/{$name}.csv", $columns, $query);
            }

            file_put_contents("{$workDir}/README.txt", $this->readme(Tenant::findOrFail($tenantId), $rowCounts));

            $relative = "exports/{$tenantId}/".Str::random(40).'.zip';
            File::ensureDirectoryExists(dirname($disk->path($relative)));
            $this->zip($workDir, $disk->path($relative), array_keys($rowCounts));

            return ['path' => $relative, 'size' => $disk->size($relative), 'row_counts' => $rowCounts];
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * File name => [columns, query]. Queries select exactly the listed columns.
     *
     * @return array<string, array{0: list<string>, 1: Builder}>
     */
    private function datasets(int $tenantId): array
    {
        $sets = [
            'users' => [User::class, ['id', 'name', 'email', 'phone', 'role', 'department_id', 'is_active', 'daily_capacity_hours', 'email_verified_at', 'last_login_at', 'created_at', 'updated_at']],
            'departments' => [Department::class, ['id', 'name', 'description', 'color', 'manager_id', 'created_at', 'updated_at']],
            'projects' => [Project::class, ['id', 'name', 'description', 'status', 'department_id', 'owner_id', 'deadline', 'created_at', 'updated_at']],
            'tasks' => [Task::class, ['id', 'project_id', 'parent_task_id', 'depends_on_task_id', 'title', 'description', 'status', 'priority', 'impact', 'effort', 'estimated_hours', 'due_date', 'assignee_id', 'created_by', 'is_recurring', 'recurrence_rule', 'recurrence_ends_at', 'completed_at', 'created_at', 'updated_at']],
            'time_logs' => [TimeLog::class, ['id', 'task_id', 'user_id', 'started_at', 'ended_at', 'duration_minutes', 'entry_type', 'notes', 'approved_by', 'created_at', 'updated_at']],
            'comments' => [Comment::class, ['id', 'task_id', 'user_id', 'parent_id', 'body', 'edited_at', 'created_at', 'updated_at']],
            'settings' => [Setting::class, ['id', 'setting_key', 'setting_value', 'updated_at']],
            'activity_logs' => [ActivityLog::class, ['id', 'user_id', 'platform_actor', 'action', 'description', 'subject_type', 'model_type', 'model_id', 'old_value', 'new_value', 'created_at']],
            'user_invitations' => [UserInvitation::class, ['id', 'email', 'role', 'department_id', 'invited_by', 'expires_at', 'accepted_at', 'accepted_user_id', 'revoked_at', 'created_at']],
        ];

        $datasets = [];
        foreach ($sets as $name => [$model, $columns]) {
            // toBase() keeps the model's global scopes, so the tenant scope still applies.
            $datasets[$name] = [$columns, $model::query()->toBase()->select($columns)];
        }

        // No model behind these two: the tenant filter must be explicit.
        $datasets['project_members'] = [
            ['project_id', 'user_id', 'role', 'joined_at'],
            DB::table('project_members')->where('tenant_id', $tenantId)->select('project_id', 'user_id', 'role', 'joined_at'),
        ];
        $datasets['attachments'] = [
            ['id', 'task_id', 'user_id', 'filename', 'mime_type', 'size_bytes', 'created_at'],
            DB::table('attachments')->where('tenant_id', $tenantId)->select('id', 'task_id', 'user_id', 'filename', 'mime_type', 'size_bytes', 'created_at'),
        ];

        return $datasets;
    }

    /** @param  list<string>  $columns */
    private function writeCsv(string $path, array $columns, Builder $query): int
    {
        $out = fopen($path, 'w');
        // UTF-8 BOM so Excel opens Arabic and accented text correctly.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $columns);

        $rows = in_array('id', $columns, true)
            ? $query->lazyById(self::CHUNK)
            : $query->orderBy($columns[0])->orderBy($columns[1])->lazy(self::CHUNK);

        $count = 0;
        foreach ($rows as $row) {
            $row = (array) $row;
            fputcsv($out, CsvCell::row(array_map(fn ($column) => $row[$column] ?? null, $columns)));
            $count++;
        }

        fclose($out);

        return $count;
    }

    /** @param  list<string>  $names */
    private function zip(string $workDir, string $target, array $names): void
    {
        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        $zip->addFile("{$workDir}/README.txt", 'README.txt');
        foreach ($names as $name) {
            $zip->addFile("{$workDir}/{$name}.csv", "{$name}.csv");
        }

        if (! $zip->close()) {
            throw new RuntimeException('Could not write the export archive.');
        }
    }

    /** @param  array<string, int>  $rowCounts */
    private function readme(Tenant $tenant, array $rowCounts): string
    {
        $subscription = $tenant->subscription()->with('plan')->first();
        $lines = [
            config('app.name').' data export',
            '',
            "Organisation: {$tenant->name}",
            'Generated: '.now()->toIso8601String(),
            'Plan: '.($subscription?->plan?->name ?? 'none').' ('.($subscription?->status ?? 'none').", {$subscription?->seats} seats)",
            '',
            'Files (one row per record; the first row holds the column names):',
        ];
        foreach ($rowCounts as $name => $count) {
            $lines[] = sprintf('  %-22s %d rows', "{$name}.csv", $count);
        }

        return implode("\n", array_merge($lines, [
            '',
            'Format: CSV, UTF-8 with a byte-order mark. IDs link the files together',
            '(for example tasks.project_id -> projects.id).',
            "Text that starts with = + - or @ has a leading apostrophe (') so",
            'spreadsheet programs show it as text instead of running it as a formula.',
            '',
            'Not included: passwords, login and invitation tokens, IP addresses,',
            'profile pictures and stored file paths. Attachments are listed by name',
            'and size only.',
            '',
        ]));
    }
}
