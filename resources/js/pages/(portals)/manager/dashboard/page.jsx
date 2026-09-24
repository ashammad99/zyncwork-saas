import React from "react";
import { Head, Link } from "@inertiajs/react";
import ManagerLayout from "../layout";
import {
    FolderKanban,
    ListTodo,
    Clock,
    Users,
    Activity,
    AlertTriangle,
    ArrowRight,
} from "lucide-react";

const CAPACITY_STYLES = {
    overloaded: { label: "Overloaded",  bar: "bg-red-500",     badge: "bg-red-50 text-red-700 border-red-200" },
    near_limit: { label: "Near Limit",  bar: "bg-amber-500",   badge: "bg-amber-50 text-amber-700 border-amber-200" },
    on_track:   { label: "On Track",    bar: "bg-emerald-500", badge: "bg-emerald-50 text-emerald-700 border-emerald-200" },
    under:      { label: "Under",       bar: "bg-gray-300",    badge: "bg-gray-50 text-gray-500 border-gray-200" },
};

// ─── Task status summary config (mirrors manager/projects/Show.jsx) ───────────

const STATUS_ORDER = ["backlog", "todo", "in_progress", "in_review", "completed"];

const STATUS_CONFIG = {
    backlog:     { label: "Backlog",      className: "bg-gray-100 text-gray-600" },
    todo:        { label: "To Do",        className: "bg-blue-100 text-blue-600" },
    in_progress: { label: "In Progress",  className: "bg-yellow-100 text-yellow-700" },
    in_review:   { label: "In Review",    className: "bg-purple-100 text-purple-700" },
    completed:   { label: "Completed",    className: "bg-green-100 text-green-700" },
};

/**
 * @param {object} props
 * @param {{ name: string }} props.user
 * @param {{ active_projects: number, open_tasks: number, logged_hours_week: number, team_members: number }} props.metrics
 * @param {Array<object>} props.recent_activity
 * @param {Array<{ id, name, profile_image, assigned_hours, capacity_hours, utilization, status }>} props.team_capacity
 */
