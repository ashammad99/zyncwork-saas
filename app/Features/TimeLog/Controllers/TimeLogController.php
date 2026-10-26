<?php

namespace App\Features\TimeLog\Controllers;

use App\Core\BaseController;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use App\Features\TimeLog\Requests\StoreManualTimeLogRequest;
use App\Features\TimeLog\Services\TimeLogService;
use App\Features\TimeLog\Services\TimerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeLogController extends BaseController
{
    public function __construct(
        private readonly TimerService $timerService,
        private readonly TimeLogService $timeLogService,
    ) {}

    // ── Start Timer ────────────────────────────────────────────────────────
    public function start(Request $request, Task $task)
    {
        $this->authorize('create', TimeLog::class);

        $user = $request->user();

        if ($user->role === 'employee' && $task->assignee_id !== $user->id) {
            abort(403, 'You can only log time on your assigned tasks.');
        }

        $log = $this->timerService->start($user, $task);

        return back()->with([
            'success' => 'Timer started.',
            'active_log_id' => $log->id,
        ]);
    }

    // ── Stop Timer ─────────────────────────────────────────────────────────
    public function stop(Request $request, Task $task, TimeLog $timeLog)
    {
        $user = $request->user();

        if ($timeLog->user_id !== $user->id && $user->role !== 'admin') {
            abort(403);
        }

        $log = $this->timerService->stopTimerById($timeLog);

        return back()->with('success', "Timer stopped. Duration: {$log->formattedDuration()}.");
    }

    // ── Manual Entry ───────────────────────────────────────────────────────
    public function storeManual(StoreManualTimeLogRequest $request, Task $task)
    {
        $user = $request->user();

        if ($user->role === 'employee' && $task->assignee_id !== $user->id) {
            abort(403, 'You can only log time on your assigned tasks.');
        }

        $this->timeLogService->createManual($user, $task, $request->validated());

        return back()->with('success', 'Time entry added.');
    }

    // ── Delete ─────────────────────────────────────────────────────────────
    public function destroy(Request $request, Task $task, TimeLog $timeLog)
    {
        $this->authorize('delete', $timeLog);

        $this->timeLogService->delete($timeLog);

        return back()->with('success', 'Time entry deleted.');
    }

    public function approve(Request $request, Task $task, TimeLog $timeLog): RedirectResponse
    {
        $this->authorize('approve', $timeLog);

        $this->timeLogService->approve($timeLog, $request->user());

        return back()->with('success', 'Time entry approved.');
    }

    // ── Revoke approval ────────────────────────────────────────────────────
    public function reject(Request $request, Task $task, TimeLog $timeLog): RedirectResponse
    {
        $this->authorize('reject', $timeLog);

        $this->timeLogService->reject($timeLog);

        return back()->with('success', 'Approval revoked.');
    }

    public function managerApprove(Request $request, TimeLog $timeLog): RedirectResponse
    {
        $this->authorize('approve', $timeLog);

        $this->timeLogService->approve($timeLog, $request->user());

        return back()->with('success', 'Time entry approved.');
    }

    public function managerReject(Request $request, TimeLog $timeLog): RedirectResponse
    {
        $this->authorize('reject', $timeLog);

        $this->timeLogService->reject($timeLog);

        return back()->with('success', 'Time entry rejected.');
    }
}
