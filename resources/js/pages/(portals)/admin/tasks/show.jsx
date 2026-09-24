// resources/js/pages/(portals)/admin/tasks/show.jsx

import React, { useState } from "react";
import { RECURRING_TASKS_ENABLED } from "@/Utils/features";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import CommentThread from "@/Components/comments/CommentThread";
import EditTaskForm from "./partials/EditTaskForm";

import {
    ArrowLeft,
    Edit,
    Trash2,
    ChevronRight,
    AlertCircle,
    Clock,
    User,
    RefreshCw,
    CheckCircle2,
    RotateCcw,
} from "lucide-react";

// ── Local badge helpers ───────────────────────────────────────────────────────

const STATUS_STYLES = {
    backlog:     "bg-gray-100 text-gray-600",
    todo:        "bg-blue-50 text-blue-700",
    in_progress: "bg-amber-50 text-amber-700",
    in_review:   "bg-violet-50 text-violet-700",
    completed:   "bg-emerald-50 text-emerald-700",
};
const STATUS_LABELS = {
    backlog: "Backlog", todo: "To Do", in_progress: "In Progress",
    in_review: "In Review", completed: "Completed",
};
const PRIORITY_DOTS = {
    low: "bg-gray-400", medium: "bg-sky-500", high: "bg-orange-500", urgent: "bg-red-500",
};
const PRIORITY_STYLES = {
    low: "bg-gray-100 text-gray-500", medium: "bg-sky-50 text-sky-700",
    high: "bg-orange-50 text-orange-700", urgent: "bg-red-50 text-red-700 font-semibold",
};
const MATRIX_MAP = {
    "high-low":  { label: "🟢 Quick Win",     cls: "text-emerald-700 bg-emerald-50" },
    "high-high": { label: "🟡 Major Project", cls: "text-amber-700 bg-amber-50" },
    "low-low":   { label: "🔵 Fill-in Task",  cls: "text-sky-700 bg-sky-50" },
    "low-high":  { label: "🔴 Reconsider",    cls: "text-red-700 bg-red-50" },
};
const STATUS_FLOW = ["backlog", "todo", "in_progress", "in_review", "completed"];
const ACTION_COLORS = {
    created: "bg-emerald-500", updated: "bg-blue-500", deleted: "bg-red-500",
    status_changed: "bg-violet-500", assigned: "bg-amber-500",
    dependency_reactivated: "bg-orange-500",
};