export default function ManagerDashboard({
                                             user,
                                             metrics = {},
                                             recent_activity = [],
                                             team_capacity = [],
                                         }) {
    const firstName = user?.name?.split(" ")[0] ?? "";
    const warnings = team_capacity.filter(
        (m) => m.status === "overloaded" || m.status === "near_limit"
    );

    return (
        <ManagerLayout>
            <Head title="Manager Dashboard" />

            <div className="max-w-7xl mx-auto space-y-6 py-6">

                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">
                        Welcome back, {firstName}
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Your department at a glance.
                    </p>
                </div>

                {/* Overload alert banner (PRD: manager must re-assign or extend deadline) */}
                {warnings.length > 0 && (
                    <div className="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4">
                        <AlertTriangle size={18} className="text-red-600 shrink-0 mt-0.5" />
                        <div className="text-sm text-red-800">
                            <span className="font-semibold">
                                {warnings.length} team member{warnings.length !== 1 && "s"} at or over capacity today.
                            </span>{" "}
                            Consider re-assigning tasks or extending deadlines.
                        </div>
                    </div>
                )}

                {/* Metric cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <MetricCard
                        icon={<FolderKanban size={20} />}
                        label="Active Projects"
                        value={metrics.active_projects ?? 0}
                        color="text-blue-600 bg-blue-50 border-blue-100"
                    />
                    <MetricCard
                        icon={<ListTodo size={20} />}
                        label="Open Tasks"
                        value={metrics.open_tasks ?? 0}
                        color="text-indigo-600 bg-indigo-50 border-indigo-100"
                    />
                    <MetricCard
                        icon={<Clock size={20} />}
                        label="Logged This Week"
                        value={`${metrics.logged_hours_week ?? 0}h`}
                        color="text-emerald-600 bg-emerald-50 border-emerald-100"
                    />
                    <MetricCard
                        icon={<Users size={20} />}
                        label="Team Members"
                        value={metrics.team_members ?? 0}
                        color="text-purple-600 bg-purple-50 border-purple-100"
                    />
                </div>

                <TaskStatusSummary summary={metrics.task_status_summary} taskListPath="/manager/tasks" />

                {/* Two-column: capacity + activity */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {/* Team capacity */}
                    <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <div className="px-6 py-4 flex items-center justify-between border-b border-gray-100">
                            <div className="flex items-center gap-2">
                                <Users size={16} className="text-gray-400" />
                                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500">
                                    Team Capacity Today
                                </h3>
                            </div>
                            <Link
                                href="/manager/timesheets"
                                className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:text-primary/80 transition"
                            >
                                Timesheets <ArrowRight size={12} />
                            </Link>
                        </div>

                        {team_capacity.length === 0 ? (
                            <div className="px-6 py-10 text-center text-sm text-gray-400">
                                No active employees in your department yet.
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-50">
                                {team_capacity.map((member) => (
                                    <CapacityRow key={member.id} member={member} />
                                ))}
                            </ul>
                        )}
                    </div>

                    {/* Recent activity */}
                    <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <div className="px-6 py-4 flex items-center gap-2 border-b border-gray-100">
                            <Activity size={16} className="text-gray-400" />
                            <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500">
                                Department Activity
                            </h3>
                        </div>

                        {recent_activity.length === 0 ? (
                            <div className="px-6 py-10 text-center text-sm text-gray-400">
                                No recent activity in your department.
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-50 max-h-[480px] overflow-y-auto">
                                {recent_activity.map((log) => (
                                    <li key={log.id} className="px-6 py-3 flex items-start gap-3">
                                        <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0 overflow-hidden">
                                            {log.user?.profile_image ? (
                                                <img
                                                    src={`/user/profile-image/${log.user.profile_image}`}

                                                    className="w-full h-full object-cover"
                                                    alt=""
                                                />
                                            ) : (
                                                log.user?.name?.charAt(0) ?? "S"
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm text-gray-900">
                                                <span className="font-medium">{log.user?.name ?? "System"}</span>{" "}
                                                <span className="text-gray-500">{log.description ?? log.action}</span>
                                            </p>
                                            <p className="text-xs text-gray-400 mt-0.5">
                                                {new Date(log.created_at).toLocaleString(undefined, {
                                                    month: "short",
                                                    day: "numeric",
                                                    hour: "2-digit",
                                                    minute: "2-digit",
                                                })}
                                            </p>
                                        </div>
                                        {log.action && (
                                            <span className="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 shrink-0">
                                                {log.action}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </ManagerLayout>
    );
}

/* ─── Metric card ────────────────────────────────────────── */

function MetricCard({ icon, label, value, color }) {
    return (
        <div className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
            <div className={`w-10 h-10 rounded-xl border flex items-center justify-center mb-3 ${color}`}>
                {icon}
            </div>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            <p className="text-xs text-gray-500 font-medium mt-0.5">{label}</p>
        </div>
    );
}

/* ─── Task Status Summary ────────────────────────────────── */

function TaskStatusSummary({ summary = {}, taskListPath }) {
    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
            <h3 className="font-bold text-gray-900 text-lg mb-4">Task Status Summary</h3>
            <div className="flex flex-wrap gap-3">
                {STATUS_ORDER.map((s) => {
                    const cfg = STATUS_CONFIG[s];
                    const data = summary?.[s] ?? { count: 0, estimated_hours: 0, logged_minutes: 0 };
                    return (
                        <Link
                            key={s}
                            href={`${taskListPath}?status=${s}`}
                            className={`min-w-[140px] px-4 py-3 rounded-xl transition hover:opacity-80 ${cfg.className}`}
                        >
                            <p className="text-[10px] font-bold uppercase tracking-wide">{cfg.label}</p>
                            <p className="text-2xl font-bold mt-1">{data.count}</p>
                            <p className="text-[11px] mt-1 opacity-80">
                                Est: {data.estimated_hours}h · Logged: {formatMinutes(data.logged_minutes)}
                            </p>
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

function formatMinutes(min) {
    if (!min || min <= 0) return "0m";
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}

/* ─── Capacity row ───────────────────────────────────────── */

function CapacityRow({ member }) {
    const style = CAPACITY_STYLES[member.status] ?? CAPACITY_STYLES.under;
    const barWidth = Math.min(member.utilization, 100);

    return (
        <li className="px-6 py-3">
            <div className="flex items-center justify-between gap-3 mb-1.5">
                <div className="flex items-center gap-2.5 min-w-0">
                    <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0 overflow-hidden">
                        {member.profile_image ? (
                            <img
                                src={`/user/profile-image/${member.profile_image}`}                                className="w-full h-full object-cover"
                                alt=""
                            />
                        ) : (
                            member.name?.charAt(0)
                        )}
                    </div>
                    <span className="text-sm font-medium text-gray-900 truncate">
                        {member.name}
                    </span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <span className="text-xs text-gray-500">
                        {member.assigned_hours}h / {member.capacity_hours}h
                    </span>
                    <span className={`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${style.badge}`}>
                        {member.utilization}%
                    </span>
                </div>
            </div>
            <div className="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div
                    className={`h-full rounded-full transition-all duration-500 ${style.bar}`}
                    style={{ width: `${barWidth}%` }}
                />
            </div>
        </li>
    );
}
