<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\Projects\Manager\Services\ProjectService;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday — mid-week, so "this week" and "next week" are both non-empty
        // and every bucket boundary is unambiguous.
        Carbon::setTestNow(Carbon::parse('2026-09-09 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => uniqid('user_') . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ], $overrides));
    }

    private function makeDepartment(string $name = 'Engineering'): Department
    {
        return Department::create(['name' => $name]);
    }

    private function makeProject(Department $department, User $owner, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'department_id' => $department->id,
            'owner_id' => $owner->id,
            'name' => 'Test Project',
            'status' => 'active',
        ], $overrides));
    }

    private function makeTask(Project $project, User $creator, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'project_id' => $project->id,
            'created_by' => $creator->id,
            'title' => 'Test Task',
            'status' => Task::STATUS_TODO,
        ], $overrides));
    }

    /** @return array{0: Department, 1: User, 2: Project} */
    private function makeDepartmentManagerProject(): array
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);

        return [$department, $manager, $this->makeProject($department, $manager)];
    }

    private function timelineUrl(int $projectId, array $query = [], string $portal = 'manager'): string
    {
        return "/{$portal}/projects/{$projectId}?" . http_build_query(array_merge(['tab' => 'timeline'], $query));
    }

    // ── Access & scoping ────────────────────────────────────────────────────

    public function test_manager_can_open_the_timeline_tab_for_their_own_department_project(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();
        $this->makeTask($project, $manager, ['due_date' => '2026-09-09']);

        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('(portals)/manager/projects/Show')
                ->where('tab', 'timeline')
                ->has('timeline.tasks.data', 1)
                ->has('timeline.bucketCounts')
            );
    }

    public function test_admin_can_open_the_timeline_tab_for_any_department_project(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();
        $admin = $this->makeUser(['role' => 'admin']);
        $this->makeTask($project, $manager, ['due_date' => '2026-09-09']);

        $this->actingAs($admin)
            ->get($this->timelineUrl($project->id, [], 'admin'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('role', 'admin')
                ->where('tab', 'timeline')
                ->has('timeline.tasks.data', 1)
            );
    }

    public function test_manager_from_another_department_cannot_open_the_timeline(): void
    {
        [, , $project] = $this->makeDepartmentManagerProject();
        $otherDepartment = $this->makeDepartment('Sales');
        $outsideManager = $this->makeUser(['role' => 'manager', 'department_id' => $otherDepartment->id]);

        $this->actingAs($outsideManager)
            ->get($this->timelineUrl($project->id))
            ->assertForbidden();
    }

    public function test_timeline_never_leaks_tasks_from_another_project(): void
    {
        [$department, $manager, $projectA] = $this->makeDepartmentManagerProject();
        $projectB = $this->makeProject($department, $manager, ['name' => 'Other Project']);

        $this->makeTask($projectA, $manager, ['title' => 'Belongs to A', 'due_date' => '2026-09-09']);
        $this->makeTask($projectB, $manager, ['title' => 'Belongs to B', 'due_date' => '2026-09-09']);

        $response = $this->actingAs($manager)->get($this->timelineUrl($projectA->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('timeline.tasks.data', 1)
            ->where('timeline.tasks.data.0.title', 'Belongs to A')
            ->where('timeline.projectTaskTotal', 1)
        );
    }

    public function test_query_parameters_cannot_widen_project_scope(): void
    {
        [$department, $manager, $projectA] = $this->makeDepartmentManagerProject();
        $projectB = $this->makeProject($department, $manager, ['name' => 'Other Project']);
        $this->makeTask($projectB, $manager, ['title' => 'Belongs to B', 'due_date' => '2026-09-09']);

        // Attempting to inject another project's id through the query string must not work.
        $response = $this->actingAs($manager)->get(
            $this->timelineUrl($projectA->id, ['project_id' => $projectB->id])
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('timeline.tasks.data', 0));
    }

    // ── Bucketing ───────────────────────────────────────────────────────────

    public function test_every_bucket_is_assigned_correctly_and_is_mutually_exclusive(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        $expected = [
            'overdue'     => $this->makeTask($project, $manager, ['title' => 'Overdue', 'due_date' => '2026-09-08'])->id,
            'today'       => $this->makeTask($project, $manager, ['title' => 'Today', 'due_date' => '2026-09-09'])->id,
            'this_week'   => $this->makeTask($project, $manager, ['title' => 'This week', 'due_date' => '2026-09-11'])->id,
            'next_week'   => $this->makeTask($project, $manager, ['title' => 'Next week', 'due_date' => '2026-09-16'])->id,
            'later'       => $this->makeTask($project, $manager, ['title' => 'Later', 'due_date' => '2026-10-01'])->id,
            'no_due_date' => $this->makeTask($project, $manager, ['title' => 'Undated', 'due_date' => null])->id,
            'completed'   => $this->makeTask($project, $manager, [
                'title' => 'Done', 'due_date' => '2026-09-11', 'status' => Task::STATUS_COMPLETED,
            ])->id,
        ];

        $response = $this->actingAs($manager)->get($this->timelineUrl($project->id));
        $response->assertOk();

        $rows = collect($response->viewData('page')['props']['timeline']['tasks']['data']);

        foreach ($expected as $bucket => $taskId) {
            $row = $rows->firstWhere('id', $taskId);
            $this->assertNotNull($row, "Task for bucket [{$bucket}] missing from timeline.");
            $this->assertSame($bucket, $row['timeline_bucket'], "Task {$taskId} landed in the wrong bucket.");
        }

        // Mutually exclusive: each task appears exactly once, in exactly one bucket.
        $this->assertCount(7, $rows);
        $this->assertSame(7, $rows->pluck('id')->unique()->count());

        $counts = $response->viewData('page')['props']['timeline']['bucketCounts'];
        foreach (array_keys($expected) as $bucket) {
            $this->assertSame(1, $counts[$bucket], "Bucket [{$bucket}] count should be 1.");
        }
        $this->assertSame(7, array_sum($counts), 'Bucket counts must sum to the filtered total.');
    }

    public function test_completed_takes_precedence_and_a_completed_past_due_task_is_not_overdue(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        $this->makeTask($project, $manager, [
            'title' => 'Late but finished',
            'due_date' => '2026-09-01',
            'status' => Task::STATUS_COMPLETED,
        ]);

        $response = $this->actingAs($manager)->get($this->timelineUrl($project->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('timeline.bucketCounts.overdue', 0)
            ->where('timeline.bucketCounts.completed', 1)
            ->where('timeline.tasks.data.0.timeline_bucket', 'completed')
        );
    }

    #[DataProvider('boundaryDateProvider')]
    public function test_bucket_boundaries_are_exact(string $dueDate, string $expectedBucket): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();
        $this->makeTask($project, $manager, ['due_date' => $dueDate]);

        $response = $this->actingAs($manager)->get($this->timelineUrl($project->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('timeline.tasks.data.0.timeline_bucket', $expectedBucket)
            ->where("timeline.bucketCounts.{$expectedBucket}", 1)
        );
    }

    public static function boundaryDateProvider(): array
    {
        // "Now" is frozen at Wednesday 2026-09-09.
        // Calendar week: Mon 2026-09-07 → Sun 2026-09-13. Next week ends Sun 2026-09-20.
        return [
            'yesterday is overdue'                  => ['2026-09-08', 'overdue'],
            'start of this week (past) is overdue'  => ['2026-09-07', 'overdue'],
            'today is today'                        => ['2026-09-09', 'today'],
            'tomorrow is this week'                 => ['2026-09-10', 'this_week'],
            'end of this week is this week'         => ['2026-09-13', 'this_week'],
            'start of next week is next week'       => ['2026-09-14', 'next_week'],
            'end of next week is next week'         => ['2026-09-20', 'next_week'],
            'day after next week is later'          => ['2026-09-21', 'later'],
        ];
    }

    // ── Ordering ────────────────────────────────────────────────────────────

    public function test_tasks_are_ordered_by_bucket_then_due_date_with_undated_last(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        // Created deliberately out of order.
        $this->makeTask($project, $manager, ['title' => 'Undated', 'due_date' => null]);
        $this->makeTask($project, $manager, ['title' => 'Later', 'due_date' => '2026-10-05']);
        $this->makeTask($project, $manager, ['title' => 'Overdue older', 'due_date' => '2026-09-01']);
        $this->makeTask($project, $manager, ['title' => 'Today', 'due_date' => '2026-09-09']);
        $this->makeTask($project, $manager, ['title' => 'Overdue newer', 'due_date' => '2026-09-08']);

        $response = $this->actingAs($manager)->get($this->timelineUrl($project->id));
        $titles = collect($response->viewData('page')['props']['timeline']['tasks']['data'])
            ->pluck('title')
            ->all();

        $this->assertSame(
            ['Overdue older', 'Overdue newer', 'Today', 'Later', 'Undated'],
            $titles
        );
    }

    public function test_id_breaks_ties_for_equal_due_dates_so_ordering_is_deterministic(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        $first = $this->makeTask($project, $manager, ['title' => 'A', 'due_date' => '2026-09-10']);
        $second = $this->makeTask($project, $manager, ['title' => 'B', 'due_date' => '2026-09-10']);

        $response = $this->actingAs($manager)->get($this->timelineUrl($project->id));
        $ids = collect($response->viewData('page')['props']['timeline']['tasks']['data'])->pluck('id')->all();

        $this->assertSame([$first->id, $second->id], $ids);
    }

    // ── Filters ─────────────────────────────────────────────────────────────

    public function test_filters_narrow_both_the_rows_and_the_bucket_counts(): void
    {
        [$department, $manager, $project] = $this->makeDepartmentManagerProject();
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);

        $this->makeTask($project, $manager, [
            'title' => 'Mine', 'due_date' => '2026-09-09', 'assignee_id' => $employee->id, 'priority' => 'high',
        ]);
        $this->makeTask($project, $manager, [
            'title' => 'Theirs', 'due_date' => '2026-09-09', 'assignee_id' => $manager->id, 'priority' => 'low',
        ]);

        // Assignee filter
        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id, ['assignee_id' => $employee->id]))
            ->assertInertia(fn ($page) => $page
                ->has('timeline.tasks.data', 1)
                ->where('timeline.bucketCounts.today', 1)
                ->where('timeline.projectTaskTotal', 2)
            );

        // Priority filter
        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id, ['priority' => 'high']))
            ->assertInertia(fn ($page) => $page
                ->has('timeline.tasks.data', 1)
                ->where('timeline.bucketCounts.today', 1)
            );

        // Search filter
        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id, ['search' => 'Theirs']))
            ->assertInertia(fn ($page) => $page
                ->has('timeline.tasks.data', 1)
                ->where('timeline.bucketCounts.today', 1)
            );

        // Status filter
        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id, ['status' => Task::STATUS_COMPLETED]))
            ->assertInertia(fn ($page) => $page
                ->has('timeline.tasks.data', 0)
                ->where('timeline.bucketCounts.today', 0)
            );
    }

    public function test_combined_filters_apply_together(): void
    {
        [$department, $manager, $project] = $this->makeDepartmentManagerProject();
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);

        $this->makeTask($project, $manager, [
            'title' => 'Match', 'due_date' => '2026-09-09', 'assignee_id' => $employee->id, 'priority' => 'urgent',
        ]);
        $this->makeTask($project, $manager, [
            'title' => 'Wrong priority', 'due_date' => '2026-09-09', 'assignee_id' => $employee->id, 'priority' => 'low',
        ]);
        $this->makeTask($project, $manager, [
            'title' => 'Wrong assignee', 'due_date' => '2026-09-09', 'assignee_id' => $manager->id, 'priority' => 'urgent',
        ]);

        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id, ['assignee_id' => $employee->id, 'priority' => 'urgent']))
            ->assertInertia(fn ($page) => $page
                ->has('timeline.tasks.data', 1)
                ->where('timeline.tasks.data.0.title', 'Match')
                ->where('timeline.bucketCounts.today', 1)
            );
    }

    // ── Pagination ──────────────────────────────────────────────────────────

    public function test_bucket_counts_are_unaffected_by_pagination_and_pages_do_not_overlap(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        for ($i = 0; $i < 20; $i++) {
            $this->makeTask($project, $manager, ['title' => "Task {$i}", 'due_date' => '2026-09-09']);
        }

        $pageOne = $this->actingAs($manager)->get($this->timelineUrl($project->id));
        $pageTwo = $this->actingAs($manager)->get($this->timelineUrl($project->id, ['page' => 2]));

        $pageOne->assertInertia(fn ($page) => $page
            ->has('timeline.tasks.data', 15)
            ->where('timeline.bucketCounts.today', 20)
            ->where('timeline.tasks.total', 20)
        );

        $pageTwo->assertInertia(fn ($page) => $page
            ->has('timeline.tasks.data', 5)
            ->where('timeline.bucketCounts.today', 20) // identical to page 1
        );

        $idsOne = collect($pageOne->viewData('page')['props']['timeline']['tasks']['data'])->pluck('id');
        $idsTwo = collect($pageTwo->viewData('page')['props']['timeline']['tasks']['data'])->pluck('id');

        $this->assertCount(0, $idsOne->intersect($idsTwo), 'Pages must not contain duplicate tasks.');
        $this->assertSame(20, $idsOne->merge($idsTwo)->unique()->count(), 'No task may be skipped across pages.');
    }

    public function test_pagination_links_preserve_tab_and_filters(): void
    {
        [$department, $manager, $project] = $this->makeDepartmentManagerProject();
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);

        for ($i = 0; $i < 18; $i++) {
            $this->makeTask($project, $manager, [
                'title' => "Task {$i}", 'due_date' => '2026-09-09', 'assignee_id' => $employee->id,
            ]);
        }

        $response = $this->actingAs($manager)->get(
            $this->timelineUrl($project->id, ['assignee_id' => $employee->id])
        );

        $nextPageUrl = $response->viewData('page')['props']['timeline']['tasks']['next_page_url'];

        $this->assertStringContainsString('tab=timeline', $nextPageUrl);
        $this->assertStringContainsString('assignee_id=' . $employee->id, $nextPageUrl);
        $this->assertStringContainsString('page=2', $nextPageUrl);
    }

    // ── Payload shape ───────────────────────────────────────────────────────

    public function test_timeline_data_is_absent_when_the_tab_is_not_timeline(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();
        $this->makeTask($project, $manager, ['due_date' => '2026-09-09']);

        $this->actingAs($manager)
            ->get("/manager/projects/{$project->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tab', 'tasks')
                ->where('timeline', null)
                ->has('tasks.data', 1)
            );
    }

    public function test_tasks_tab_data_is_not_queried_while_viewing_the_timeline(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();
        $this->makeTask($project, $manager, ['due_date' => '2026-09-09']);

        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tasks', null));
    }

    public function test_existing_tasks_tab_status_counts_remain_whole_project_counts(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        $this->makeTask($project, $manager, ['status' => Task::STATUS_COMPLETED]);
        $this->makeTask($project, $manager, ['status' => Task::STATUS_BACKLOG]);

        // Phase 2 behaviour must be untouched: status facet counts stay whole-project.
        $this->actingAs($manager)
            ->get("/manager/projects/{$project->id}?status=completed")
            ->assertInertia(fn ($page) => $page
                ->has('tasks.data', 1)
                ->where('statusCounts.completed', 1)
                ->where('statusCounts.backlog', 1)
            );
    }

    // ── Empty states ────────────────────────────────────────────────────────

    public function test_project_with_no_tasks_returns_zero_filled_buckets(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        $response = $this->actingAs($manager)->get($this->timelineUrl($project->id));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('timeline.tasks.data', 0)
            ->where('timeline.projectTaskTotal', 0)
        );

        $counts = $response->viewData('page')['props']['timeline']['bucketCounts'];
        $this->assertSame(ProjectService::TIMELINE_BUCKETS, array_keys($counts));
        $this->assertSame(0, array_sum($counts));
    }

    public function test_project_where_no_task_has_a_due_date_puts_everything_in_no_due_date(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();

        $this->makeTask($project, $manager, ['due_date' => null]);
        $this->makeTask($project, $manager, ['due_date' => null]);

        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id))
            ->assertInertia(fn ($page) => $page
                ->where('timeline.bucketCounts.no_due_date', 2)
                ->where('timeline.bucketCounts.overdue', 0)
                ->has('timeline.tasks.data', 2)
            );
    }

    public function test_filters_with_no_matches_return_zero_counts_and_no_rows(): void
    {
        [, $manager, $project] = $this->makeDepartmentManagerProject();
        $this->makeTask($project, $manager, ['title' => 'Real task', 'due_date' => '2026-09-09']);

        $response = $this->actingAs($manager)->get(
            $this->timelineUrl($project->id, ['search' => 'nothing-matches-this'])
        );

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('timeline.tasks.data', 0)
            ->where('timeline.projectTaskTotal', 1) // whole-project context preserved
        );

        $counts = $response->viewData('page')['props']['timeline']['bucketCounts'];
        $this->assertSame(0, array_sum($counts));
    }

    // ── Logged time ─────────────────────────────────────────────────────────

    public function test_logged_minutes_are_aggregated_per_task_without_loading_time_logs(): void
    {
        [$department, $manager, $project] = $this->makeDepartmentManagerProject();
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);

        $task = $this->makeTask($project, $manager, ['due_date' => '2026-09-09']);

        foreach ([30, 45] as $minutes) {
            TimeLog::create([
                'task_id' => $task->id,
                'user_id' => $employee->id,
                'started_at' => now(),
                'ended_at' => now(),
                'duration_minutes' => $minutes,
                'entry_type' => 'manual',
            ]);
        }

        $this->actingAs($manager)
            ->get($this->timelineUrl($project->id))
            ->assertInertia(fn ($page) => $page
                ->where('timeline.tasks.data.0.logged_minutes', 75)
            );
    }

    // ── Performance ─────────────────────────────────────────────────────────

    public function test_timeline_query_count_does_not_grow_with_task_count(): void
    {
        [$department, $manager, $project] = $this->makeDepartmentManagerProject();
        $service = app(ProjectService::class);

        $small = $this->makeProject($department, $manager, ['name' => 'Small']);
        $big = $this->makeProject($department, $manager, ['name' => 'Big']);

        foreach ([[$small, 3], [$big, 40]] as [$target, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
                $task = $this->makeTask($target, $manager, [
                    'due_date' => '2026-09-1' . ($i % 9),
                    'assignee_id' => $employee->id,
                ]);
                TimeLog::create([
                    'task_id' => $task->id,
                    'user_id' => $employee->id,
                    'started_at' => now(),
                    'ended_at' => now(),
                    'duration_minutes' => 15,
                    'entry_type' => 'manual',
                ]);
            }
        }

        $run = function (Project $target) use ($service) {
            DB::flushQueryLog();
            $service->getProjectTimelineTasks($target)->items();
            $service->getProjectTimelineBucketCounts($target);
            $service->getProjectTaskTotal($target);

            return DB::getQueryLog();
        };

        DB::enableQueryLog();
        $smallQueries = $run($small);
        $bigQueries = $run($big);
        DB::disableQueryLog();

        $this->assertSame(
            count($smallQueries),
            count($bigQueries),
            'Timeline query count must not grow with task count: '
                . count($smallQueries) . ' vs ' . count($bigQueries)
        );
    }
}
