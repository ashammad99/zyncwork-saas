import React from "react";
import EmployeeLayout from "../layout";
import { Head, Link } from "@inertiajs/react";
import { CheckCircle2, Clock, ListTodo, AlertTriangle, ArrowRight, PlayCircle } from "lucide-react";

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
 * @param {{ id: number, name: string, email: string, avatar: string|null, daily_capacity_hours: number }} props.user
 * @param {{ my_open_tasks: number, in_progress: number, in_review: number, completed_this_week: number, overdue: number, hours_this_week: number }} props.metrics
 * @param {Array<{ id: number, description: string, action: string, subject_type: string|null, created_at: string }>} props.recent_activity
 * @param {{ utilization_pct: number, capacity_hours: number, logged_hours: number }} props.stats
 */
export default function EmployeeDashboard({ user, metrics = {}, recent_activity = [], stats = {} }) {
    const firstName = user.name.split(' ')[0];

    return (
        <EmployeeLayout>
            <Head title="Employee Dashboard" />

            <div className="max-w-7xl mx-auto space-y-6 p-6">

                {/* Hero */}
                <div className="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl p-8 text-white shadow-sm">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 className="text-2xl md:text-3xl font-bold">
                                Welcome back, {firstName}
                            </h1>
                            <p className="mt-1 text-white/80 text-sm">
                                Here's what's on your plate today.
                            </p>
                        </div>
                        <div className="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3 backdrop-blur-sm border border-white/10">
                            <div className="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center overflow-hidden border-2 border-white">
                                {user.profile_image ? (
                                    <img
                                        src={`/user/profile-image/${user.profile_image}`}
                                        className="w-full h-full object-cover"
                                        alt=""
                                        onError={(e) => { e.target.style.display = "none"; }}
                                    />
                                ) : (
                                    <span className="text-lg font-bold">{user.name.charAt(0)}</span>
                                )}
                            </div>
                            <div>
                                <p className="font-semibold text-sm">{user.name}</p>
                                <p className="text-xs text-white/70">{user.email}</p>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Metric cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <MetricCard
                        icon={<ListTodo size={20} />}
                        label="Open Tasks"
                        value={metrics.my_open_tasks ?? 0}
                        color="blue"
                    />
                    <MetricCard
                        icon={<PlayCircle size={20} />}
                        label="In Progress"
                        value={metrics.in_progress ?? 0}
                        color="indigo"
                    />
                    <MetricCard
                        icon={<CheckCircle2 size={20} />}
                        label="Completed This Week"
                        value={metrics.completed_this_week ?? 0}
                        color="green"
                    />
                    <MetricCard
                        icon={<AlertTriangle size={20} />}
                        label="Overdue"
                        value={metrics.overdue ?? 0}
                        color="red"
                    />
                </div>

                <TaskStatusSummary summary={metrics.task_status_summary} taskListPath="/employee/tasks" />

                {/* Two-column: Weekly capacity + Recent activity */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Weekly capacity */}
                    <div className="lg:col-span-1 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                        <div className="flex items-center gap-2 mb-4">
                            <Clock size={18} className="text-gray-500" />
                            <h3 className="font-semibold text-gray-900">This Week</h3>
                        </div>

                        <div className="space-y-4">
                            <div>
                                <div className="flex justify-between text-sm mb-1">
                                    <span className="text-gray-500">Logged</span>
                                    <span className="font-medium">
                                        {(stats.logged_hours ?? 0).toFixed(1)}h / {stats.capacity_hours ?? 40}h
                                    </span>
                                </div>
                                <div className="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        className={`h-full transition-all ${utilizationBar(stats.utilization_pct ?? 0)}`}
                                        style={{ width: `${Math.min(stats.utilization_pct ?? 0, 100)}%` }}
                                    />
                                </div>
                                <p className="text-xs text-gray-500 mt-2">
                                    Utilization: <span className="font-medium">{(stats.utilization_pct ?? 0).toFixed(0)}%</span>
                                </p>
                            </div>

                            <Link
                                href="/timesheets"
                                className="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-700"
                            >
                                View timesheet <ArrowRight size={14} />
                            </Link>
                        </div>
                    </div>

                    {/* Recent activity */}
                    <div className="lg:col-span-2 bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="font-semibold text-gray-900">Recent Activity</h3>
                        </div>

                        {recent_activity.length > 0 ? (
                            <ul className="space-y-3">
                                {recent_activity.map((log) => (
                                    <li key={log.id} className="flex items-start gap-3 py-2 border-b border-gray-50 last:border-0">
                                        <div className="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                            <Clock size={14} />
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm text-gray-900">{log.description}</p>
                                            <p className="text-xs text-gray-400 mt-0.5">
                                                {new Date(log.created_at).toLocaleString()}
                                            </p>
                                        </div>
                                        {log.action && (
                                            <span className="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                                {log.action}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <div className="text-center py-8 text-sm text-gray-400">
                                No activity yet.
                            </div>
                        )}
                    </div>
                </div>

            </div>
        </EmployeeLayout>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

function MetricCard({ icon, label, value, color }) {
    const palette = {
        blue: 'bg-blue-50 text-blue-600 border-blue-100',
        indigo: 'bg-indigo-50 text-indigo-600 border-indigo-100',
        green: 'bg-green-50 text-green-600 border-green-100',
        red: 'bg-red-50 text-red-600 border-red-100',
    }[color] || 'bg-gray-50 text-gray-600 border-gray-100';

    return (
        <div className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
            <div className={`w-10 h-10 rounded-xl border flex items-center justify-center mb-3 ${palette}`}>
                {icon}
            </div>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            <p className="text-xs text-gray-500 font-medium mt-0.5">{label}</p>
        </div>
    );
}

function utilizationBar(pct) {
    if (pct < 60) return 'bg-gray-400';
    if (pct <= 100) return 'bg-green-500';
    if (pct <= 120) return 'bg-yellow-500';
    return 'bg-red-500';
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
