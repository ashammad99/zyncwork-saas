import React, { useState, useEffect, useMemo } from "react";
import { router } from "@inertiajs/react";
import {
    Play,
    Square,
    Clock,
    Plus,
    Users,
    ChevronDown,
    ChevronUp,
    Trash2,
} from "lucide-react";
import ManualEntryModal from "./ManualEntryModal.jsx";

/**
 * @param {object} props
 * @param {{ id: number }} props.task
 * @param {Array<object>} props.timeLogs
 * @param {{ id: number, name: string }} props.currentUser
 * @param {number} [props.estimatedHours] - Task's estimated hours (for progress bar)
 */
export default function EmployeeTaskTimerPanel({
                                                   task,
                                                   timeLogs = [],
                                                   currentUser,
                                                   estimatedHours,
                                               }) {
    // ── Derived data ───────────────────────────────────────────────────────
    const myLogs = useMemo(
        () => timeLogs.filter((l) => l.user_id === currentUser.id),
        [timeLogs, currentUser.id]
    );
    const othersLogs = useMemo(
        () => timeLogs.filter((l) => l.user_id !== currentUser.id),
        [timeLogs, currentUser.id]
    );

    const myActiveTimer = useMemo(
        () =>
            myLogs.find(
                (l) => l.entry_type === "timer" && l.ended_at === null
            ),
        [myLogs]
    );

    const myTotalMinutes = useMemo(
        () =>
            myLogs
                .filter((l) => l.duration_minutes !== null)
                .reduce((sum, l) => sum + Number(l.duration_minutes || 0), 0),
        [myLogs]
    );

    const teamTotalMinutes = useMemo(
        () =>
            othersLogs
                .filter((l) => l.duration_minutes !== null)
                .reduce((sum, l) => sum + Number(l.duration_minutes || 0), 0),
        [othersLogs]
    );

    const teamContributors = useMemo(() => {
        const ids = new Set(othersLogs.map((l) => l.user_id));
        return ids.size;
    }, [othersLogs]);

    const totalAllMinutes = myTotalMinutes + teamTotalMinutes;
    const estimatedMinutes = estimatedHours ? Number(estimatedHours) * 60 : null;
    const progressPct = estimatedMinutes
        ? Math.min((totalAllMinutes / estimatedMinutes) * 100, 999)
        : null;

    // ── UI state ───────────────────────────────────────────────────────────
    const [showHistory, setShowHistory] = useState(false);
    const [showManualModal, setShowManualModal] = useState(false);

    // ── Actions ────────────────────────────────────────────────────────────
    const startTimer = () => {
        router.post(
            `/tasks/${task.id}/time-logs/start`,
            {},
            { preserveScroll: true }
        );
    };

    const stopTimer = () => {
        if (!myActiveTimer) return;
        router.patch(
            `/tasks/${task.id}/time-logs/${myActiveTimer.id}/stop`,
            {},
            { preserveScroll: true }
        );
    };

    const deleteLog = (log) => {
        if (!confirm("Delete this time entry?")) return;
        router.delete(`/tasks/${task.id}/time-logs/${log.id}`, {
            preserveScroll: true,
        });
    };

    // ── Render ─────────────────────────────────────────────────────────────
    return (
        <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            {/* Header */}
            <div className="px-6 py-4 border-b border-gray-100 flex items-center gap-2">
                <Clock size={16} className="text-gray-400" />
                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500">
                    Time Tracking
                </h3>
            </div>

            {/* Big timer card */}
            {myActiveTimer ? (
                <RunningTimer
                    startedAt={myActiveTimer.started_at}
                    onStop={stopTimer}
                />
            ) : (
                <IdleTimer onStart={startTimer} />
            )}

            {/* My progress + team aggregate */}
            <div className="px-6 py-4 border-t border-gray-100 space-y-3">
                <div className="flex items-baseline justify-between">
                    <span className="text-xs text-gray-500 uppercase tracking-wider font-semibold">
                        My Time
                    </span>
                    <span className="text-lg font-bold text-gray-900">
                        {formatMinutes(myTotalMinutes)}
                    </span>
                </div>

                {estimatedMinutes && (
                    <>
                        <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                className={`h-full transition-all duration-500 ${progressBarColor(progressPct)}`}
                                style={{
                                    width: `${Math.min(progressPct, 100)}%`,
                                }}
                            />
                        </div>
                        <div className="flex justify-between text-[10px] text-gray-500">
                            <span>
                                Team total: <b className="text-gray-700">{formatMinutes(totalAllMinutes)}</b>
                            </span>
                            <span>
                                Est: {estimatedHours}h
                            </span>
                        </div>
                    </>
                )}

                {teamContributors > 0 && (
                    <div className="flex items-center gap-1.5 text-xs text-gray-500 pt-1">
                        <Users size={12} />
                        <span>
                            {teamContributors} teammate{teamContributors !== 1 && "s"} also logged{" "}
                            <b className="text-gray-700">{formatMinutes(teamTotalMinutes)}</b>
                        </span>
                    </div>
                )}
            </div>

            {/* My history */}
            {myLogs.length > 0 && (
                <div className="border-t border-gray-100">
                    <button
                        onClick={() => setShowHistory((v) => !v)}
                        className="w-full px-6 py-3 flex items-center justify-between hover:bg-gray-50 transition"
                    >
                        <span className="text-xs font-semibold text-gray-700">
                            My Sessions ({myLogs.length})
                        </span>
                        {showHistory ? (
                            <ChevronUp size={14} className="text-gray-400" />
                        ) : (
                            <ChevronDown size={14} className="text-gray-400" />
                        )}
                    </button>

                    {showHistory && (
                        <ul className="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                            {myLogs.map((log) => (
                                <MyLogRow
                                    key={log.id}
                                    log={log}
                                    onDelete={() => deleteLog(log)}
                                />
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {/* Manual entry footer */}
            <div className="border-t border-gray-100 px-6 py-3">
                <button
                    onClick={() => setShowManualModal(true)}
                    className="w-full flex items-center justify-center gap-1.5 text-xs font-medium text-gray-500 hover:text-primary transition"
                >
                    <Plus size={12} />
                    Forgot to start the timer? Log time manually
                </button>
            </div>

            {/* Manual entry modal */}
            {showManualModal && (
                <ManualEntryModal
                    taskId={task.id}
                    onClose={() => setShowManualModal(false)}
                />
            )}
        </div>
    );
}

/* ─── Running timer (active session) ─────────────────────── */

function RunningTimer({ startedAt, onStop }) {
    const [elapsed, setElapsed] = useState(computeElapsed(startedAt));

    useEffect(() => {
        const tick = () => setElapsed(computeElapsed(startedAt));
        tick();
        const id = setInterval(tick, 1000);

        // Re-sync when tab regains focus (in case system was asleep)
        const onFocus = () => tick();
        window.addEventListener("focus", onFocus);

        return () => {
            clearInterval(id);
            window.removeEventListener("focus", onFocus);
        };
    }, [startedAt]);

    return (
        <div className="p-6 bg-gradient-to-br from-emerald-50 to-green-50 border-b border-emerald-100">
            <div className="text-center">
                <div className="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-widest text-emerald-700 mb-2">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    Tracking
                </div>
                <div className="font-mono text-4xl font-bold text-emerald-900 tabular-nums tracking-tight">
                    {formatElapsed(elapsed)}
                </div>
                <button
                    onClick={onStop}
                    className="mt-4 inline-flex items-center gap-2 px-6 py-2.5 bg-red-600 text-white font-semibold text-sm rounded-lg hover:bg-red-700 transition shadow-sm"
                >
                    <Square size={14} fill="currentColor" />
                    Stop & Save
                </button>
            </div>
        </div>
    );
}

/* ─── Idle timer (no session running) ─────────────────────── */

function IdleTimer({ onStart }) {
    return (
        <div className="p-6 bg-gradient-to-br from-gray-50 to-slate-50 border-b border-gray-100">
            <div className="text-center">
                <div className="font-mono text-4xl font-bold text-gray-300 tabular-nums tracking-tight">
                    00:00:00
                </div>
                <button
                    onClick={onStart}
                    className="mt-4 inline-flex items-center gap-2 px-6 py-2.5 bg-primary text-white font-semibold text-sm rounded-lg hover:bg-primary/90 transition shadow-sm"
                >
                    <Play size={14} fill="currentColor" />
                    Start Timer
                </button>
                <p className="mt-2 text-[11px] text-gray-500">
                    Track your work time as you go
                </p>
            </div>
        </div>
    );
}

/* ─── Single log row (my session) ────────────────────────── */

function MyLogRow({ log, onDelete }) {
    const isRunning = log.entry_type === "timer" && log.ended_at === null;
    const isApproved = log.approved_by !== null;

    return (
        <li className="px-6 py-3 flex items-center gap-3 hover:bg-gray-50 group">
            <div
                className={`w-1 h-8 rounded-full shrink-0 ${
                    log.entry_type === "timer" ? "bg-blue-400" : "bg-purple-400"
                }`}
            />
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 text-xs text-gray-600">
                    <span>{formatDateTime(log.started_at)}</span>
                    {isApproved && (
                        <span className="text-[9px] font-bold uppercase text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">
                            Approved
                        </span>
                    )}
                </div>
                {log.notes && (
                    <div className="text-xs text-gray-500 italic truncate mt-0.5">
                        "{log.notes}"
                    </div>
                )}
            </div>
            <div className="text-sm font-semibold text-gray-900 shrink-0">
                {isRunning ? (
                    <span className="text-emerald-600 text-xs">running…</span>
                ) : (
                    formatMinutes(log.duration_minutes)
                )}
            </div>
            {!isApproved && !isRunning && (
                <button
                    onClick={onDelete}
                    className="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-600 transition"
                    title="Delete entry"
                >
                    <Trash2 size={14} />
                </button>
            )}
        </li>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

function computeElapsed(startedAtIso) {
    return Math.floor((Date.now() - new Date(startedAtIso).getTime()) / 1000);
}

function formatElapsed(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600)
        .toString()
        .padStart(2, "0");
    const m = Math.floor((totalSeconds % 3600) / 60)
        .toString()
        .padStart(2, "0");
    const s = (totalSeconds % 60).toString().padStart(2, "0");
    return `${h}:${m}:${s}`;
}

function formatMinutes(min) {
    if (!min || min <= 0) return "0m";
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}

function formatDateTime(iso) {
    return new Date(iso).toLocaleString(undefined, {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

function progressBarColor(pct) {
    if (pct < 80) return "bg-emerald-500";
    if (pct < 100) return "bg-amber-500";
    return "bg-red-500";
}
