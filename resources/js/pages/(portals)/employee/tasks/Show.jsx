import React from "react";
import { Head, Link, router, useForm } from "@inertiajs/react";
import EmployeeLayout from "../layout";
import EmployeeTaskTimerPanel from "../timesheets/EmployeeTaskTimerPanel";
import CommentsSection from "./CommentsSection";

import {
    ArrowLeft,
    Calendar,
    Clock,
    User as UserIcon,
    Building2,
    AlertTriangle,
    CheckCircle2,
    ChevronRight,
    Flag,
    FolderKanban,
    Info,
} from "lucide-react";

/* ─── Style maps ─────────────────────────────────────────── */

const STATUS_STYLES = {
    backlog:     { label: "Backlog",     dot: "bg-slate-400",  color: "text-slate-700" },
    todo:        { label: "To Do",       dot: "bg-blue-500",   color: "text-blue-700" },
    in_progress: { label: "In Progress", dot: "bg-amber-500",  color: "text-amber-700" },
    in_review:   { label: "In Review",   dot: "bg-violet-500", color: "text-violet-700" },
    completed:   { label: "Completed",   dot: "bg-emerald-500", color: "text-emerald-700" },
};

const STATUS_ORDER = ["backlog", "todo", "in_progress", "in_review", "completed"];

const PRIORITY_STYLES = {
    low:    { label: "Low",    color: "bg-slate-100 text-slate-600", dot: "bg-slate-400" },
    medium: { label: "Medium", color: "bg-sky-100 text-sky-700",     dot: "bg-sky-500" },
    high:   { label: "High",   color: "bg-orange-100 text-orange-700", dot: "bg-orange-500" },
    urgent: { label: "Urgent", color: "bg-red-100 text-red-700",     dot: "bg-red-500" },
};

/* ─── Page ───────────────────────────────────────────────── */

/**
 * @param {object} props
 * @param {object} props.task
 * @param {{ id: number, name: string, role: string }} props.currentUser
 * @param {{ to_todo: boolean, to_in_progress: boolean, to_in_review: boolean }} props.canTransition
 */
