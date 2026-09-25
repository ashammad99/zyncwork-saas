<?php

namespace App\Features\TimeLog\Models;

use App\Features\Auth\Models\User;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use App\Core\Tenancy\BelongsToTenant;

class TimeLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'task_id',
        'user_id',
        'approved_by',
        'started_at',
        'ended_at',
        'duration_minutes',
        'entry_type',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
    ];

    const TYPE_TIMER  = 'timer';
    const TYPE_MANUAL = 'manual';

    // ── Relationships ──────────────────────────────────────────────────────
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_by !== null;
    }

    public function formattedDuration(): string
    {
        if ($this->duration_minutes === null) {
            return 'Running…';
        }

        $h = intdiv($this->duration_minutes, 60);
        $m = $this->duration_minutes % 60;

        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isRunning(): bool
    {
        return $this->entry_type === 'timer' && is_null($this->ended_at);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('entry_type', 'timer')->whereNull('ended_at');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('started_at', [$start, $end]);
    }

    /**
     * Total logged minutes across every task in a project, computed at the
     * database level (a single WHERE EXISTS subquery via whereHas) rather
     * than loading rows into PHP and summing them.
     */
    public static function totalMinutesForProject(int $projectId): int
    {
        return (int) static::whereHas('task', fn (Builder $q) => $q->where('project_id', $projectId))
            ->sum('duration_minutes');
    }

    /**
     * Logged minutes grouped by the parent task's status, for whatever tasks
     * the given (already-scoped) Task query builder matches. The scoped task
     * IDs are passed as a subquery (single SQL statement — no separate pluck,
     * no N+1), so callers' existing scoping is reused, never duplicated here.
     * Zero-fills statuses with no logged time.
     */
    public static function loggedMinutesByTaskStatus(\Illuminate\Database\Eloquent\Builder $taskQuery): array
    {
        $rows = static::query()
            ->join('tasks', 'tasks.id', '=', 'time_logs.task_id')
            ->whereIn('tasks.id', (clone $taskQuery)->select('tasks.id'))
            ->selectRaw('tasks.status as status, COALESCE(SUM(time_logs.duration_minutes), 0) as minutes')
            ->groupBy('tasks.status')
            ->pluck('minutes', 'status');

        return collect(Task::STATUSES)
            ->mapWithKeys(fn ($status) => [$status => (int) ($rows[$status] ?? 0)])
            ->toArray();
    }
}
