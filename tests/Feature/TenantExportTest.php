<?php

namespace Tests\Feature;

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
use App\Features\TenantExport\Jobs\BuildTenantExport;
use App\Features\TenantExport\Models\TenantExport;
use App\Features\TenantExport\Services\TenantExportBuilder;
use App\Features\TenantExport\Services\TenantExportService;
use App\Features\TimeLog\Models\TimeLog;
use App\Mail\GeneralMail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Full tenant data export (Phase 1D, PR 2c): a queued job writes a ZIP of
 * CSVs; only the admin who asked can download it, through a signed link.
 */
class TenantExportTest extends TestCase
{
    use RefreshDatabase;

    private const FILES = ['users', 'departments', 'projects', 'project_members', 'tasks', 'time_logs',
        'comments', 'attachments', 'settings', 'activity_logs', 'user_invitations'];

    private User $admin;

    /** @var array<string, mixed> tenant A's fixture ids and secrets */
    private array $a;

    /** @var array<string, mixed> tenant B's */
    private array $b;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-11 10:00:00');
        Storage::fake('local');
        Mail::fake();

        $this->a = $this->seedTenant($this->tenant, 'a');
        $this->tenantB = Tenant::create(['name' => 'Tenant B']);
        $this->b = $this->seedTenant($this->tenantB, 'b');
        $this->admin = $this->a['admin'];
        app(TenantContext::class)->set($this->tenant->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** One row of every exported type, with secrets set, in the given tenant. */
    private function seedTenant(Tenant $tenant, string $tag): array
    {
        return app(TenantContext::class)->run($tenant->id, function () use ($tenant, $tag) {
            $admin = User::create([
                'name' => "Admin {$tag}", 'email' => "admin-{$tag}@example.com", 'password' => "secret-{$tag}",
                'role' => 'admin', 'is_active' => true,
                'last_login_ip' => "10.0.0.{$tenant->id}", 'profile_image' => "profiles/{$tag}-face.png",
            ]);
            $admin->forceFill(['remember_token' => "remember-token-{$tag}"])->save();
            $department = Department::create(['name' => "Dept {$tag}"]);
            $employee = User::create([
                'name' => "Employee {$tag}", 'email' => "employee-{$tag}@example.com", 'password' => 'password',
                'role' => 'employee', 'is_active' => true, 'department_id' => $department->id,
            ]);
            $project = Project::create(['department_id' => $department->id, 'owner_id' => $admin->id, 'name' => "Project {$tag}", 'status' => 'active']);
            DB::table('project_members')->insert(['project_id' => $project->id, 'user_id' => $employee->id, 'tenant_id' => $tenant->id, 'role' => 'contributor', 'joined_at' => now()]);
            $task = Task::create([
                'project_id' => $project->id, 'created_by' => $admin->id, 'assignee_id' => $employee->id,
                'title' => "=Formula task {$tag}", 'status' => Task::STATUS_IN_PROGRESS,
            ]);
            $timeLog = TimeLog::create([
                'task_id' => $task->id, 'user_id' => $employee->id, 'started_at' => now()->subHour(), 'ended_at' => now(),
                'duration_minutes' => 60, 'entry_type' => 'manual', 'notes' => "Notes {$tag}",
            ]);
            $comment = Comment::create(['task_id' => $task->id, 'user_id' => $employee->id, 'body' => "Comment {$tag}"]);
            $attachmentId = DB::table('attachments')->insertGetId([
                'tenant_id' => $tenant->id, 'task_id' => $task->id, 'user_id' => $employee->id,
                'path' => "private/attachments/{$tag}-stored-path.pdf", 'filename' => "spec-{$tag}.pdf", 'mime_type' => 'application/pdf', 'size_bytes' => 1234,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $setting = Setting::create(['setting_key' => 'app.name', 'setting_value' => "Name {$tag}"]);
            $log = ActivityLog::create([
                'user_id' => $admin->id, 'action' => "seeded_{$tag}", 'description' => 'Seeded',
                'ip_address' => "203.0.113.{$tenant->id}", 'user_agent' => "SecretAgent/{$tag}",
            ]);
            $invitation = UserInvitation::create([
                'email' => "invitee-{$tag}@example.com", 'role' => 'employee', 'invited_by' => $admin->id,
                'token_hash' => hash('sha256', "invite-token-{$tag}"), 'expires_at' => now()->addDays(7),
            ]);

            return [
                'admin' => $admin, 'employee' => $employee,
                'ids' => [
                    'users' => [$admin->id, $employee->id], 'departments' => [$department->id], 'projects' => [$project->id],
                    'tasks' => [$task->id], 'time_logs' => [$timeLog->id], 'comments' => [$comment->id],
                    'attachments' => [$attachmentId], 'settings' => [$setting->id], 'user_invitations' => [$invitation->id],
                    'project_members' => ["{$project->id}-{$employee->id}"],
                ],
                'secrets' => [
                    $admin->fresh()->getAttributes()['password'], "remember-token-{$tag}", "10.0.0.{$tenant->id}",
                    "profiles/{$tag}-face.png", "private/attachments/{$tag}-stored-path.pdf",
                    "203.0.113.{$tenant->id}", "SecretAgent/{$tag}", hash('sha256', "invite-token-{$tag}"),
                ],
            ];
        });
    }

    private function exportAs(User $admin): TenantExport
    {
        $this->actingAs($admin)->post('/admin/exports')->assertSessionHas('success');

        return app(TenantContext::class)->run($admin->tenant_id, fn () => TenantExport::latest('id')->firstOrFail());
    }

    /** @return array<string, list<array<string, string>>> file => rows keyed by header */
    private function readZip(TenantExport $export): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open(Storage::disk('local')->path($export->file_path)));

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $content = $zip->getFromIndex($i);
            if (! str_ends_with($name, '.csv')) {
                $files[$name] = $content;

                continue;
            }
            $lines = array_map('str_getcsv', array_values(array_filter(preg_split('/\r?\n/', ltrim($content, "\xEF\xBB\xBF")))));
            $header = array_shift($lines);
            $files[basename($name, '.csv')] = array_map(fn ($row) => array_combine($header, $row), $lines);
        }
        $zip->close();

        return $files;
    }

