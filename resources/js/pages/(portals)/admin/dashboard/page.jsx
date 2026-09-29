import React from "react";
import AdminLayout from "../layout";
import { Head, Link } from "@inertiajs/react";
import { Users, UsersRound, UserMinus, Activity, Clock } from "lucide-react";

// ─── Task status summary config (mirrors manager/projects/Show.jsx) ───────────

const STATUS_ORDER = ["backlog", "todo", "in_progress", "in_review", "completed"];

const STATUS_CONFIG = {
    backlog:     { label: "Backlog",      className: "bg-gray-100 text-gray-600" },
    todo:        { label: "To Do",        className: "bg-blue-100 text-blue-600" },
    in_progress: { label: "In Progress",  className: "bg-yellow-100 text-yellow-700" },
    in_review:   { label: "In Review",    className: "bg-purple-100 text-purple-700" },
    completed:   { label: "Completed",    className: "bg-green-100 text-green-700" },
};

export default function DashboardPage({ metrics, recent_activity }) {
    return (
        <AdminLayout>
            <Head title="Admin" />
            <div className="max-w-7xl mx-auto space-y-8 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                        Admin Dashboard
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        System overview, user metrics, and real-time activity tracking.
                    </p>
                </div>

                {/* Metrics Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <MetricCard
                        title="Total Users"
                        value={metrics.total_users}
                        icon={<Users size={24} className="text-blue-600" />}
                        bg="bg-blue-50"
                        border="border-blue-100"
                    />
                    <MetricCard
                        title="Active Users"
                        value={metrics.active_users}
                        icon={<UsersRound size={24} className="text-green-600" />}
                        bg="bg-green-50"
                        border="border-green-100"
                    />
                    <MetricCard
                        title="Suspended Users"
                        value={metrics.suspended_users}
                        icon={<UserMinus size={24} className="text-red-600" />}
                        bg="bg-red-50"
                        border="border-red-100"
                    />
                    <MetricCard
                        title="Total Activities"
                        value={metrics.total_activities}
                        icon={<Activity size={24} className="text-purple-600" />}
                        bg="bg-purple-50"
                        border="border-purple-100"
                    />
                </div>

                <TaskStatusSummary summary={metrics.task_status_summary} taskListPath="/admin/tasks" />

                {/* Activity Feed and Secondary Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-4">
                        <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl p-6">
                            <div className="flex items-center justify-between mb-6">
                                <div>
                                    <h3 className="font-bold text-gray-900 text-lg">Recent System Activity</h3>
                                    <p className="text-gray-500 text-xs mt-1">Latest actions performed across the platform</p>
                                </div>
                                <Activity className="text-gray-300" size={24} />
                            </div>

                            {recent_activity.length > 0 ? (
                                <div className="space-y-6">
                                    {recent_activity.map((log) => (
                                        <div key={log.id} className="flex gap-4 relative">
                                            {/* Timeline Line */}
                                            <div className="absolute top-8 bottom-[-24px] left-5 w-px bg-gray-100 last:hidden"></div>

                                            <div className="w-10 h-10 rounded-full bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0 z-10 overflow-hidden">
                                                {!log.platform_actor && log.user?.profile_image ? (
                                                    <img     src={`/admin/profile-image/${log.user.profile_image}`}
                                                             className="w-full h-full object-cover" />
                                                ) : (
                                                    <span className="text-sm font-bold text-primary">
                                                        {(log.platform_actor || log.user?.name)?.charAt(0) || "S"}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="flex-1 bg-gray-50 rounded-xl p-4 border border-gray-100/50">
                                                <div className="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p className="font-semibold text-gray-900">
                                                            {log.platform_actor || log.user?.name || "System"} <span className="text-gray-400 font-normal">performed an action</span>
                                                        </p>
                                                        <p className="text-gray-600 mt-1">{log.description}</p>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 text-xs text-gray-400 whitespace-nowrap shrink-0">
                                                        <Clock size={12} />
                                                        {new Date(log.created_at).toLocaleDateString()}
                                                    </div>
                                                </div>
                                                <div className="mt-3 flex gap-2">
                                                    <span className="px-2.5 py-1 bg-white border border-gray-200 text-gray-600 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                                        {log.subject_type || 'SYSTEM'}
                                                    </span>
                                                    <span className="px-2.5 py-1 bg-primary/5 border border-primary/10 text-primary rounded-full text-[10px] font-bold uppercase tracking-wider">
                                                        {log.action}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-12 text-gray-500">
                                    No recent activities recorded.
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="space-y-6">
                        {/* Quick Links / Summary widget */}
                        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                            <h3 className="font-bold text-gray-900 mb-4">Quick Actions</h3>
                            <div className="space-y-2">
                                <Link href="/admin/users" className="block p-3 rounded-xl hover:bg-gray-50 border border-transparent hover:border-gray-100 transition">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                                <Users size={16} />
                                            </div>
                                            <span className="font-semibold text-sm text-gray-700">Manage Users</span>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

function MetricCard({ title, value, icon, bg, border }) {
    return (
        <div className={`p-6 rounded-2xl border ${border} ${bg} transition-all hover:shadow-md`}>
            <div className="flex justify-between items-start mb-4">
                <div className={`p-3 rounded-xl bg-white/60 shadow-sm`}>
                    {icon}
                </div>
            </div>
            <div>
                <h3 className="text-3xl font-bold text-gray-900 mb-1">{value}</h3>
                <p className="text-sm font-medium text-gray-600">{title}</p>
            </div>
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