// ── StatusBadge ───────────────────────────────────────────────────────────────
function StatusBadge({ status, size = "sm" }) {
    const cls = size === "lg" ? "px-3 py-1 text-sm" : "px-2.5 py-0.5 text-xs";
    return (
        <span className={`inline-flex items-center rounded-full font-medium ${cls} ${STATUS_STYLES[status] ?? "bg-gray-100 text-gray-600"}`}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

// ── StatusPanel ───────────────────────────────────────────────────────────────
function StatusPanel({ task }) {
    const [loading, setLoading] = useState(false);
    const currentIdx = STATUS_FLOW.indexOf(task.status);
    const nextStatus = STATUS_FLOW[currentIdx + 1];

    function transition(newStatus) {
        if (loading) return;
        setLoading(true);
        router.patch(`/admin/tasks/${task.id}/status`, { status: newStatus }, {
            onFinish: () => setLoading(false),
        });
    }

    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-5">
            <h3 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-4">Status Pipeline</h3>

            <div className="space-y-1 mb-5">
                {STATUS_FLOW.map((s, i) => (
                    <div
                        key={s}
                        className={`flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors
                            ${s === task.status ? "bg-primary/10 text-primary" : i < currentIdx ? "text-emerald-600" : "text-gray-300"}`}
                    >
                        {i < currentIdx ? (
                            <CheckCircle2 size={16} className="text-emerald-500 shrink-0" />
                        ) : s === task.status ? (
                            <span className="h-2 w-2 rounded-full bg-primary shrink-0" />
                        ) : (
                            <span className="h-2 w-2 rounded-full bg-gray-200 shrink-0" />
                        )}
                        {STATUS_LABELS[s]}
                    </div>
                ))}
            </div>

            {task.is_blocked && task.dependency && (
                <div className="mb-4 flex items-start gap-2 rounded-lg bg-red-50 border border-red-100 px-3 py-2.5">
                    <AlertCircle size={14} className="text-red-500 mt-0.5 shrink-0" />
                    <p className="text-xs text-red-600 font-medium">
                        Blocked by: <span className="font-semibold">"{task.dependency.title}"</span>
                    </p>
                </div>
            )}

            <div className="space-y-2">
                {nextStatus && task.status !== "completed" && (
                    <button
                        onClick={() => transition(nextStatus)}
                        disabled={loading || task.is_blocked}
                        className="w-full flex items-center justify-center gap-2 py-2.5 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 disabled:opacity-40 transition"
                    >
                        {loading ? <RefreshCw size={15} className="animate-spin" /> : <ChevronRight size={15} />}
                        Move to {STATUS_LABELS[nextStatus]}
                    </button>
                )}

                {task.status === "in_review" && (
                    <button
                        onClick={() => transition("in_progress")}
                        disabled={loading}
                        className="w-full flex items-center justify-center gap-2 py-2.5 border border-amber-200 bg-amber-50 text-amber-700 text-sm font-semibold rounded-xl hover:bg-amber-100 disabled:opacity-40 transition"
                    >
                        <RotateCcw size={15} />
                        Send back to In Progress
                    </button>
                )}

                {task.status === "completed" && (
                    <button
                        onClick={() => transition("todo")}
                        disabled={loading}
                        className="w-full flex items-center justify-center gap-2 py-2.5 border border-orange-200 bg-orange-50 text-orange-700 text-sm font-semibold rounded-xl hover:bg-orange-100 disabled:opacity-40 transition"
                    >
                        <RotateCcw size={15} />
                        Reactivate Task
                    </button>
                )}
            </div>
        </div>
    );
}

// ── AssignPanel ───────────────────────────────────────────────────────────────
function AssignPanel({ task, users }) {
    const { data, setData, patch, processing, errors } = useForm({
        assignee_id: task.assignee_id ?? "",
    });

    function submit(e) {
        e.preventDefault();
        patch(`/admin/tasks/${task.id}/assign`);
    }

    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-5">
            <h3 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-4">Assignee</h3>
            <form onSubmit={submit} className="flex gap-2">
                <select
                    value={data.assignee_id ?? ""}
                    onChange={e => setData("assignee_id", e.target.value || null)}
                    className="flex-1 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                >
                    <option value="">Unassigned</option>
                    {(users ?? []).map(u => (
                        <option key={u.id} value={u.id}>{u.name}</option>
                    ))}
                </select>
                <button
                    type="submit"
                    disabled={processing}
                    className="px-3 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 disabled:opacity-50 transition flex items-center gap-1"
                >
                    {processing ? <RefreshCw size={14} className="animate-spin" /> : null}
                    Assign
                </button>
            </form>
            {errors.assignee_id && <p className="mt-1 text-xs text-red-600">{errors.assignee_id}</p>}
        </div>
    );
}

// ── TimePanel ─────────────────────────────────────────────────────────────────
function TimePanel({ timeLogs, estimatedHours }) {
    const totalMins = (timeLogs ?? []).filter(l => l.ended_at).reduce((s, l) => s + (l.duration_minutes ?? 0), 0);
    const totalHrs  = (totalMins / 60).toFixed(1);
    const estHrs    = estimatedHours ? parseFloat(estimatedHours) : null;
    const pct       = estHrs ? Math.min(100, Math.round((totalMins / 60 / estHrs) * 100)) : null;
    const barColor  = pct > 100 ? "bg-red-500" : pct > 80 ? "bg-amber-500" : "bg-emerald-500";

    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-5">
            <h3 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-3">Time Logged</h3>
            <div className="flex items-baseline gap-1.5 mb-2">
                <span className="text-2xl font-bold text-gray-900">{totalHrs}h</span>
                {estHrs && <span className="text-sm text-gray-400">/ {estHrs}h estimated</span>}
            </div>
            {pct !== null && (
                <div className="h-1.5 w-full rounded-full bg-gray-100 overflow-hidden mb-3">
                    <div className={`h-full rounded-full ${barColor}`} style={{ width: `${pct}%` }} />
                </div>
            )}
            {(timeLogs ?? []).length > 0 && (
                <div className="space-y-2 mt-3 border-t border-gray-100 pt-3">
                    {timeLogs.slice(0, 5).map(log => (
                        <div key={log.id} className="flex items-center justify-between text-xs">
                            <div className="flex items-center gap-1.5">
                                <User size={11} className="text-gray-400" />
                                <span className="font-medium text-gray-700">{log.user?.name}</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-gray-500">
                                    {log.duration_minutes ? `${(log.duration_minutes / 60).toFixed(1)}h` : (
                                        <span className="flex items-center gap-1 text-amber-600">
                                            <Clock size={10} className="animate-pulse" /> Running
                                        </span>
                                    )}
                                </span>
                                <span className="capitalize text-[10px] text-gray-400 border rounded px-1">{log.entry_type}</span>
                            </div>
                        </div>
                    ))}
                    {timeLogs.length > 5 && (
                        <p className="text-xs text-gray-400 text-center">+{timeLogs.length - 5} more entries</p>
                    )}
                </div>
            )}
        </div>
    );
}

