// resources/js/pages/(portals)/manager/tasks/show.jsx

import React, { useState } from "react";
import { RECURRING_TASKS_ENABLED } from "@/Utils/features";
import ManagerLayout from "@/pages/(portals)/manager/layout";
import { Head, router, useForm, usePage } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import CommentThread from "@/Components/comments/CommentThread";
import TimeLogPanel from "@/pages/(portals)/admin/tasks/partials/TimeLogPanel";
import EditTaskForm from "./partials/EditTaskForm";
import {
    ArrowLeft,
    Edit,
    Trash2,
    ChevronRight,
    AlertCircle,
    RefreshCw,
    CheckCircle2,
    RotateCcw,
} from "lucide-react";

// ── Badge / config helpers ────────────────────────────────────────────────────

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
        router.patch(`/manager/tasks/${task.id}/status`, { status: newStatus }, {
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
        patch(`/manager/tasks/${task.id}/assign`);
    }

    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-5">
            <h3 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-4">Assignee</h3>
            <form onSubmit={submit} className="space-y-3">
                <select
                    value={data.assignee_id ?? ""}
                    onChange={e => setData("assignee_id", e.target.value || null)}
                    className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                >
                    <option value="">Unassigned</option>
                    {(users ?? []).map(u => (
                        <option key={u.id} value={u.id}>{u.name}</option>
                    ))}
                </select>
                {errors.assignee_id && <p className="text-xs text-red-600">{errors.assignee_id}</p>}
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full flex items-center justify-center gap-2 py-2 bg-gray-900 text-white text-sm font-semibold rounded-xl hover:bg-gray-800 disabled:opacity-40 transition"
                >
                    {processing && <RefreshCw size={14} className="animate-spin" />}
                    Update Assignee
                </button>
            </form>
        </div>
    );
}

// ── ActivityPanel ─────────────────────────────────────────────────────────────

function ActivityPanel({ logs }) {
    if (!logs.length) return null;

    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-5">
            <h3 className="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-4">Activity</h3>
            <div className="space-y-3">
                {logs.map(log => (
                    <div key={log.id} className="flex items-start gap-3">
                        <span className={`mt-1 h-2 w-2 rounded-full shrink-0 ${ACTION_COLORS[log.action] ?? "bg-gray-400"}`} />
                        <div className="min-w-0">
                            <p className="text-xs text-gray-700">
                                <span className="font-semibold">{log.user?.name ?? "System"}</span>
                                {" "}{log.action?.replace(/_/g, " ")}
                            </p>
                            <p className="text-[11px] text-gray-400 mt-0.5">
                                {new Date(log.created_at).toLocaleString("en-GB", {
                                    day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit",
                                })}
                            </p>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

// ── Main Page ─────────────────────────────────────────────────────────────────

export default function TaskShow({ task, comments, users, activityLogs, mentionableUsers = [] }) {    const { auth } = usePage().props;
    const currentUser = auth.user;
    const { openModal, closeModal } = useModal();

    const matrix = task.impact && task.effort
        ? MATRIX_MAP[`${task.impact}-${task.effort}`]
        : null;

    function openEditModal() {
        openModal(
            <EditTaskForm
                task={task}
                projects={[task.project].filter(Boolean)}
                users={users}
                tasks={[]}
                priorities={["low", "medium", "high", "urgent"]}
                onClose={closeModal}
            />,
            { size: "xl" }
        );
    }

    function handleDelete() {
        if (confirm(`Delete "${task.title}"? This cannot be undone.`)) {
            router.delete(`/manager/tasks/${task.id}`);
        }
    }

    return (
        <ManagerLayout>
            <Head title={task.title} />

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">

                {/* ── Breadcrumb ── */}
                <div className="flex items-center gap-2">
                    <a
                        href="/manager/tasks"
                        className="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors"
                    >
                        <ArrowLeft size={15} />
                        Tasks
                    </a>
                    <span className="text-gray-300">/</span>
                    <span className="text-sm text-gray-700 font-medium truncate max-w-xs">{task.title}</span>
                </div>

                {/* ── Header ── */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div className="flex-1 min-w-0">
                        <h1 className="text-2xl font-bold text-gray-900 leading-snug truncate">
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

                            {RECURRING_TASKS_ENABLED && task.is_recurring && (
                                <span className="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs text-indigo-600 font-medium">
                                    ↻ Recurring
                                </span>
                            )}
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
                        {task.status !== "completed" && (
                            <button
                                onClick={handleDelete}
                                className="flex items-center gap-1.5 px-3 py-2 border border-red-200 bg-red-50 text-sm font-semibold text-red-600 rounded-xl hover:bg-red-100 transition-colors"
                            >
                                <Trash2 size={15} />
                                Delete
                            </button>
                        )}
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
                                    { label: "Created by",   value: task.creator?.name },
                                    { label: "Due Date",     value: task.due_date ? new Date(task.due_date).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" }) : null },
                                    { label: "Est. Hours",   value: task.estimated_hours ? `${task.estimated_hours}h` : null },
                                    { label: "Impact",       value: task.impact ? task.impact.charAt(0).toUpperCase() + task.impact.slice(1) : null },
                                    { label: "Effort",       value: task.effort ? task.effort.charAt(0).toUpperCase() + task.effort.slice(1) : null },
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
                                                href={`/manager/tasks/${dep.id}`}
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

                        {/* Comments */}
                        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                            <CommentThread
                                comments={comments ?? []}
                                taskId={task.id}
                                currentUser={currentUser}
                                mentionableUsers={mentionableUsers}
                            />
                        </div>

                        {/* Time Logs — mobile only */}
                        <div className="lg:hidden">
                            <TimeLogPanel
                                task={task}
                                timeLogs={task.time_logs ?? []}
                                currentUser={currentUser}
                                canApprove={true}
                            />
                        </div>
                    </div>

                    {/* ── Right: sidebar ── */}
                    <div className="space-y-5">
                        <StatusPanel task={task} />
                        <AssignPanel task={task} users={users} />
                        <div className="hidden lg:block">
                            <TimeLogPanel
                                task={task}
                                timeLogs={task.time_logs ?? []}
                                currentUser={currentUser}
                                canApprove={true}
                            />
                        </div>
                        <ActivityPanel logs={activityLogs ?? []} />
                    </div>
                </div>
            </div>
        </ManagerLayout>
    );
}
