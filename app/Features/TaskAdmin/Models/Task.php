<?php

namespace App\Models;
namespace App\Features\TaskAdmin\Models;

use App\Features\Auth\Models\User;
use App\Features\Comment\Models\Comment;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Core\Tenancy\BelongsToTenant;

class Task extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'project_id',
        'assignee_id',
        'created_by',
        'parent_task_id',
        'depends_on_task_id',
        'title',
        'description',
        'status',
        'priority',
        'impact',
        'effort',
        'estimated_hours',
        'due_date',
        'is_recurring',
        'recurrence_rule',
        'recurrence_ends_at',
        'completed_at',
    ];

    protected $casts = [
        'due_date'           => 'date',
        'recurrence_ends_at' => 'date',
        'completed_at'       => 'datetime',
        'is_recurring'       => 'boolean',
        'estimated_hours'    => 'decimal:2',
    ];

    // ── Status constants ───────────────────────────────────────────────────
    const STATUS_BACKLOG     = 'backlog';
    const STATUS_TODO        = 'todo';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_IN_REVIEW   = 'in_review';
    const STATUS_COMPLETED   = 'completed';

    const STATUSES = [
        self::STATUS_BACKLOG,
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_IN_REVIEW,
        self::STATUS_COMPLETED,
    ];

    // ── Priority constants ─────────────────────────────────────────────────
    const PRIORITY_LOW    = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH   = 'high';
    const PRIORITY_URGENT = 'urgent';

    const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'depends_on_task_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(Task::class, 'depends_on_task_id');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(TimeLog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->with('replies.user');
    }

//    public function attachments(): HasMany
//    {
//        return $this->hasMany(Attachment::class);
//    }

    // ── Helpers ────────────────────────────────────────────────────────────
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isBlocked(): bool
    {
        return $this->depends_on_task_id !== null
            && optional($this->dependency)->status !== self::STATUS_COMPLETED;
    }

    public function totalLoggedMinutes(): int
    {
        return (int) $this->timeLogs()->whereNotNull('ended_at')->sum('duration_minutes');
    }

    /**
     * Count rows matching the given (already-scoped) query, grouped by status.
     * Callers own the scoping (project_id, department, assignee, etc.); this
     * only owns the counting SQL, so scoping logic is never duplicated.
     * Zero-fills statuses with no matching rows.
     */
    public static function statusCounts(Builder $query): array
    {
        $raw = (clone $query)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return collect(self::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => (int) ($raw[$status] ?? 0)])
            ->toArray();
    }

    /**
     * Count + estimated_hours per status for the given (already-scoped) query,
     * in one aggregate SQL query. Zero-fills statuses with no matching rows.
     * Callers own the scoping; this only owns the aggregation SQL.
     */
    public static function statusSummary(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(estimated_hours), 0) as estimated_hours')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect(self::STATUSES)
            ->mapWithKeys(fn ($status) => [
                $status => [
                    'count' => (int) ($rows[$status]->count ?? 0),
                    'estimated_hours' => (float) ($rows[$status]->estimated_hours ?? 0),
                ],
            ])
            ->toArray();
    }
}