// ── ActivityPanel ─────────────────────────────────────────────────────────────
function ActivityPanel({ logs }) {
    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-5">
            <h3 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-4">
                Activity {logs.length > 0 && <span className="normal-case font-normal text-gray-400">({logs.length})</span>}
            </h3>
            {logs.length === 0 ? (
                <p className="text-sm text-gray-400">No activity yet.</p>
            ) : (
                <ol className="space-y-3">
                    {logs.map(log => (
                        <li key={log.id} className="flex gap-3 text-sm">
                            <div className="mt-1.5 shrink-0">
                                <span className={`block h-2 w-2 rounded-full ${ACTION_COLORS[log.action] ?? "bg-gray-400"}`} />
                            </div>
                            <div>
                                <p className="text-gray-700">
                                    <span className="font-semibold">{log.user?.name ?? "System"}</span>
                                    {" "}
                                    <span className="text-gray-500">{log.action.replace(/_/g, " ")}</span>
                                    {log.action === "status_changed" && log.old_value && log.new_value && (
                                        <span className="text-gray-500">
                                            {" "}from <strong>{STATUS_LABELS[log.old_value.status] ?? log.old_value.status}</strong>
                                            {" "}to <strong>{STATUS_LABELS[log.new_value.status] ?? log.new_value.status}</strong>
                                        </span>
                                    )}
                                </p>
                                <p className="text-xs text-gray-400 mt-0.5">{new Date(log.created_at).toLocaleString()}</p>
                            </div>
                        </li>
                    ))}
                </ol>
            )}
        </div>
    );
}

