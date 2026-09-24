// resources/js/pages/(portals)/admin/tasks/partials/TimeLogPanel.jsx
// Drop this inside your Task Show page — works for admin, manager, employee

import React, { useState, useEffect, useRef } from "react";
import { router, useForm } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import {
    Play,
    Square,
    Plus,
    Trash2,
    CheckCircle,
    Clock,
    Timer,
    PenLine,
    RefreshCw,
    X,
} from "lucide-react";

// ── Helpers ────────────────────────────────────────────────────────────────────

function formatDuration(minutes) {
    if (minutes === null || minutes === undefined) return "—";
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function formatDatetime(iso) {
    if (!iso) return "—";
    return new Date(iso).toLocaleString("en-GB", {
        day: "2-digit", month: "short", year: "numeric",
        hour: "2-digit", minute: "2-digit",
    });
}

// ── Live Timer Display ────────────────────────────────────────────────────────

function LiveTimer({ startedAt }) {
    const [elapsed, setElapsed] = useState(0);

    useEffect(() => {
        const start = new Date(startedAt).getTime();

        // Set initial
        setElapsed(Math.floor((Date.now() - start) / 1000));

        const interval = setInterval(() => {
            setElapsed(Math.floor((Date.now() - start) / 1000));
        }, 1000);

        return () => clearInterval(interval);
    }, [startedAt]);

    const h = Math.floor(elapsed / 3600);
    const m = Math.floor((elapsed % 3600) / 60);
    const s = elapsed % 60;

    return (
        <span className="font-mono text-lg font-bold text-primary tabular-nums">
            {String(h).padStart(2, "0")}:{String(m).padStart(2, "0")}:{String(s).padStart(2, "0")}
        </span>
    );
}

// ── Manual Entry Modal ────────────────────────────────────────────────────────

function ManualEntryModal({ task, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        date:       new Date().toISOString().split("T")[0],
        started_at: "",
        ended_at:   "",
        notes:      "",
    });

    function submit(e) {
        e.preventDefault();
        post(`/tasks/${task.id}/time-logs/manual`, {
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-1">Manual Time Entry</h2>
            <p className="text-sm text-gray-500 mb-6 truncate">Task: {task.title}</p>

            <form onSubmit={submit} className="space-y-4">
                {/* Date */}
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Date <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="date"
                        value={data.date}
                        max={new Date().toISOString().split("T")[0]}
                        onChange={e => setData("date", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.date && <p className="mt-1 text-xs text-red-600">{errors.date}</p>}
                </div>

                {/* Start + End Time */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">
                            Start Time <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="time"
                            value={data.started_at}
                            onChange={e => setData("started_at", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        />
                        {errors.started_at && <p className="mt-1 text-xs text-red-600">{errors.started_at}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">
                            End Time <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="time"
                            value={data.ended_at}
                            onChange={e => setData("ended_at", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        />
                        {errors.ended_at && <p className="mt-1 text-xs text-red-600">{errors.ended_at}</p>}
                    </div>
                </div>

                {/* Live duration preview */}
                {data.started_at && data.ended_at && data.started_at < data.ended_at && (
                    <div className="flex items-center gap-2 rounded-lg bg-primary/5 border border-primary/10 px-3 py-2">
                        <Clock size={14} className="text-primary" />
                        <span className="text-sm font-semibold text-primary">
                            {(() => {
                                const [sh, sm] = data.started_at.split(":").map(Number);
                                const [eh, em] = data.ended_at.split(":").map(Number);
                                const diff = (eh * 60 + em) - (sh * 60 + sm);
                                return formatDuration(diff);
                            })()}
                        </span>
                        <span className="text-xs text-gray-400">will be logged</span>
                    </div>
                )}

                {/* Notes */}
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                    <textarea
                        rows={3}
                        value={data.notes}
                        onChange={e => setData("notes", e.target.value)}
                        placeholder="What did you work on?"
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm resize-none"
                    />
                    {errors.notes && <p className="mt-1 text-xs text-red-600">{errors.notes}</p>}
                </div>

                <div className="flex justify-end gap-3 mt-6">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={processing}
                        className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2"
                    >
                        {processing && <RefreshCw size={14} className="animate-spin" />}
                        Save Entry
                    </button>
                </div>
            </form>
        </div>
    );
}

// ── Main TimeLog Panel ────────────────────────────────────────────────────────

export default function TimeLogPanel({ task, timeLogs, currentUser }) {
    const { openModal, closeModal } = useModal();
    const [stoppingId, setStoppingId] = useState(null);
    const [startingTimer, setStartingTimer] = useState(false);

    // Find if current user has an active timer on this task
    const myActiveLog = timeLogs.find(
        l => l.user_id === currentUser.id && l.ended_at === null
    );

    // Summary stats
    const totalMinutes = timeLogs
        .filter(l => l.ended_at !== null)
        .reduce((sum, l) => sum + (l.duration_minutes ?? 0), 0);

    const estHours = task.estimated_hours ? parseFloat(task.estimated_hours) : null;
    const totalHours = (totalMinutes / 60).toFixed(1);
    const pct = estHours ? Math.min(100, Math.round((totalMinutes / 60 / estHours) * 100)) : null;
    const barColor = pct > 100 ? "bg-red-500" : pct > 80 ? "bg-amber-500" : "bg-emerald-500";

    function handleStartTimer() {
        setStartingTimer(true);
        router.post(`/tasks/${task.id}/time-logs/start`, {}, {
            onFinish: () => setStartingTimer(false),
        });
    }

    function handleStopTimer(logId) {
        setStoppingId(logId);
        router.patch(`/tasks/${task.id}/time-logs/${logId}/stop`, {}, {
            onFinish: () => setStoppingId(null),
        });
    }

    function handleDelete(logId) {
        if (!confirm("Delete this time entry?")) return;
        router.delete(`/tasks/${task.id}/time-logs/${logId}`);
    }

    function handleApprove(logId) {
        router.patch(`/tasks/${task.id}/time-logs/${logId}/approve`);
    }

    function openManualModal() {
        openModal(
            <ManualEntryModal task={task} onClose={closeModal} />,
            { size: "md" }
        );
    }

    const canApprove = ["admin", "manager"].includes(currentUser.role);
    const canLog     = task.status !== "completed" || currentUser.role === "admin";

    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">

            {/* ── Header ── */}
            <div className="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div className="flex items-center gap-2">
                    <Timer size={16} className="text-gray-400" />
                    <h3 className="text-sm font-bold text-gray-700">Time Logs</h3>
                    {timeLogs.length > 0 && (
                        <span className="text-xs text-gray-400 bg-gray-100 rounded-full px-2 py-0.5">
                            {timeLogs.length}
                        </span>
                    )}
                </div>

                {canLog && (
                    <div className="flex items-center gap-2">
                        {/* Manual Entry */}
                        <button
                            onClick={openManualModal}
                            className="flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-600 text-xs font-semibold rounded-lg hover:bg-gray-50 transition"
                        >
                            <PenLine size={13} />
                            Manual
                        </button>

                        {/* Start / Stop Timer */}
                        {myActiveLog ? (
                            <button
                                onClick={() => handleStopTimer(myActiveLog.id)}
                                disabled={stoppingId === myActiveLog.id}
                                className="flex items-center gap-1.5 px-3 py-1.5 bg-red-50 border border-red-200 text-red-600 text-xs font-semibold rounded-lg hover:bg-red-100 transition disabled:opacity-50"
                            >
                                {stoppingId === myActiveLog.id
                                    ? <RefreshCw size={13} className="animate-spin" />
                                    : <Square size={13} />
                                }
                                Stop
                            </button>
                        ) : (
                            <button
                                onClick={handleStartTimer}
                                disabled={startingTimer}
                                className="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-semibold rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
                            >
                                {startingTimer
                                    ? <RefreshCw size={13} className="animate-spin" />
                                    : <Play size={13} />
                                }
                                Start
                            </button>
                        )}
                    </div>
                )}
            </div>

            {/* ── Active Timer Banner ── */}
            {myActiveLog && (
                <div className="flex items-center justify-between gap-3 px-5 py-3 bg-primary/5 border-b border-primary/10">
                    <div className="flex items-center gap-2">
                        <span className="h-2 w-2 rounded-full bg-primary animate-pulse" />
                        <span className="text-xs font-semibold text-primary">Timer running</span>
                    </div>
                    <LiveTimer startedAt={myActiveLog.started_at} />
                </div>
            )}

            {/* ── Summary Bar ── */}
            {timeLogs.length > 0 && (
                <div className="px-5 py-3 bg-gray-50/50 border-b border-gray-100">
                    <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs text-gray-500">Total logged</span>
                        <span className="text-xs font-bold text-gray-700">
                            {totalHours}h
                            {estHours && <span className="font-normal text-gray-400"> / {estHours}h est.</span>}
                        </span>
                    </div>
                    {pct !== null && (
                        <div className="h-1.5 w-full rounded-full bg-gray-200 overflow-hidden">
                            <div className={`h-full rounded-full transition-all ${barColor}`} style={{ width: `${pct}%` }} />
                        </div>
                    )}
                </div>
            )}

            {/* ── Logs List ── */}
            {timeLogs.length === 0 ? (
                <div className="py-10 text-center">
                    <Clock size={28} className="mx-auto mb-2 text-gray-200" />
                    <p className="text-sm text-gray-400 font-medium">No time logged yet</p>
                    <p className="text-xs text-gray-300 mt-0.5">Start the timer or add a manual entry</p>
                </div>
            ) : (
                <div className="divide-y divide-gray-100">
                    {timeLogs.map(log => {
                        const isRunning = log.ended_at === null;
                        const isMine    = log.user_id === currentUser.id;
                        const canDelete = isMine || canApprove;

                        return (
                            <div key={log.id} className={`px-5 py-3.5 ${isRunning ? "bg-primary/3" : ""}`}>
                                <div className="flex items-start justify-between gap-3">
                                    {/* Left: user + time info */}
                                    <div className="flex items-start gap-3 min-w-0">
                                        {/* Avatar */}
                                        <div className="h-7 w-7 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary shrink-0 mt-0.5">
                                            {log.user?.name?.charAt(0).toUpperCase()}
                                        </div>

                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="text-sm font-semibold text-gray-800">{log.user?.name}</span>

                                                {/* Entry type badge */}
                                                <span className={`text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded
                                                    ${log.entry_type === "timer"
                                                    ? "bg-blue-50 text-blue-600"
                                                    : "bg-gray-100 text-gray-500"
                                                }`}>
                                                    {log.entry_type}
                                                </span>

                                                {/* Running indicator */}
                                                {isRunning && (
                                                    <span className="flex items-center gap-1 text-[10px] font-bold text-primary">
                                                        <span className="h-1.5 w-1.5 rounded-full bg-primary animate-pulse" />
                                                        Running
                                                    </span>
                                                )}

                                                {/* Approved badge */}
                                                {log.approved_by && (
                                                    <span className="flex items-center gap-1 text-[10px] font-semibold text-emerald-600">
                                                        <CheckCircle size={10} />
                                                        Approved
                                                    </span>
                                                )}
                                            </div>

                                            {/* Time range */}
                                            <p className="text-xs text-gray-400 mt-0.5">
                                                {formatDatetime(log.started_at)}
                                                {log.ended_at && <> → {formatDatetime(log.ended_at)}</>}
                                            </p>

                                            {/* Notes */}
                                            {log.notes && (
                                                <p className="text-xs text-gray-500 mt-1 italic">"{log.notes}"</p>
                                            )}
                                        </div>
                                    </div>

                                    {/* Right: duration + actions */}
                                    <div className="flex items-center gap-2 shrink-0">
                                        {/* Duration */}
                                        <div className="text-right">
                                            {isRunning && isMine ? (
                                                <LiveTimer startedAt={log.started_at} />
                                            ) : (
                                                <span className="text-sm font-bold text-gray-700">
                                                    {formatDuration(log.duration_minutes)}
                                                </span>
                                            )}
                                        </div>

                                        {/* Stop (own running timer) */}
                                        {isRunning && isMine && (
                                            <button
                                                onClick={() => handleStopTimer(log.id)}
                                                disabled={stoppingId === log.id}
                                                className="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                                title="Stop timer"
                                            >
                                                {stoppingId === log.id
                                                    ? <RefreshCw size={14} className="animate-spin" />
                                                    : <Square size={14} />
                                                }
                                            </button>
                                        )}

                                        {/* Approve (manager/admin, not running, not yet approved) */}
                                        {canApprove && !isRunning && !log.approved_by && (
                                            <button
                                                onClick={() => handleApprove(log.id)}
                                                className="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition"
                                                title="Approve entry"
                                            >
                                                <CheckCircle size={14} />
                                            </button>
                                        )}

                                        {/* Delete */}
                                        {canDelete && (
                                            <button
                                                onClick={() => handleDelete(log.id)}
                                                className="p-1.5 text-gray-300 hover:text-red-600 hover:bg-red-50 rounded-lg transition"
                                                title="Delete entry"
                                            >
                                                <Trash2 size={14} />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