    private function rawZipText(TenantExport $export): string
    {
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($export->file_path));
        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $text .= $zip->getFromIndex($i);
        }
        $zip->close();

        return $text;
    }

    private function downloadUrl(TenantExport $export): string
    {
        return app(TenantContext::class)->run($export->tenant_id, fn () => app(TenantExportService::class)->downloadUrl($export));
    }

    // ── Building ──────────────────────────────────────────────────────────

    public function test_an_admin_gets_a_zip_of_every_file_and_a_readme(): void
    {
        $export = $this->exportAs($this->admin);

        $this->assertSame(TenantExport::STATUS_READY, $export->status);
        $this->assertTrue($export->expires_at->equalTo(now()->addDays(7)));
        $this->assertSame($this->admin->id, $export->requested_by);
        $this->assertStringStartsWith("exports/{$this->tenant->id}/", $export->file_path);

        $files = $this->readZip($export);
        $this->assertEqualsCanonicalizing(array_merge(self::FILES, ['README.txt']), array_keys($files));
        $this->assertStringContainsString('Organisation: Test Tenant', $files['README.txt']);
        $this->assertStringContainsString('Not included: passwords', $files['README.txt']);
        $this->assertSame(2, $export->row_counts['users']);

        // Temporary files are cleaned up; only the ZIP stays.
        $this->assertSame([$export->file_path], Storage::disk('local')->allFiles('exports'));

        // The requester is emailed a signed link to it.
        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo($this->admin->email)
            && $mail->contentView === 'emails.export.ready'
            && str_contains($mail->data['actionUrl'], "/admin/exports/{$export->id}/download?expires=")
            && str_contains($mail->data['actionUrl'], 'signature='));

        $this->assertTrue(ActivityLog::where('action', 'tenant_export_requested')->where('user_id', $this->admin->id)->exists());
    }

    public function test_every_file_holds_only_this_tenants_rows(): void
    {
        $files = $this->readZip($this->exportAs($this->admin));

        foreach ($this->a['ids'] as $file => $ids) {
            $exported = $file === 'project_members'
                ? array_map(fn ($row) => "{$row['project_id']}-{$row['user_id']}", $files[$file])
                : array_map(fn ($row) => (int) $row['id'], $files[$file]);

            $this->assertEqualsCanonicalizing($ids, $exported, "{$file}.csv");
        }

        // The activity log is the tenant's own: B's entry never appears.
        $actions = array_column($files['activity_logs'], 'action');
        $this->assertContains('seeded_a', $actions);
        $this->assertNotContains('seeded_b', $actions);
    }

    public function test_tenant_b_gets_only_its_own_rows_too(): void
    {
        $files = $this->readZip($this->exportAs($this->b['admin']));

        $this->assertEqualsCanonicalizing($this->b['ids']['users'], array_map(fn ($row) => (int) $row['id'], $files['users']));
        $this->assertSame([$this->b['ids']['project_members'][0]], array_map(fn ($row) => "{$row['project_id']}-{$row['user_id']}", $files['project_members']));
        $this->assertSame($this->b['ids']['attachments'], array_map(fn ($row) => (int) $row['id'], $files['attachments']));
    }

    public function test_secrets_are_never_exported(): void
    {
        $export = $this->exportAs($this->admin);
        $text = $this->rawZipText($export);

        foreach (array_merge($this->a['secrets'], $this->b['secrets']) as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }

        $files = $this->readZip($export);
        foreach (['password', 'remember_token', 'last_login_ip', 'profile_image'] as $column) {
            $this->assertArrayNotHasKey($column, $files['users'][0]);
        }
        $this->assertArrayNotHasKey('token_hash', $files['user_invitations'][0]);
        $this->assertArrayNotHasKey('path', $files['attachments'][0]);
        $this->assertArrayNotHasKey('ip_address', $files['activity_logs'][0]);
        $this->assertArrayNotHasKey('user_agent', $files['activity_logs'][0]);
    }

    public function test_cells_cannot_run_as_formulas(): void
    {
        $files = $this->readZip($this->exportAs($this->admin));

        $this->assertSame("'=Formula task a", $files['tasks'][0]['title']);
    }

    public function test_a_failed_build_is_recorded_and_the_requester_told(): void
    {
        $this->mock(TenantExportBuilder::class)->shouldReceive('build')->andThrow(new RuntimeException('Disk full'));

        $export = $this->exportAs($this->admin);

        $this->assertSame(TenantExport::STATUS_FAILED, $export->status);
        $this->assertSame('Disk full', $export->error);
        $this->assertNull($export->file_path);
        $this->assertTrue(ActivityLog::where('action', 'tenant_export_failed')->exists());
        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo($this->admin->email) && $mail->contentView === 'emails.export.failed');
        Mail::assertNotQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->contentView === 'emails.export.ready');

        // And it doesn't block the next one.
        $this->assertTrue(app(TenantExportService::class)->canRequest());
    }

    public function test_a_job_running_as_another_tenant_does_nothing(): void
    {
        $export = (new TenantExport)->forceFill(['requested_by' => $this->admin->id, 'status' => TenantExport::STATUS_QUEUED]);
        $export->save();

        // As if the payload carried tenant B: the export isn't visible, so nothing is built.
        app(TenantContext::class)->run($this->tenantB->id, fn () => app()->call([new BuildTenantExport($export->id), 'handle']));

        $this->assertSame(TenantExport::STATUS_QUEUED, $export->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('exports'));
        Mail::assertNothingQueued();
    }

    public function test_the_builder_refuses_an_export_of_another_tenant(): void
    {
        $export = (new TenantExport)->forceFill(['requested_by' => $this->admin->id, 'status' => TenantExport::STATUS_RUNNING]);
        $export->save();

        // A second line of defence behind the job's scoped lookup.
        app(TenantContext::class)->run($this->tenantB->id, function () use ($export) {
            try {
                app(TenantExportBuilder::class)->build($export);
                $this->fail('Building another tenant\'s export should be refused.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('belongs to another tenant', $e->getMessage());
            }
        });

        $this->assertSame([], Storage::disk('local')->allFiles('exports'));
    }

    public function test_running_the_job_again_does_not_rebuild_a_finished_export(): void
    {
        $export = $this->exportAs($this->admin);
        $path = $export->file_path;

        app()->call([new BuildTenantExport($export->id), 'handle']);

        $this->assertSame($path, $export->fresh()->file_path);
        $this->assertSame([$path], Storage::disk('local')->allFiles('exports'));
        Mail::assertQueued(GeneralMail::class, 1);
    }

    public function test_an_export_can_never_point_at_another_tenants_user(): void
    {
        // The composite (requested_by, tenant_id) key rejects it in the database itself.
        $this->expectException(QueryException::class);

        (new TenantExport)->forceFill(['requested_by' => $this->b['admin']->id, 'status' => TenantExport::STATUS_QUEUED])->save();
    }

    // ── Who may start and download ────────────────────────────────────────

    public function test_managers_and_employees_cannot_start_or_download(): void
    {
        $export = $this->exportAs($this->admin);
        $url = $this->downloadUrl($export);

        foreach (['manager', 'employee'] as $role) {
            $user = app(TenantContext::class)->run($this->tenant->id, fn () => User::create([
                'name' => $role, 'email' => "{$role}-x@example.com", 'password' => 'password', 'role' => $role, 'is_active' => true,
            ]));
            $this->actingAs($user)->post('/admin/exports')->assertForbidden();
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_only_the_requester_can_download(): void
    {
        $export = $this->exportAs($this->admin);
        $url = $this->downloadUrl($export);
        $otherAdmin = app(TenantContext::class)->run($this->tenant->id, fn () => User::create([
            'name' => 'Other Admin', 'email' => 'other-admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true,
        ]));

        $this->actingAs($otherAdmin)->get($url)->assertForbidden();

        $response = $this->actingAs($this->admin)->get($url)->assertOk();
        $this->assertStringContainsString('zyncwork-export-2026-10-11.zip', $response->headers->get('content-disposition'));
        $this->assertNotNull($export->fresh()->downloaded_at);
        $this->assertTrue(ActivityLog::where('action', 'tenant_export_downloaded')->where('user_id', $this->admin->id)->exists());
    }

    public function test_another_tenants_admin_gets_not_found_even_with_a_valid_link(): void
    {
        $url = $this->downloadUrl($this->exportAs($this->admin));

        $this->actingAs($this->b['admin'])->get($url)->assertNotFound();
    }

    public function test_the_link_must_be_signed_unexpired_and_used_signed_in(): void
    {
        $export = $this->exportAs($this->admin);
        $url = $this->downloadUrl($export);

        $this->actingAs($this->admin)->get("/admin/exports/{$export->id}/download")->assertForbidden();
        $this->actingAs($this->admin)->get(str_replace('signature=', 'signature=0', $url))->assertForbidden();

        $this->post('/auth/logout');
        $this->get($url)->assertRedirect(route('login'));

        Carbon::setTestNow(now()->addHours(24)->addSecond());
        $this->actingAs($this->admin)->get($url)->assertForbidden();
    }

    public function test_an_expired_or_pruned_export_is_gone(): void
    {
        $export = $this->exportAs($this->admin);

        Carbon::setTestNow(now()->addDays(7));
        // A fresh link is still refused once the file's time is up.
        $url = URL::temporarySignedRoute('admin.exports.download', now()->addHour(), ['export' => $export->id]);
        $this->actingAs($this->admin)->get($url)->assertStatus(410);

        $this->artisan('exports:prune')->assertSuccessful();
        $this->assertSame(TenantExport::STATUS_EXPIRED, $export->fresh()->status);
        $this->assertNull($export->fresh()->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles('exports'));
    }

    // ── Limits ────────────────────────────────────────────────────────────

    public function test_one_active_export_per_tenant(): void
    {
        $queued = (new TenantExport)->forceFill(['requested_by' => $this->admin->id, 'status' => TenantExport::STATUS_QUEUED]);
        $queued->save();

        $this->actingAs($this->admin)->post('/admin/exports')->assertSessionHas('error', fn ($e) => str_contains($e, 'already being prepared'));
        $this->assertSame(1, TenantExport::count());

        // The billing page disables the button meanwhile.
        $this->actingAs($this->admin)->get('/admin/billing')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('exports.can_request', false));

        // Another tenant isn't held up by it.
        $this->exportAs($this->b['admin']);
        $this->assertSame(1, app(TenantContext::class)->run($this->tenantB->id, fn () => TenantExport::count()));
    }

    public function test_at_most_three_exports_in_24_hours(): void
    {
        foreach (range(1, 3) as $i) {
            $this->exportAs($this->admin);
            Carbon::setTestNow(now()->addHour());
        }

        $this->actingAs($this->admin)->post('/admin/exports')->assertSessionHas('error', fn ($e) => str_contains($e, '3 exports in 24 hours'));
        $this->assertSame(3, TenantExport::count());
        $this->assertFalse(app(TenantExportService::class)->canRequest());

        Carbon::setTestNow(now()->addHours(21)->addSecond());
        $this->exportAs($this->admin);
        $this->assertSame(4, TenantExport::count());
    }

    public function test_prune_fails_exports_stuck_for_too_long(): void
    {
        $stuck = (new TenantExport)->forceFill(['requested_by' => $this->admin->id, 'status' => TenantExport::STATUS_RUNNING]);
        $stuck->save();
        $recent = app(TenantContext::class)->run($this->tenantB->id, function () {
            $export = (new TenantExport)->forceFill(['requested_by' => $this->b['admin']->id, 'status' => TenantExport::STATUS_QUEUED]);
            $export->save();

            return $export;
        });

        Carbon::setTestNow(now()->addMinutes(61));
        $recent->forceFill(['created_at' => now()->subMinutes(5)])->saveQuietly();

        $this->artisan('exports:prune')->assertSuccessful();

        $this->assertSame(TenantExport::STATUS_FAILED, $stuck->fresh()->status);
        $this->assertSame(TenantExport::STATUS_QUEUED, TenantExport::withoutTenancy()->find($recent->id)->status);
    }

    // ── Billing page ──────────────────────────────────────────────────────

    public function test_the_billing_page_lists_exports_with_a_link_only_for_the_requester(): void
    {
        $export = $this->exportAs($this->admin);
        $otherAdmin = app(TenantContext::class)->run($this->tenant->id, fn () => User::create([
            'name' => 'Other Admin', 'email' => 'other-admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true,
        ]));

        $this->actingAs($this->admin)->get('/admin/billing')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('exports.can_request', true)
            ->has('exports.recent', 1)
            ->where('exports.recent.0.id', $export->id)
            ->where('exports.recent.0.status', 'ready')
            ->where('exports.recent.0.download_url', fn ($url) => str_contains((string) $url, 'signature=')));

        $this->actingAs($otherAdmin)->get('/admin/billing')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('exports.recent.0.id', $export->id)
            ->where('exports.recent.0.download_url', null));

        // Tenant B's admin sees none of A's exports.
        $this->actingAs($this->b['admin'])->get('/admin/billing')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('exports.recent', 0));
    }
}