// ── Main Show Page ────────────────────────────────────────────────────────────
export default function TaskShowPage({
                                         task,
                                         activityLogs,
                                         users,
                                         projects,
                                         tasks: allTasks,
                                         priorities,
                                         comments,
                                         currentUser,
                                         mentionableUsers = [],

                                     }) {
    const { openModal, closeModal } = useModal();
    const matrix = MATRIX_MAP[`${task.impact}-${task.effort}`];

    function handleDelete() {
        if (confirm(`Permanently delete "${task.title}"?`)) {
            router.delete(`/admin/tasks/${task.id}`);
        }
    }

    function openEditModal() {
        openModal(
            <EditTaskForm
                task={task}
                projects={projects}
                users={users}
                tasks={allTasks}
                priorities={priorities}
                onClose={closeModal}
            />,
            { size: "xl" }
        );
    }

    return (
        <AdminLayout>
            <Head title={task.title} />

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">

                {/* ── Header ── */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3 min-w-0">
                        <a
                            href="/admin/tasks"
                            className="mt-1 p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors shrink-0"
                        >
                            <ArrowLeft size={18} />
                        </a>
                        <div className="min-w-0">
                            <nav className="flex items-center gap-1 text-xs text-gray-400 mb-2">
                                <a href="/admin/tasks" className="hover:text-primary transition-colors">Tasks</a>
                                <ChevronRight size={12} />
                                <span className="text-gray-500">{task.project?.name}</span>
                                <ChevronRight size={12} />
                                <span className="text-gray-700 font-medium">#{task.id}</span>
                            </nav>

                            <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600 leading-tight">
                                {task.title}
                            </h1>

                            <div className="flex flex-wrap items-center gap-2 mt-2">
                                <StatusBadge status={task.status} size="lg" />

                                <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${PRIORITY_STYLES[task.priority] ?? ""}`}>
                                    <span className={`h-1.5 w-1.5 rounded-full ${PRIORITY_DOTS[task.priority] ?? "bg-gray-400"}`} />
                                    {task.priority?.charAt(0).toUpperCase() + task.priority?.slice(1)}
                                </span>

                                {matrix && (
                                    <span className={`text-xs rounded-md px-2 py-0.5 font-medium ${matrix.cls}`}>{matrix.label}</span>
                                )}

                                {task.is_blocked && task.dependency && (
                                    <span className="inline-flex items-center gap-1 rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-xs text-red-700 font-medium">
                                        <AlertCircle size={10} />
                                        Blocked by: {task.dependency.title}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        <button
                            onClick={openEditModal}
                            className="flex items-center gap-1.5 px-3 py-2 border border-gray-200 bg-white text-sm font-semibold text-gray-700 rounded-xl hover:bg-gray-50 transition-colors"
                        >
                            <Edit size={15} />
                            Edit
                        </button>
                        <button
                            onClick={handleDelete}
                            className="flex items-center gap-1.5 px-3 py-2 border border-red-200 bg-red-50 text-sm font-semibold text-red-600 rounded-xl hover:bg-red-100 transition-colors"
                        >
                            <Trash2 size={15} />
                            Delete
                        </button>
                    </div>
                </div>

                {/* ── 2-Column Body ── */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">

                    {/* ── Left: main content ── */}
                    <div className="lg:col-span-2 space-y-5">

                        {/* Description */}
                        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                            <h2 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-3">Description</h2>
                            {task.description ? (
                                <p className="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed">{task.description}</p>
                            ) : (
                                <p className="text-sm text-gray-400 italic">No description provided.</p>
                            )}
                        </div>

                        {/* Details grid */}
                        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                            <h2 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-4">Details</h2>
                            <dl className="grid grid-cols-2 gap-x-8 gap-y-4 sm:grid-cols-3">
                                {[
                                    { label: "Project",      value: task.project?.name },
                                    { label: "Department",   value: task.project?.department?.name },
                                    { label: "Created by",   value: task.creator?.name },
                                    { label: "Due Date",     value: task.due_date ? new Date(task.due_date).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : null },
                                    { label: "Est. Hours",   value: task.estimated_hours ? `${task.estimated_hours}h` : null },
                                    { label: "Completed At", value: task.completed_at ? new Date(task.completed_at).toLocaleString() : null },
                                ].map(({ label, value }) => (
                                    <div key={label}>
                                        <dt className="text-xs text-gray-400">{label}</dt>
                                        <dd className="mt-0.5 text-sm font-semibold text-gray-700">
                                            {value ?? <span className="font-normal text-gray-300">—</span>}
                                        </dd>
                                    </div>
                                ))}

                                {RECURRING_TASKS_ENABLED && task.is_recurring && (
                                    <>
                                        <div>
                                            <dt className="text-xs text-gray-400">Recurrence</dt>
                                            <dd className="mt-0.5 text-sm font-semibold text-gray-700 capitalize">{task.recurrence_rule}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-gray-400">Ends At</dt>
                                            <dd className="mt-0.5 text-sm font-semibold text-gray-700">
                                                {task.recurrence_ends_at ?? <span className="font-normal text-gray-400">Indefinite</span>}
                                            </dd>
                                        </div>
                                    </>
                                )}
                            </dl>
                        </div>

                        {/* Dependents */}
                        {task.dependents?.length > 0 && (
                            <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                                <h2 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-4">
                                    Blocking ({task.dependents.length})
                                </h2>
                                <ul className="space-y-2">
                                    {task.dependents.map(dep => (
                                        <li key={dep.id} className="flex items-center justify-between bg-gray-50 rounded-xl px-4 py-2.5">
                                            <a
                                                href={`/admin/tasks/${dep.id}`}
                                                className="text-sm font-semibold text-gray-700 hover:text-primary transition-colors"
                                            >
                                                #{dep.id} — {dep.title}
                                            </a>
                                            <div className="flex items-center gap-2">
                                                {dep.assignee && (
                                                    <span className="text-xs text-gray-400">{dep.assignee.name}</span>
                                                )}
                                                <StatusBadge status={dep.status} />
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <CommentThread
                            comments={comments ?? []}
                            taskId={task.id}
                            currentUser={currentUser}
                            mentionableUsers={mentionableUsers ?? []}
                        />

                        {/* Time Logs — mobile only */}
                        <div className="lg:hidden">
                            <TimePanel timeLogs={task.time_logs ?? []} estimatedHours={task.estimated_hours} />
                        </div>
                    </div>

                    {/* ── Right: sidebar ── */}
                    <div className="space-y-5">
                        <StatusPanel task={task} />
                        <AssignPanel task={task} users={users} />
                        <div className="hidden lg:block">
                            <TimePanel timeLogs={task.time_logs ?? []} estimatedHours={task.estimated_hours} />
                        </div>
                        <ActivityPanel logs={activityLogs ?? []} />
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
