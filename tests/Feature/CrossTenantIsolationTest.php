<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantIsolationException;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Comment\Models\Comment;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Notification\Events\NotificationCreated;
use App\Features\Notification\User\Models\Notification;
use App\Features\Projects\Admin\Models\Project;
use App\Features\SystemSettings\Admin\Models\Setting;
use App\Features\TaskAdmin\Models\Task;
use App\Features\Tenancy\Models\Tenant;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Tenant A's admin keeps the policy before() → true bypass, and still cannot
 * reach tenant B: not through listings, direct IDs, validation rules,
 * queued jobs, or the model layer itself.
 */
class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    private Tenant $tenantB;

    /** @var array<string, mixed> */
    private array $a;

    /** @var array<string, mixed> */
    private array $b;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-24 10:00:00');

        $this->context = app(TenantContext::class);
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);

        $this->a = $this->context->run($this->tenant->id, fn () => $this->buildTenantData('a'));
        $this->b = $this->context->run($this->tenantB->id, fn () => $this->buildTenantData('b'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function buildTenantData(string $p): array
    {
        $make = fn (string $role, ?int $departmentId = null) => User::create([
            'name' => "{$p}-{$role}",
            'email' => "{$p}-{$role}@example.com",
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
            'department_id' => $departmentId,
        ]);

        $department = Department::create(['name' => "{$p}-department"]);
        $admin = $make('admin');
        $manager = $make('manager', $department->id);
        $employee = $make('employee', $department->id);
        $employee->forceFill(['profile_image' => "{$p}-avatar.jpg"])->save();

        $project = Project::create([
            'department_id' => $department->id,
            'owner_id' => $manager->id,
            'name' => "{$p}-project",
            'status' => 'active',
        ]);
        $task = Task::create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'assignee_id' => $employee->id,
            'title' => "{$p}-task",
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
        $timeLog = TimeLog::create([
            'task_id' => $task->id,
            'user_id' => $employee->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
            'duration_minutes' => 60,
            'entry_type' => 'manual',
            'notes' => "{$p}-log",
        ]);
        $comment = Comment::create(['task_id' => $task->id, 'user_id' => $admin->id, 'body' => "{$p}-comment"]);
        $notification = Notification::create([
            'user_id' => $admin->id, 'category' => 'system', 'title' => "{$p}-notification", 'description' => 'x',
        ]);
        ActivityLog::create(['user_id' => $admin->id, 'action' => 'seed', 'description' => "{$p}-activity"]);
        Setting::create(['setting_key' => 'app.name', 'setting_value' => "{$p}-app"]);
        // attachments has no model yet; insert the row directly.
        $attachment = DB::table('attachments')->insertGetId([
            'tenant_id' => $this->context->id(), 'task_id' => $task->id, 'user_id' => $employee->id,
            'path' => "{$p}.pdf", 'filename' => "{$p}.pdf", 'created_at' => now(), 'updated_at' => now(),
        ]);

        return compact('department', 'admin', 'manager', 'employee', 'project', 'task', 'timeLog', 'comment', 'notification', 'attachment');
    }

    private function actAsAdminA(): static
    {
        return $this->actingAs($this->a['admin']);
    }

    private function ids(iterable $rows): array
    {
        return collect($rows)->pluck('id')->all();
    }

    // ── The policy bypass is intact; the scope is the boundary ────────────

    public function test_admin_policy_bypass_is_intact_yet_the_other_tenants_row_is_unreachable(): void
    {
        // before() → true: the policy alone would allow this.
        $this->assertTrue(Gate::forUser($this->a['admin'])->allows('update', $this->b['project']));

        $this->actAsAdminA()->put("/admin/projects/{$this->b['project']->id}", [
            'department_id' => $this->a['department']->id,
            'owner_id' => $this->a['admin']->id,
            'name' => 'hijacked',
            'status' => 'active',
        ])->assertNotFound();

        $this->assertSame('b-project', Project::withoutTenancy()->find($this->b['project']->id)->name);
    }

    // ── Listings ──────────────────────────────────────────────────────────

    public function test_admin_listings_only_contain_own_tenant_rows(): void
    {
        $this->actAsAdminA()->get('/admin/projects')->assertOk()->assertInertia(fn ($page) => $page
            ->where('projects.data', fn ($rows) => $this->ids($rows) === [$this->a['project']->id])
            ->where('departments', fn ($rows) => $this->ids($rows) === [$this->a['department']->id])
            ->where('owners', fn ($rows) => ! in_array($this->b['admin']->id, $this->ids($rows), true))
        );

        $this->actAsAdminA()->get('/admin/tasks')->assertOk()->assertInertia(fn ($page) => $page
            ->where('tasks.data', fn ($rows) => $this->ids($rows) === [$this->a['task']->id])
            ->where('projects', fn ($rows) => $this->ids($rows) === [$this->a['project']->id])
            ->where('users', fn ($rows) => ! in_array($this->b['employee']->id, $this->ids($rows), true))
        );

        $this->actAsAdminA()->get('/admin/users')->assertOk()->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($rows) => collect($rows)->pluck('email')->every(fn ($e) => str_starts_with($e, 'a-')))
        );
    }

    public function test_admin_csv_export_only_contains_own_tenant_logs(): void
    {
        $csv = $this->actAsAdminA()->get('/admin/timesheets/export')->assertOk()->streamedContent();

        $this->assertStringContainsString('a-log', $csv);
        $this->assertStringNotContainsString('b-log', $csv);
    }

    // ── Direct IDs in URLs ────────────────────────────────────────────────

    public function test_other_tenant_ids_in_urls_return_404(): void
    {
        $b = $this->b;
        $taskA = $this->a['task']->id;

        $requests = [
            ['get', "/admin/projects/{$b['project']->id}"],
            ['delete', "/admin/projects/{$b['project']->id}"],
            ['get', "/admin/tasks/{$b['task']->id}"],
            ['put', "/admin/tasks/{$b['task']->id}"],
            ['patch', "/admin/tasks/{$b['task']->id}/status"],
            ['delete', "/admin/tasks/{$b['task']->id}"],
            ['patch', "/tasks/{$b['task']->id}/time-logs/{$b['timeLog']->id}/approve"],
            // Own task, other tenant's time log.
            ['patch', "/tasks/{$taskA}/time-logs/{$b['timeLog']->id}/approve"],
            ['post', "/tasks/{$b['task']->id}/comments"],
            ['put', "/comments/{$b['comment']->id}"],
            ['delete', "/comments/{$b['comment']->id}"],
            ['put', "/admin/users/{$b['employee']->id}"],
            ['put', "/admin/departments/{$b['department']->id}"],
            ['delete', "/admin/departments/{$b['department']->id}"],
        ];

        foreach ($requests as [$method, $url]) {
            $this->actAsAdminA()->{$method}($url, ['body' => 'x', 'name' => 'x', 'status' => 'todo'])
                ->assertNotFound();
        }

        // Nothing in tenant B changed.
        $this->context->run($this->tenantB->id, function () use ($b) {
            $this->assertNotNull(Project::find($b['project']->id));
            $this->assertNotNull(Task::find($b['task']->id));
            $this->assertNull(TimeLog::find($b['timeLog']->id)->approved_by);
            $this->assertSame('b-comment', Comment::find($b['comment']->id)->body);
            $this->assertSame('b-department', Department::find($b['department']->id)->name);
            $this->assertSame(1, Comment::count());
        });
    }

    public function test_other_tenants_notification_cannot_be_read(): void
    {
        $this->actAsAdminA()->get("/admin/notification/{$this->b['notification']->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('notification', null));

        $this->assertNull(Notification::withoutTenancy()->find($this->b['notification']->id)->read_at);
    }

    public function test_other_tenants_profile_image_is_not_served(): void
    {
        $this->actAsAdminA()->get('/admin/profile-image/b-avatar.jpg')->assertNotFound();
    }

    // ── IDs in request bodies (validation rules) ──────────────────────────

    public function test_other_tenant_ids_in_request_bodies_fail_validation(): void
    {
        $b = $this->b;

        $this->actAsAdminA()->post('/admin/projects', [
            'department_id' => $b['department']->id,
            'owner_id' => $b['manager']->id,
            'member_ids' => [$b['employee']->id],
            'name' => 'x',
            'status' => 'active',
        ])->assertInvalid(['department_id', 'owner_id', 'member_ids.0']);

        $this->actAsAdminA()->post('/admin/tasks', [
            'project_id' => $b['project']->id,
            'assignee_id' => $b['employee']->id,
            'depends_on_task_id' => $b['task']->id,
            'title' => 'x', 'status' => 'todo', 'priority' => 'low', 'impact' => 'low', 'effort' => 'low',
        ])->assertInvalid(['project_id', 'assignee_id', 'depends_on_task_id']);

        $this->actAsAdminA()->patch("/admin/tasks/{$this->a['task']->id}/assign", [
            'assignee_id' => $b['employee']->id,
        ])->assertInvalid(['assignee_id']);

        $this->actAsAdminA()->post('/admin/users', [
            'department_id' => $b['department']->id,
        ])->assertInvalid(['department_id']);

        $this->actAsAdminA()->post('/admin/departments', [
            'name' => 'new', 'manager_id' => $b['manager']->id,
        ])->assertInvalid(['manager_id']);

        $this->actAsAdminA()->post('/admin/timesheets/bulk-approve', [
            'time_log_ids' => [$b['timeLog']->id],
        ])->assertInvalid(['time_log_ids.0']);

        $this->actAsAdminA()->post("/tasks/{$this->a['task']->id}/comments", [
            'body' => 'reply', 'parent_id' => $b['comment']->id,
        ])->assertInvalid(['parent_id']);

        // Nothing was created in tenant A from those requests.
        $this->assertSame(1, Project::count());
        $this->assertSame(1, Task::count());
        $this->assertSame(1, Department::count());
    }

    public function test_unique_rules_are_per_tenant(): void
    {
        // Tenant B's department name is free to use in tenant A...
        $this->actAsAdminA()->post('/admin/departments', ['name' => 'b-department'])->assertValid();
        $this->assertTrue(Department::where('name', 'b-department')->exists());

        // ...but user emails stay unique across the whole platform.
        $this->actAsAdminA()->post('/admin/users', ['email' => 'b-employee@example.com'])->assertInvalid(['email']);
    }

    // ── Model layer: fails closed, never writes across tenants ────────────

    public function test_model_queries_only_see_the_current_tenant(): void
    {
        foreach ([Department::class, Project::class, Task::class, TimeLog::class, Comment::class,
            Notification::class, ActivityLog::class, Setting::class] as $model) {
            $this->assertSame(1, $model::count(), $model);
            $this->assertSame(2, $model::withoutTenancy()->count(), $model);
        }

        $this->assertNull(Project::find($this->b['project']->id));
        $this->assertNull(User::where('email', 'b-admin@example.com')->first());
        $this->assertSame('a-app', Setting::get('app.name'));
    }

    public function test_join_based_aggregates_stay_inside_the_tenant(): void
    {
        $minutes = TimeLog::loggedMinutesByTaskStatus(Task::query());

        $this->assertSame(60, (int) array_sum($minutes));
    }

    public function test_queries_without_a_tenant_throw_instead_of_returning_everything(): void
    {
        $this->context->forget();

        $this->expectException(TenantIsolationException::class);
        Project::query()->count();
    }

    public function test_creating_without_a_tenant_throws(): void
    {
        $this->context->forget();

        $this->expectException(TenantIsolationException::class);
        Department::create(['name' => 'orphan']);
    }

    public function test_cannot_create_a_row_for_another_tenant(): void
    {
        $department = new Department(['name' => 'smuggled']);
        $department->forceFill(['tenant_id' => $this->tenantB->id]);

        $this->expectException(TenantIsolationException::class);
        $department->save();
    }

    public function test_cannot_move_a_row_to_another_tenant(): void
    {
        $project = $this->a['project']->fresh();
        $project->forceFill(['tenant_id' => $this->tenantB->id]);

        $this->expectException(TenantIsolationException::class);
        $project->save();
    }

    public function test_tenant_id_is_not_mass_assignable(): void
    {
        $department = Department::create(['name' => 'mass', 'tenant_id' => $this->tenantB->id]);

        $this->assertSame($this->tenant->id, $department->tenant_id);
    }

    // ── Database constraints: wrong-tenant links rejected below the app ───

    public function test_project_members_cannot_link_across_tenants(): void
    {
        $projectA = $this->a['project'];
        $userB = $this->b['employee']->id;

        // HTTP: update (store is covered above) with another tenant's user id.
        $this->actAsAdminA()->put("/admin/projects/{$projectA->id}", [
            'department_id' => $this->a['department']->id,
            'owner_id' => $this->a['manager']->id,
            'member_ids' => [$userB],
            'name' => 'a-project',
            'status' => 'active',
        ])->assertInvalid(['member_ids.0']);

        // Model layer: the pivot sync/attach skips validation and model events.
        $this->assertQueryRejected(fn () => $projectA->members()->attach($userB));
        $this->assertQueryRejected(fn () => $projectA->members()->sync([$userB]));
        $this->assertQueryRejected(fn () => $this->b['employee']->collaboratedProjects()->attach($projectA->id));

        // Raw rows with direct IDs, whichever tenant_id they claim.
        foreach ([$this->tenant->id, $this->tenantB->id] as $tenantId) {
            $this->assertQueryRejected(fn () => DB::table('project_members')->insert([
                'project_id' => $projectA->id, 'user_id' => $userB, 'tenant_id' => $tenantId,
            ]));
        }

        $this->assertSame(0, DB::table('project_members')->count());

        // Same-tenant membership still works, and the row carries the tenant.
        $projectA->members()->sync([$this->a['employee']->id]);
        $this->assertSame([$this->a['employee']->id], $projectA->members()->pluck('users.id')->all());
        $this->assertSame($this->tenant->id, (int) DB::table('project_members')->value('tenant_id'));
        $this->assertSame([$projectA->id], $this->a['employee']->collaboratedProjects()->pluck('projects.id')->all());

        // An existing membership row cannot be re-pointed at the other tenant by direct ID.
        $this->assertQueryRejected(fn () => DB::table('project_members')->update(['user_id' => $userB]));
        $this->assertQueryRejected(fn () => DB::table('project_members')->update(['project_id' => $this->b['project']->id]));
        $this->assertQueryRejected(fn () => DB::table('project_members')->update(['tenant_id' => $this->tenantB->id]));
        $this->assertSame(
            [[$projectA->id, $this->a['employee']->id, $this->tenant->id]],
            DB::table('project_members')->get()->map(fn ($r) => [(int) $r->project_id, (int) $r->user_id, (int) $r->tenant_id])->all()
        );
    }

    public function test_every_tenant_owned_link_rejects_another_tenants_row(): void
    {
        $a = $this->a;
        $b = $this->b;
        $activityA = ActivityLog::first();

        // [table, tenant A row id, column, tenant B row it must not point at]
        $links = [
            ['users', $a['employee']->id, 'department_id', $b['department']->id],
            ['departments', $a['department']->id, 'manager_id', $b['manager']->id],
            ['projects', $a['project']->id, 'department_id', $b['department']->id],
            ['projects', $a['project']->id, 'owner_id', $b['manager']->id],
            ['tasks', $a['task']->id, 'project_id', $b['project']->id],
            ['tasks', $a['task']->id, 'assignee_id', $b['employee']->id],
            ['tasks', $a['task']->id, 'created_by', $b['manager']->id],
            ['tasks', $a['task']->id, 'parent_task_id', $b['task']->id],
            ['tasks', $a['task']->id, 'depends_on_task_id', $b['task']->id],
            ['time_logs', $a['timeLog']->id, 'task_id', $b['task']->id],
            ['time_logs', $a['timeLog']->id, 'user_id', $b['employee']->id],
            ['time_logs', $a['timeLog']->id, 'approved_by', $b['manager']->id],
            ['comments', $a['comment']->id, 'task_id', $b['task']->id],
            ['comments', $a['comment']->id, 'user_id', $b['admin']->id],
            ['comments', $a['comment']->id, 'parent_id', $b['comment']->id],
            ['notifications', $a['notification']->id, 'user_id', $b['admin']->id],
            ['activity_logs', $activityA->id, 'user_id', $b['admin']->id],
            ['attachments', $a['attachment'], 'task_id', $b['task']->id],
            ['attachments', $a['attachment'], 'user_id', $b['employee']->id],
        ];

        foreach ($links as [$table, $id, $column, $otherTenantId]) {
            $before = DB::table($table)->where('id', $id)->value($column);

            $this->assertQueryRejected(
                fn () => DB::table($table)->where('id', $id)->update([$column => $otherTenantId]),
                "{$table}.{$column}"
            );
            $this->assertSame($before, DB::table($table)->where('id', $id)->value($column), "{$table}.{$column}");
        }

        // NULL links are still allowed.
        DB::table('tasks')->where('id', $a['task']->id)->update(['assignee_id' => null]);
        $this->assertNull(Task::find($a['task']->id)->assignee_id);
    }

    private function assertQueryRejected(callable $write, string $message = ''): void
    {
        try {
            // Savepoint, so the failed statement leaves the test transaction usable.
            DB::transaction($write);
        } catch (QueryException $e) {
            $this->assertMatchesRegularExpression('/foreign key|constraint/i', $e->getMessage(), $message);

            return;
        }

        $this->fail("Cross-tenant write was accepted. {$message}");
    }

    // ── Queued jobs ───────────────────────────────────────────────────────

    private function runOneQueuedJob(): void
    {
        // A worker process starts with no tenant; only the payload provides one.
        $this->context->forget();
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);
    }

    public function test_queued_job_runs_as_the_tenant_that_dispatched_it(): void
    {
        config(['queue.default' => 'database']);

        event(new NotificationCreated($this->a['employee'], 'queued-a', 'm'));

        $payload = json_decode(DB::table('jobs')->value('payload'), true);
        $this->assertSame($this->tenant->id, $payload['tenant_id']);

        $this->runOneQueuedJob();

        $created = Notification::withoutTenancy()->where('title', 'queued-a')->first();
        $this->assertNotNull($created);
        $this->assertSame($this->tenant->id, $created->tenant_id);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_queued_job_without_a_tenant_fails_closed(): void
    {
        config(['queue.default' => 'database']);
        $employee = $this->a['employee'];

        $this->context->forget();
        event(new NotificationCreated($employee, 'queued-none', 'm'));
        $this->runOneQueuedJob();

        $this->assertFalse(Notification::withoutTenancy()->where('title', 'queued-none')->exists());
        $this->assertStringContainsString('No tenant is set', DB::table('failed_jobs')->value('exception'));
    }

    public function test_queued_job_cannot_load_another_tenants_model(): void
    {
        config(['queue.default' => 'database']);

        // Dispatched as tenant A, but carrying tenant B's user.
        event(new NotificationCreated($this->b['employee'], 'queued-cross', 'm'));
        $this->runOneQueuedJob();

        $this->assertFalse(Notification::withoutTenancy()->where('title', 'queued-cross')->exists());
        $this->assertSame(1, DB::table('failed_jobs')->count());
    }

    // ── Authentication sets the tenant ────────────────────────────────────

    public function test_login_switches_to_the_users_tenant(): void
    {
        $this->context->forget();

        $this->post('/auth/login', ['email' => 'b-admin@example.com', 'password' => 'password'])
            ->assertRedirect();

        $this->assertSame($this->tenantB->id, $this->context->id());
        $this->assertTrue(ActivityLog::where('action', 'login')->exists()); // logged into tenant B

        $this->get('/admin/projects')->assertOk()->assertInertia(fn ($page) => $page
            ->where('projects.data', fn ($rows) => $this->ids($rows) === [$this->b['project']->id])
        );
    }

    public function test_guest_password_reset_finds_users_in_any_tenant(): void
    {
        Mail::fake();
        $this->context->forget();

        $this->post('/auth/forgot-password', ['email' => 'b-employee@example.com'])->assertSessionHasNoErrors();

        $this->assertTrue(DB::table('password_reset_tokens')->where('email', 'b-employee@example.com')->exists());
    }

    public function test_guest_password_reset_completes_and_logs_into_the_users_own_tenant(): void
    {
        $token = Password::broker()->createToken($this->b['employee']);
        $this->context->forget();

        $this->post('/auth/reset-password', [
            'token' => $token,
            'email' => 'b-employee@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password', User::withoutTenancy()->find($this->b['employee']->id)->password));

        $log = ActivityLog::withoutTenancy()->where('action', 'password_reset')->first();
        $this->assertSame($this->tenantB->id, $log->tenant_id);
        $this->assertNull($this->context->id()); // context restored after the reset
    }
}