export default function EmployeeTaskShow({ task, currentUser, canTransition = {}, comments = [], mentionableUsers = [] }) {
    console.log('DEBUG canTransition:', canTransition, 'task.status:', task.status);

    const isCompleted = task.status === "completed";
    const isBlocked =
        task.dependency && task.dependency.status !== "completed";

    // Which button to show
    const nextTransition = getNextTransition(canTransition);

    return (
        <EmployeeLayout>
            <Head title={task.title} />

            <div className="max-w-7xl mx-auto py-6 space-y-6">

                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-sm text-gray-500">
                    <Link
                        href="/employee/tasks"
                        className="inline-flex items-center gap-1 hover:text-gray-700 transition"
                    >
                        <ArrowLeft size={14} />
                        My Tasks
                    </Link>
                    <ChevronRight size={14} className="text-gray-300" />
                    <span className="text-gray-400 truncate">
                        {task.project?.name}
                    </span>
                    <ChevronRight size={14} className="text-gray-300" />
                    <span className="font-medium text-gray-700 truncate">
                        #{task.id}
                    </span>
                </div>

                {/* Title */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">{task.title}</h1>
                    <div className="flex items-center gap-2 mt-2">
                        <StatusBadge status={task.status} />
                        <PriorityBadge priority={task.priority} />
                        {isBlocked && <BlockedBadge dependency={task.dependency} />}
                    </div>
                </div>

                {/* Two-column: main + sidebar */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Main column */}
                    <div className="lg:col-span-2 space-y-6">

                        {/* Description */}
                        <Card>
                            <CardHeader icon={<Info size={16} />} title="Description" />
                            <div className="p-6 pt-0 text-sm text-gray-700 whitespace-pre-wrap">
                                {task.description || (
                                    <span className="italic text-gray-400">
                                        No description provided.
                                    </span>
                                )}
                            </div>
                        </Card>

                        {/* Details grid */}
                        <Card>
                            <CardHeader icon={<Info size={16} />} title="Details" />
                            <div className="p-6 pt-0 grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                                <DetailItem
                                    label="Project"
                                    icon={<FolderKanban size={14} />}
                                    value={task.project?.name}
                                />
                                <DetailItem
                                    label="Department"
                                    icon={<Building2 size={14} />}
                                    value={task.project?.department?.name}
                                />
                                <DetailItem
                                    label="Created By"
                                    icon={<UserIcon size={14} />}
                                    value={task.creator?.name}
                                />
                                <DetailItem
                                    label="Due Date"
                                    icon={<Calendar size={14} />}
                                    value={task.due_date ? formatDate(task.due_date) : "—"}
                                    highlight={
                                        task.due_date &&
                                        new Date(task.due_date) < new Date() &&
                                        !isCompleted
                                            ? "text-red-600 font-semibold"
                                            : ""
                                    }
                                />
                                <DetailItem
                                    label="Estimated Hours"
                                    icon={<Clock size={14} />}
                                    value={task.estimated_hours ? `${task.estimated_hours}h` : "—"}
                                />
                                <DetailItem
                                    label="Completed At"
                                    icon={<CheckCircle2 size={14} />}
                                    value={task.completed_at ? formatDate(task.completed_at) : "—"}
                                />
                            </div>
                        </Card>

                        {/* Impact / Effort */}
                        <Card>
                            <CardHeader icon={<Flag size={16} />} title="Impact & Effort" />
                            <div className="p-6 pt-0 grid grid-cols-2 gap-4">
                                <ImpactEffortBadge label="Impact" value={task.impact} />
                                <ImpactEffortBadge label="Effort" value={task.effort} />
                            </div>
                        </Card>

                        {/* Dependents (tasks that depend on THIS one) */}
                        {task.dependents && task.dependents.length > 0 && (
                            <Card>
                                <CardHeader
                                    icon={<AlertTriangle size={16} />}
                                    title={`Blocking (${task.dependents.length})`}
                                />
                                <ul className="divide-y divide-gray-100">
                                    {task.dependents.map((dep) => (
                                        <li key={dep.id} className="px-6 py-3 flex items-center justify-between text-sm">
                                            <div>
                                                <div className="font-medium text-gray-900">
                                                    #{dep.id} — {dep.title}
                                                </div>
                                                {dep.assignee && (
                                                    <div className="text-xs text-gray-500 mt-0.5">
                                                        Assigned to {dep.assignee.name}
                                                    </div>
                                                )}
                                            </div>
                                            <StatusBadge status={dep.status} />
                                        </li>
                                    ))}
                                </ul>
                            </Card>
                        )}
                        <CommentsSection
                            taskId={task.id}
                            comments={comments}
                            currentUser={currentUser}
                            mentionableUsers={mentionableUsers}
                        />
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">

                        {/* Status pipeline + action button */}
                        <Card>
                            <CardHeader title="Status Pipeline" />
                            <div className="p-6 pt-0">
                                <StatusPipeline currentStatus={task.status} />

                                {/* Action button */}
                                {nextTransition && !isBlocked && (
                                    <TransitionButton
                                        taskId={task.id}
                                        newStatus={nextTransition.status}
                                        label={nextTransition.label}
                                    />
                                )}

                                {isBlocked && (
                                    <div className="mt-4 p-3 rounded-lg bg-orange-50 border border-orange-200 text-xs text-orange-800">
                                        <AlertTriangle size={14} className="inline mr-1" />
                                        This task is blocked. Complete{" "}
                                        <span className="font-semibold">"{task.dependency.title}"</span> first.
                                    </div>
                                )}

                                {isCompleted && (
                                    <div className="mt-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800">
                                        <CheckCircle2 size={14} className="inline mr-1" />
                                        Task completed. Ask your manager to reopen if changes are needed.
                                    </div>
                                )}
                            </div>
                        </Card>

                        {/* Assignee */}
                        <Card>
                            <CardHeader icon={<UserIcon size={16} />} title="Assignee" />
                            <div className="p-6 pt-0">
                                <AssigneeCard user={task.assignee} />
                            </div>
                        </Card>

                        {/* Time logs preview */}
                        {/* Time tracking panel */}
                        <EmployeeTaskTimerPanel
                            task={task}
                            timeLogs={task.time_logs ?? []}
                            currentUser={currentUser}
                            estimatedHours={task.estimated_hours}
                        />
                    </div>
                </div>
            </div>
        </EmployeeLayout>
    );
}

/* ─── Sub-components ────────────────────────────────────── */

function Card({ children }) {
    return (
        <div className="bg-white border border-gray-200 rounded-2xl shadow-sm">
            {children}
        </div>
    );
}

function CardHeader({ icon, title }) {
    return (
        <div className="px-6 py-4 flex items-center gap-2 border-b border-gray-100">
            {icon && <span className="text-gray-400">{icon}</span>}
            <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500">
                {title}
            </h3>
        </div>
    );
}

function DetailItem({ label, icon, value, highlight = "" }) {
    return (
        <div>
            <div className="text-xs text-gray-500 flex items-center gap-1 mb-1">
                {icon}
                {label}
            </div>
            <div className={`text-sm text-gray-900 ${highlight}`}>
                {value || <span className="text-gray-400">—</span>}
            </div>
        </div>
    );
}

function StatusBadge({ status }) {
    const s = STATUS_STYLES[status] ?? {};
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-2.5 py-0.5 text-xs font-medium ${s.color}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${s.dot}`} />
            {s.label ?? status}
        </span>
    );
}

function PriorityBadge({ priority }) {
    const p = PRIORITY_STYLES[priority] ?? {};
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium ${p.color}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${p.dot}`} />
            {p.label ?? priority}
        </span>
    );
}

function BlockedBadge({ dependency }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium bg-orange-50 text-orange-700 border border-orange-200">
            <AlertTriangle size={12} />
            Blocked by "{dependency.title}"
        </span>
    );
}

