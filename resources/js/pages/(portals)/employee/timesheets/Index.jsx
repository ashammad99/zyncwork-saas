import React, { useState, useEffect } from "react";
import { Head, Link, router } from "@inertiajs/react";
import EmployeeLayout from "../layout";
import ManualEntryModal from "./ManualEntryModal";
import {
    Clock,
    ChevronLeft,
    ChevronRight,
    Plus,
    Trash2,
    Square,
    CheckCircle2,
    CalendarDays,
    Download
} from "lucide-react";

const DAY_LABELS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

/**
 * @param {object} props
 * @param {Array<object>} props.logs
 * @param {object|null} props.activeTimer
 * @param {string} props.weekStart
 * @param {string} props.weekEnd
 * @param {Array<{ id, title, project_name }>} props.tasks
 * @param {{ total_minutes, total_hours, capacity_hours, utilization_pct, by_day }} props.summary
 */
export default function EmployeeTimesheet({
                                              logs = [],
                                              activeTimer = null,
                                              weekStart,
                                              weekEnd,
                                              tasks = [],
                                              summary = {},
                                          }) {
    const [showManual, setShowManual] = useState(false);
    const [manualTaskId, setManualTaskId] = useState(tasks[0]?.id ?? null);

    // ── Week navigation ────────────────────────────────────────────────────
    const goWeek = (deltaDays) => {
        const d = new Date(weekStart);
        d.setDate(d.getDate() + deltaDays);
        router.get("/timesheets", { week: d.toISOString().slice(0, 10) }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const goToday = () => {
        router.get("/timesheets", {}, { preserveScroll: true, replace: true });
    };

    // ── Actions ────────────────────────────────────────────────────────────
    const stopTimer = () => {
        if (!activeTimer) return;
        router.patch(
            `/tasks/${activeTimer.task_id}/time-logs/${activeTimer.id}/stop`,
            {},
            { preserveScroll: true }
        );
    };

    const deleteLog = (log) => {
        if (!confirm("Delete this time entry?")) return;
        router.delete(`/tasks/${log.task_id}/time-logs/${log.id}`, {
            preserveScroll: true,
        });
    };

    // ── Group logs by day for the table ────────────────────────────────────
    const byDay = groupByDay(logs);
    const weekDates = buildWeekDates(weekStart);

    return (
        <EmployeeLayout>
            <Head title="My Timesheet" />

            <div className="max-w-5xl mx-auto space-y-6 py-6">

                {/* Header */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">My Timesheet</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {formatRange(weekStart, weekEnd)}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => setShowManual(true)}
                        disabled={tasks.length === 0}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
                    >
                        <Plus size={14} />
                        Add Entry
                    </button>
                </div>

                {/* Week navigator */}
                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => goWeek(-7)}
                        className="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                        title="Previous week"
                    >
                        <ChevronLeft size={16} />
                    </button>
                    <button
                        type="button"
                        onClick={goToday}
                        className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                    >
                        This Week
                    </button>
                    <button
                        type="button"
                        onClick={() => goWeek(7)}
                        className="p-2 border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                        title="Next week"
                    >
                        <ChevronRight size={16} />
                    </button>
                </div>

                {/* Running timer banner */}
                {activeTimer && (
                    <div className="flex items-center justify-between gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3">
                        <div className="flex items-center gap-2 text-sm text-emerald-800 min-w-0">
                            <span className="relative flex h-2 w-2 shrink-0">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span className="truncate">
                                Timer running on{" "}
                                <Link
                                    href={`/employee/tasks/${activeTimer.task_id}`}
                                    className="font-semibold underline hover:no-underline"
                                >
                                    {activeTimer.task?.title ?? "a task"}
                                </Link>
                            </span>
                        </div>
                        <button
                            type="button"
                            onClick={stopTimer}
                            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold bg-red-600 text-white rounded-lg hover:bg-red-700 transition shrink-0"
                        >
                            <Square size={12} fill="currentColor" />
                            Stop
                        </button>
                    </div>
                )}

                {/* Summary: week total + daily bars */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <div className="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                            Week Total
                        </div>
                        <div className="text-3xl font-bold text-gray-900">
                            {(summary.total_hours ?? 0).toFixed(1)}h
                        </div>
                        <div className="text-xs text-gray-500 mt-1">
                            of {summary.capacity_hours ?? 40}h capacity
                        </div>
                        <div className="mt-3 w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div
                                className={`h-full rounded-full transition-all ${utilizationBar(summary.utilization_pct ?? 0)}`}
                                style={{ width: `${Math.min(summary.utilization_pct ?? 0, 100)}%` }}
                            />
                        </div>
                        <div className="text-xs text-gray-500 mt-1.5">
                            {Math.round(summary.utilization_pct ?? 0)}% utilization
                        </div>
                    </div>

                    {/* Daily breakdown */}
                    <div className="md:col-span-2 bg-white border border-gray-200 rounded-2xl p-5 shadow-sm">
                        <div className="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-4">
                            Daily Breakdown
                        </div>
                        <DailyBars byDay={summary.by_day ?? {}} weekDates={weekDates} />
                    </div>
                </div>

                {/* Entries grouped by day */}
                {logs.length === 0 ? (
                    <div className="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center">
                        <CalendarDays size={24} className="mx-auto mb-3 text-gray-300" />
                        <h3 className="font-semibold text-gray-900 mb-1">No time logged this week</h3>
                        <p className="text-sm text-gray-500">
                            Start a timer from a task page, or add a manual entry.
                        </p>
                    </div>
                ) : (
                    Object.entries(byDay).map(([date, dayLogs]) => (
                        <DayGroup
                            key={date}
                            date={date}
                            logs={dayLogs}
                            onDelete={deleteLog}
                        />
                    ))
                )}
            </div>

            {/* Manual entry: task picker + modal */}
            {showManual && (
                <TaskPickerModal
                    tasks={tasks}
                    taskId={manualTaskId}
                    setTaskId={setManualTaskId}
                    onClose={() => setShowManual(false)}
                />
            )}

        </EmployeeLayout>
    );
}

/* ─── Daily bar chart ────────────────────────────────────── */

function DailyBars({ byDay, weekDates }) {
    const values = weekDates.map((d) => Number(byDay[d] ?? 0));
    const max = Math.max(...values, 60); // min scale of 1h so tiny bars don't fill the chart

    return (
        <div className="flex items-end justify-between gap-2 h-24">
            {weekDates.map((date, i) => {
                const minutes = values[i];
                const heightPct = (minutes / max) * 100;
                const isToday = date === new Date().toISOString().slice(0, 10);

                return (
                    <div key={date} className="flex-1 flex flex-col items-center gap-1.5 min-w-0">
                        <div className="text-[10px] text-gray-500 font-medium">
                            {minutes > 0 ? (minutes / 60).toFixed(1) : ""}
                        </div>
                        <div className="w-full h-16 flex items-end">
                            <div
                                className={`w-full rounded-t transition-all ${minutes > 0 ? "bg-primary/70" : "bg-gray-100"}`}
                                style={{ height: `${Math.max(heightPct, 4)}%` }}
                            />
                        </div>
                        <div className={`text-[10px] font-medium ${isToday ? "text-primary font-bold" : "text-gray-400"}`}>
                            {DAY_LABELS[i]}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/* ─── One day's entries ──────────────────────────────────── */

function DayGroup({ date, logs, onDelete }) {
    const dayTotal = logs.reduce((s, l) => s + Number(l.duration_minutes || 0), 0);

    return (
        <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div className="px-6 py-3 flex items-center justify-between bg-gray-50/50 border-b border-gray-100">
                <span className="text-sm font-semibold text-gray-900">
                    {formatDayHeading(date)}
                </span>
                <span className="text-xs font-semibold text-gray-500">
                    {formatMinutes(dayTotal)}
                </span>
            </div>
            <ul className="divide-y divide-gray-50">
                {logs.map((log) => {
                    const isRunning = !log.ended_at;
                    const isApproved = !!log.approved_by;

                    return (
                        <li key={log.id} className="px-6 py-3 flex items-center gap-3 group">
                            <div className={`w-1 h-9 rounded-full shrink-0 ${log.entry_type === "timer" ? "bg-blue-400" : "bg-purple-400"}`} />
                            <div className="flex-1 min-w-0">
                                <div className="text-sm font-medium text-gray-900 truncate">
                                    {log.task?.title ?? "—"}
                                </div>
                                <div className="text-xs text-gray-500 flex items-center gap-2 flex-wrap">
                                    <span>{log.task?.project?.name ?? "—"}</span>
                                    <span>·</span>
                                    <span>
                                        {formatTime(log.started_at)} → {log.ended_at ? formatTime(log.ended_at) : "…"}
                                    </span>
                                    {isApproved && (
                                        <span className="inline-flex items-center gap-0.5 text-[9px] font-bold uppercase text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded">
                                            <CheckCircle2 size={9} />
                                            Approved
                                        </span>
                                    )}
                                </div>
                                {log.notes && (
                                    <div className="text-xs text-gray-500 italic truncate mt-0.5">"{log.notes}"</div>
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
                                    type="button"
                                    onClick={() => onDelete(log)}
                                    className=" p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition shrink-0"
                                    title="Delete"
                                >
                                    <Trash2 size={18} />
                                </button>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}

/* ─── Task picker wrapping the ManualEntryModal ──────────── */

function TaskPickerModal({ tasks, taskId, setTaskId, onClose }) {
    const [step, setStep] = useState(taskId ? "form" : "pick");

    if (step === "form" && taskId) {
        return <ManualEntryModal taskId={taskId} onClose={onClose} />;
    }

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            onClick={onClose}
        >
            <div
                className="w-full max-w-md bg-white rounded-2xl shadow-2xl p-6"
                onClick={(e) => e.stopPropagation()}
            >
                <h2 className="text-lg font-bold text-gray-900 mb-1">Which task?</h2>
                <p className="text-xs text-gray-500 mb-4">Pick the task this time belongs to.</p>
                <select
                    value={taskId ?? ""}
                    onChange={(e) => setTaskId(Number(e.target.value))}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm mb-4"
                >
                    {tasks.map((t) => (
                        <option key={t.id} value={t.id}>
                            {t.project_name ? `${t.project_name} — ` : ""}{t.title}
                        </option>
                    ))}
                </select>
                <div className="flex justify-end gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={() => setStep("form")}
                        disabled={!taskId}
                        className="px-4 py-2 text-sm bg-primary text-white font-semibold rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
                    >
                        Continue
                    </button>
                </div>
            </div>
        </div>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

function groupByDay(logs) {
    const groups = {};
    for (const log of logs) {
        const date = log.started_at.slice(0, 10);
        (groups[date] ??= []).push(log);
    }
    return groups; // logs arrive sorted desc from the controller
}

function buildWeekDates(weekStart) {
    const dates = [];
    const d = new Date(weekStart);
    for (let i = 0; i < 7; i++) {
        dates.push(d.toISOString().slice(0, 10));
        d.setDate(d.getDate() + 1);
    }
    return dates;
}

function formatRange(from, to) {
    const opts = { month: "short", day: "numeric" };
    return `${new Date(from).toLocaleDateString(undefined, opts)} – ${new Date(to).toLocaleDateString(undefined, { ...opts, year: "numeric" })}`;
}

function formatDayHeading(iso) {
    return new Date(iso).toLocaleDateString(undefined, {
        weekday: "long",
        month: "short",
        day: "numeric",
    });
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" });
}

function formatMinutes(min) {
    if (!min || min <= 0) return "0m";
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}

function utilizationBar(pct) {
    if (pct < 60) return "bg-gray-400";
    if (pct <= 100) return "bg-emerald-500";
    if (pct <= 120) return "bg-amber-500";
    return "bg-red-500";
}
