<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\SystemSettings\Admin\Models\Setting;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The upgrade path from the single-tenant schema. Uses DatabaseMigrations,
 * not RefreshDatabase: SQLite rebuilds tables to alter them, which it cannot
 * do inside RefreshDatabase's wrapping transaction (real migrate/rollback
 * runs have no such outer transaction).
 */
class TenantBackfillMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const TABLES = ['users', 'departments', 'projects', 'tasks', 'time_logs',
        'comments', 'notifications', 'activity_logs', 'settings', 'attachments', 'project_members'];

    /** Every migration from add_tenant_id onwards (the tenants table itself stays). */
    private const TENANCY_STEPS = 3;

    /** The platform-admin migrations that run after them, rolled back first. */
    private const PLATFORM_STEPS = 5;

    public function test_existing_single_tenant_rows_are_backfilled_into_one_new_tenant(): void
    {
        $this->seed();

        // Back to the pre-tenancy schema, with its data still in place.
        Artisan::call('migrate:rollback', ['--step' => self::PLATFORM_STEPS + self::TENANCY_STEPS, '--force' => true]);
        $this->assertFalse(Schema::hasColumn('projects', 'tenant_id'));
        $this->assertSame(3, DB::table('projects')->count());

        // Rows the seeder doesn't create, written as the old app would have.
        $projectId = DB::table('projects')->value('id');
        $userId = DB::table('users')->value('id');
        $taskId = DB::table('tasks')->insertGetId(['project_id' => $projectId, 'created_by' => $userId, 'title' => 'Old task']);
        DB::table('project_members')->insert(['project_id' => $projectId, 'user_id' => $userId]);
        DB::table('attachments')->insert(['task_id' => $taskId, 'user_id' => $userId, 'path' => 'a.pdf', 'filename' => 'a.pdf']);

        $tenantsBefore = DB::table('tenants')->max('id');

        Artisan::call('migrate', ['--force' => true]);

        $backfilled = DB::table('tenants')->max('id');
        $this->assertGreaterThan($tenantsBefore, $backfilled);
        $this->assertSame(config('app.name'), DB::table('tenants')->where('id', $backfilled)->value('name'));

        foreach (self::TABLES as $table) {
            $this->assertSame(
                DB::table($table)->count(),
                DB::table($table)->where('tenant_id', $backfilled)->count(),
                "{$table} rows not all backfilled"
            );
        }

        // The backfilled tenant's data is fully usable through the scoped models.
        app(TenantContext::class)->run($backfilled, function () {
            $this->assertSame(3, Project::count());
            $this->assertSame(1, Department::count());
            Setting::create(['setting_key' => 'app.name', 'setting_value' => 'After upgrade']);
            $this->assertSame('After upgrade', Setting::get('app.name'));
        });
    }

    public function test_migrating_an_empty_database_creates_no_tenant(): void
    {
        DB::table('tenants')->delete(); // drop the TestCase default tenant

        Artisan::call('migrate:rollback', ['--step' => self::PLATFORM_STEPS + self::TENANCY_STEPS, '--force' => true]);
        Artisan::call('migrate', ['--force' => true]);

        $this->assertSame(0, DB::table('tenants')->count());
    }
}