function ImpactEffortBadge({ label, value }) {
    const isHigh = value === "high";
    return (
        <div className={`p-3 rounded-lg border ${isHigh ? "border-orange-200 bg-orange-50" : "border-slate-200 bg-slate-50"}`}>
            <div className="text-xs text-gray-500 uppercase tracking-wider">{label}</div>
            <div className={`text-lg font-bold mt-1 ${isHigh ? "text-orange-700" : "text-slate-700"}`}>
                {value?.charAt(0).toUpperCase() + value?.slice(1)}
            </div>
        </div>
    );
}

function StatusPipeline({ currentStatus }) {
    const currentIdx = STATUS_ORDER.indexOf(currentStatus);

    return (
        <ol className="space-y-3">
            {STATUS_ORDER.map((status, idx) => {
                const s = STATUS_STYLES[status];
                const isPast = idx < currentIdx;
                const isCurrent = idx === currentIdx;
                const isFuture = idx > currentIdx;

                return (
                    <li key={status} className="flex items-center gap-3">
                        <div className={`
                            w-5 h-5 rounded-full flex items-center justify-center shrink-0
                            ${isPast    ? "bg-emerald-500 text-white" : ""}
                            ${isCurrent ? `${s.dot} ring-4 ring-white shadow-md` : ""}
                            ${isFuture  ? "bg-gray-200"  : ""}
                        `}>
                            {isPast && <CheckCircle2 size={12} />}
                        </div>
                        <span className={`text-sm font-medium ${isCurrent ? s.color : isPast ? "text-gray-700" : "text-gray-400"}`}>
                            {s.label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
function TransitionButton({ taskId, newStatus, label }) {
    const [processing, setProcessing] = React.useState(false);
    const [error, setError] = React.useState(null);

    const submit = () => {
        setError(null);
        setProcessing(true);
        router.patch(
            `/employee/tasks/${taskId}/status`,
            { status: newStatus },
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.status),
                onFinish: () => setProcessing(false),
            }
        );
    };

    return (
        <>
            <button
                onClick={submit}
                disabled={processing}
                className="mt-6 w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-semibold text-sm rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
            >
                {processing ? "Updating…" : label}
                {!processing && <ChevronRight size={16} />}
            </button>
            {error && (
                <p className="mt-2 text-xs text-red-600">{error}</p>
            )}
        </>
    );
}
function AssigneeCard({ user }) {
    if (!user) return <div className="text-sm text-gray-400">Unassigned</div>;

    return (
        <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm overflow-hidden">
                {user.profile_image ? (
                    <img
                        src={`/user/profile-image/${user.profile_image}`}

                        alt={user.name}
                        className="w-full h-full object-cover"
                    />
                ) : (
                    user.name?.charAt(0)
                )}
            </div>
            <div className="min-w-0">
                <div className="font-semibold text-sm text-gray-900 truncate">
                    {user.name}
                </div>
                <div className="text-xs text-gray-500 truncate">
                    {user.email}
                </div>
            </div>
        </div>
    );
}

function TimeLogsPreview({ logs }) {
    if (logs.length === 0) {
        return (
            <div className="text-sm text-gray-400 text-center py-4">
                No time logged yet.
            </div>
        );
    }

    const totalMinutes = logs
        .filter((l) => l.duration_minutes !== null)
        .reduce((sum, l) => sum + Number(l.duration_minutes || 0), 0);

    return (
        <div className="space-y-3">
            <div className="text-sm">
                <span className="text-gray-500">Total logged:</span>{" "}
                <span className="font-semibold text-gray-900">
                    {(totalMinutes / 60).toFixed(1)}h
                </span>
            </div>
            <ul className="space-y-2 max-h-48 overflow-y-auto">
                {logs.slice(0, 5).map((log) => (
                    <li key={log.id} className="flex items-center justify-between text-xs">
                        <div className="min-w-0">
                            <div className="font-medium text-gray-700 truncate">
                                {log.user?.name ?? "—"}
                            </div>
                            <div className="text-gray-400">
                                {formatDate(log.started_at)}
                            </div>
                        </div>
                        <span className="text-gray-900 font-semibold shrink-0 ml-2">
                            {log.duration_minutes !== null
                                ? `${(log.duration_minutes / 60).toFixed(1)}h`
                                : "…"}
                        </span>
                    </li>
                ))}
            </ul>
            {logs.length > 5 && (
                <div className="text-xs text-gray-400 text-center">
                    + {logs.length - 5} more
                </div>
            )}
        </div>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

function getNextTransition(canTransition) {
    if (canTransition.to_todo) {
        return { status: "todo", label: "Move to To Do" };
    }
    if (canTransition.to_in_progress) {
        return { status: "in_progress", label: "Start Working" };
    }
    if (canTransition.to_in_review) {
        return { status: "in_review", label: "Submit for Review" };
    }
    return null;
}

function formatDate(iso) {
    return new Date(iso).toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
}
