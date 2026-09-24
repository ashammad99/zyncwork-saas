import React, { useState } from "react";
import ManagerLayout from "@/pages/(portals)/manager/layout";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, Link, router, usePage, useForm } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import Pagination from "@/Components/ui/Pagination";
import {
    ArrowLeft, Edit, Archive, Plus, ChevronRight, ChevronDown,
    Clock, User, Calendar, RefreshCw, AlertTriangle, Search,
    CalendarDays, ListTodo, TrendingDown, X,
} from "lucide-react";

// ─── Config ───────────────────────────────────────────────────────────────────

const STATUS_ORDER = ["backlog", "todo", "in_progress", "in_review", "completed"];

const STATUS_CONFIG = {
    backlog:     { label: "Backlog",      className: "bg-gray-100 text-gray-600" },
    todo:        { label: "To Do",        className: "bg-blue-100 text-blue-600" },
    in_progress: { label: "In Progress",  className: "bg-yellow-100 text-yellow-700" },
    in_review:   { label: "In Review",    className: "bg-purple-100 text-purple-700" },
    completed:   { label: "Completed",    className: "bg-green-100 text-green-700" },
};

const PRIORITY_CONFIG = {
    low:    { label: "Low",    className: "bg-gray-100 text-gray-500" },
    medium: { label: "Medium", className: "bg-blue-100 text-blue-600" },
    high:   { label: "High",   className: "bg-orange-100 text-orange-600" },
    urgent: { label: "Urgent", className: "bg-red-100 text-red-600" },
};

const PROJECT_STATUS = {
    active:   { label: "Active",   className: "bg-green-100 text-green-700" },
    on_hold:  { label: "On Hold",  className: "bg-yellow-100 text-yellow-700" },
    archived: { label: "Archived", className: "bg-gray-100 text-gray-600" },
};

// ─── Timeline config ──────────────────────────────────────────────────────────
// Buckets are assigned server-side; this only controls presentation order/tone.

const TIMELINE_BUCKETS = [
    { key: "overdue",     label: "Overdue",     tone: "danger", openByDefault: true },
    { key: "today",       label: "Today",       tone: "today",  openByDefault: true },
    { key: "this_week",   label: "This Week",   tone: "normal", openByDefault: true },
    { key: "next_week",   label: "Next Week",   tone: "normal", openByDefault: true },
    { key: "later",       label: "Later",       tone: "normal", openByDefault: true },
    { key: "no_due_date", label: "No Due Date", tone: "muted",  openByDefault: false },
    { key: "completed",   label: "Completed",   tone: "muted",  openByDefault: false },
];

const BUCKET_TONE = {
    danger: { chip: "bg-red-100 text-red-700",      heading: "text-red-700",   row: "border-l-4 border-red-400" },
    today:  { chip: "bg-primary/10 text-primary",   heading: "text-primary",   row: "border-l-4 border-primary/50" },
    normal: { chip: "bg-gray-100 text-gray-600",    heading: "text-gray-700",  row: "" },
    muted:  { chip: "bg-gray-100 text-gray-500",    heading: "text-gray-500",  row: "" },
};

const FILTER_LABELS = {
    search:      "Search",
    status:      "Status",
    priority:    "Priority",
    assignee_id: "Assignee",
};

/** Parses a date-only value as local midnight so the displayed day never shifts. */
function parseTaskDate(value) {
    if (!value) return null;
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatTaskDate(value) {
    const date = parseTaskDate(value);
    if (!date) return null;
    return date.toLocaleDateString("en-GB", { weekday: "short", day: "numeric", month: "short" });
}

/** Human hint used as a native tooltip alongside — never instead of — the real date. */
function relativeDateHint(value) {
    const date = parseTaskDate(value);
    if (!date) return "";
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const days = Math.round((date - today) / 86400000);
    if (days === 0) return "Due today";
    if (days === 1) return "Due tomorrow";
    if (days === -1) return "1 day overdue";
    return days > 0 ? `Due in ${days} days` : `${Math.abs(days)} days overdue`;
}

// ─── Small reusable components ────────────────────────────────────────────────

function Badge({ config }) {
    return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${config.className}`}>
            {config.label}
        </span>
    );
}
function Avatar({ user, size = 28 }) {
    return (
        <div
            title={user?.name}
            style={{ width: size, height: size }}
            className="rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold overflow-hidden shrink-0"
        >
            {user?.profile_image
                ? <img
                    src={`/user/profile-image/${user.profile_image}`}
                    className="w-full h-full object-cover"
                    alt=""
                    onError={(e) => { e.target.style.display = "none"; }}
                />
                : (user?.name?.charAt(0)?.toUpperCase() ?? "?")}
        </div>
    );
}

// ─── Task Row ─────────────────────────────────────────────────────────────────

function TaskRow({ task }) {
    const sc = STATUS_CONFIG[task.status]   ?? STATUS_CONFIG.backlog;
    const pc = PRIORITY_CONFIG[task.priority] ?? PRIORITY_CONFIG.medium;

    return (
        <tr className="hover:bg-gray-50/50 transition-colors">
            <td className="px-6 py-3">
                <p className="font-medium text-gray-900 text-sm">{task.title}</p>
                {task.description && (
                    <p className="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{task.description}</p>
                )}
            </td>

            <td className="px-6 py-3">
                <Badge config={sc} />
            </td>

            <td className="px-6 py-3">
                <Badge config={pc} />
            </td>

            <td className="px-6 py-3">
                {task.assignee ? (
                    <div className="flex items-center gap-2">
                        <Avatar user={task.assignee} size={24} />
                        <span className="text-xs text-gray-600">{task.assignee.name}</span>
                    </div>
                ) : (
                    <span className="text-xs text-gray-400 italic">Unassigned</span>
                )}
            </td>

            <td className="px-6 py-3 text-xs text-gray-500">
                {task.due_date
                    ? new Date(task.due_date).toLocaleDateString("en-GB", {
                        day: "numeric", month: "short", year: "numeric",
                    })
                    : <span className="text-gray-300">—</span>
                }
            </td>

            <td className="px-6 py-3 text-xs text-gray-500">
                {task.estimated_hours
                    ? `${task.estimated_hours}h`
                    : <span className="text-gray-300">—</span>
                }
            </td>
        </tr>
    );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function ShowProject({ project, tasks, timeline = null, tab = "tasks", statusCounts = {}, totalMinutes = 0, filters = {}, employees = [], role = "manager" }) {
    const { openModal, closeModal } = useModal();

    const isManager = role === "manager";
    const Layout = isManager ? ManagerLayout : AdminLayout;
    const basePath = isManager ? "/manager/projects" : "/admin/projects";
    const taskBasePath = isManager ? "/manager/tasks" : "/admin/tasks";
    const activeTab = tab === "timeline" ? "timeline" : "tasks";

    const ps = PROJECT_STATUS[project.status] ?? PROJECT_STATUS.active;

    const [search, setSearch] = useState(filters?.search || "");
    const [statusFilter, setStatusFilter] = useState(filters?.status || "");

    // Whole-project counts (independent of the current page/filter of `tasks`)
    const totalTasks     = STATUS_ORDER.reduce((sum, s) => sum + (statusCounts[s] ?? 0), 0);
    const completedTasks = statusCounts.completed ?? 0;
    const completionPct  = totalTasks > 0 ? Math.round((completedTasks / totalTasks) * 100) : 0;

    const handleSearch = (e) => {
        e.preventDefault();
        router.get(`${basePath}/${project.id}`, { search, status: statusFilter }, { preserveState: true, replace: true });
    };

    const handleStatusFilter = (val) => {
        const next = statusFilter === val ? "" : val;
        setStatusFilter(next);
        router.get(`${basePath}/${project.id}`, { search, status: next }, { preserveState: true, replace: true });
    };

    const openEditModal = () => {
        openModal(
            <EditProjectModal project={project} onClose={closeModal} />,
            { size: "md" }
        );
    };
    const openAddTaskModal = () => {
        openModal(
            <AddTaskModal project={project} employees={employees} onClose={closeModal} />,
            { size: "xl" }
        );
    };
    const handleArchive = () => {
        if (confirm(`Archive "${project.name}"?`)) {
            router.delete(`/manager/projects/${project.id}`, {
                onSuccess: () => router.visit("/manager/projects"),
            });
        }
    };

    return (
        <Layout>
            <Head title={project.name} />

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">

                {/* Breadcrumb */}
                <div className="flex items-center gap-2 text-sm text-gray-500">
                    <a href={basePath} className="hover:text-primary transition-colors flex items-center gap-1">
                        <ArrowLeft size={14} />
                        Projects
                    </a>
                    <ChevronRight size={14} className="text-gray-300" />
                    <span className="text-gray-700 font-medium truncate">{project.name}</span>
                </div>

                {/* Project Header */}
                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                    <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-3 flex-wrap">
                                <h1 className="text-2xl font-bold text-gray-900">{project.name}</h1>
                                <Badge config={ps} />
                            </div>

                            {project.description && (
                                <p className="text-sm text-gray-500 mt-2 leading-relaxed">
                                    {project.description}
                                </p>
                            )}

                            {/* Meta */}
                            <div className="flex flex-wrap gap-4 mt-4">
                                {project.department && (
                                    <div className="flex items-center gap-1.5 text-xs text-gray-500">
                                        <span className="font-semibold text-gray-700">Dept:</span>
                                        {project.department.name}
                                    </div>
                                )}
                                {project.owner && (
                                    <div className="flex items-center gap-1.5 text-xs text-gray-500">
                                        <User size={12} />
                                        {project.owner.name}
                                    </div>
                                )}
                                {project.deadline && (
                                    <div className="flex items-center gap-1.5 text-xs text-gray-500">
                                        <Calendar size={12} />
                                        {new Date(project.deadline).toLocaleDateString("en-GB", {
                                            day: "numeric", month: "long", year: "numeric",
                                        })}
                                    </div>
                                )}
                                <div className="flex items-center gap-1.5 text-xs text-gray-500">
                                    <Clock size={12} />
                                    Created {new Date(project.created_at).toLocaleDateString("en-GB", {
                                    day: "numeric", month: "short", year: "numeric",
                                })}
                                </div>
                                <div className="flex items-center gap-1.5 text-xs text-gray-500">
                                    <Clock size={12} />
                                    <span className="font-semibold text-gray-700">Total time logged:</span>
                                    {formatMinutes(totalMinutes)}
                                </div>
                            </div>
                        </div>

                        {/* Actions — Edit/Archive are Manager-only: Admin's Edit request requires
                            department_id/owner_id this modal doesn't send, and Admin's delete is a
                            hard delete (not the archive this button implies). Admin already has full
                            Edit/Delete on its own Projects index page. */}
                        {isManager && (
                            <div className="flex items-center gap-2 shrink-0">
                                <button
                                    onClick={openEditModal}
                                    className="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 transition"
                                >
                                    <Edit size={14} />
                                    Edit
                                </button>

                                {project.status !== "archived" && (
                                    <button
                                        onClick={handleArchive}
                                        className="flex items-center gap-2 px-4 py-2 border border-red-200 rounded-xl text-sm font-semibold text-red-500 hover:bg-red-50 transition"
                                    >
                                        <Archive size={14} />
                                        Archive
                                    </button>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Progress bar */}
                    {totalTasks > 0 && (
                        <div className="mt-6 pt-5 border-t border-gray-100">
                            <div className="flex justify-between items-center mb-2">
                                <span className="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                    Progress
                                </span>
                                <span className="text-sm font-bold text-gray-700">
                                    {completedTasks}/{totalTasks} tasks · {completionPct}%
                                </span>
                            </div>
                            <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    className="h-full rounded-full transition-all duration-500"
                                    style={{
                                        width: `${completionPct}%`,
                                        background: completionPct === 100 ? "#22c55e" : "var(--color-primary, #6366f1)",
                                    }}
                                />
                            </div>

                            {/* Status summary */}
                            <div className="flex flex-wrap gap-3 mt-3">
                                {STATUS_ORDER.map(s => {
                                    const cnt = statusCounts[s] ?? 0;
                                    if (!cnt) return null;
                                    const cfg = STATUS_CONFIG[s];
                                    return (
                                        <span key={s} className={`px-2 py-0.5 rounded-full text-xs font-semibold ${cfg.className}`}>
                                            {cnt} {cfg.label}
                                        </span>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>

                {/* Tab navigation */}
                <div className="border-b border-gray-200" role="tablist" aria-label="Project views">
                    <div className="flex gap-1 -mb-px">
                        {[
                            { key: "tasks", label: "Tasks", icon: <ListTodo size={15} /> },
                            { key: "timeline", label: "Timeline", icon: <CalendarDays size={15} /> },
                        ].map(({ key, label, icon }) => {
                            const isActive = activeTab === key;
                            return (
                                <Link
                                    key={key}
                                    href={`${basePath}/${project.id}${key === "timeline" ? "?tab=timeline" : ""}`}
                                    role="tab"
                                    aria-selected={isActive}
                                    aria-current={isActive ? "page" : undefined}
                                    className={`flex items-center gap-2 px-4 py-2.5 text-sm font-semibold border-b-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded-t-lg ${
                                        isActive
                                            ? "border-primary text-primary"
                                            : "border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                    }`}
                                >
                                    {icon}
                                    {label}
                                </Link>
                            );
                        })}
                    </div>
                </div>

                {activeTab === "timeline" && (
                    <TimelineTab
                        timeline={timeline}
                        filters={filters}
                        employees={employees}
                        basePath={basePath}
                        taskBasePath={taskBasePath}
                        projectId={project.id}
                    />
                )}

                {/* Tasks Section */}
                {activeTab === "tasks" && tasks && (
                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-6 py-4 border-b border-gray-100">
                        <h2 className="text-base font-bold text-gray-900">
                            Tasks
                            <span className="ml-2 px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold">
                                {totalTasks}
                            </span>
                        </h2>
                        <div className="flex items-center gap-3">
                            <form onSubmit={handleSearch} className="relative">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
                                <input
                                    type="text"
                                    placeholder="Search tasks..."
                                    className="pl-8 pr-3 py-1.5 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-xs w-48"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </form>
                            {isManager && (
                                <button
                                    type="button"
                                    onClick={openAddTaskModal}
                                    className="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white rounded-xl text-xs font-semibold hover:bg-primary/90 transition"
                                >
                                    <Plus size={14} />
                                    Add Task
                                </button>
                            )}
                        </div>
                    </div>

                    {/* Status filter pills */}
                    <div className="flex flex-wrap gap-2 px-6 py-3 border-b border-gray-100">
                        {STATUS_ORDER.map(s => {
                            const cfg = STATUS_CONFIG[s];
                            return (
                                <button
                                    key={s}
                                    onClick={() => handleStatusFilter(s)}
                                    className={`px-3 py-1.5 rounded-full text-xs font-semibold border transition ${
                                        statusFilter === s
                                            ? cfg.className + " border-transparent"
                                            : "bg-white text-gray-500 border-gray-200 hover:border-gray-300"
                                    }`}
                                >
                                    {cfg.label} · {statusCounts[s] ?? 0}
                                </button>
                            );
                        })}
                        {statusFilter && (
                            <button
                                onClick={() => handleStatusFilter("")}
                                className="px-3 py-1.5 rounded-full text-xs font-semibold border border-gray-200 text-gray-400 hover:text-gray-600 transition"
                            >
                                Clear ✕
                            </button>
                        )}
                    </div>

                    {tasks.data.length === 0 ? (
                        <div className="py-16 text-center">
                            <p className="text-gray-400 text-sm">
                                {totalTasks === 0 ? "No tasks yet." : "No tasks match your filters."}
                            </p>
                            {totalTasks === 0 && isManager && (
                                <button
                                    type="button"
                                    onClick={openAddTaskModal}
                                    className="mt-3 inline-block text-sm text-primary font-semibold hover:underline"
                                >
                                    Add the first task →
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left whitespace-nowrap">
                                <thead className="bg-gray-50/50 text-gray-400 text-[10px] uppercase tracking-wider font-bold">
                                <tr>
                                    <th className="px-6 py-2">Task</th>
                                    <th className="px-6 py-2">Status</th>
                                    <th className="px-6 py-2">Priority</th>
                                    <th className="px-6 py-2">Assignee</th>
                                    <th className="px-6 py-2">Due Date</th>
                                    <th className="px-6 py-2">Est. Hours</th>
                                </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                {tasks.data.map(task => (
                                    <TaskRow key={task.id} task={task} />
                                ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <Pagination links={tasks} />
                </div>
                )}
            </div>
        </Layout>
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

// ─── Timeline Tab ─────────────────────────────────────────────────────────────

function TimelineTab({ timeline, filters = {}, employees = [], basePath, taskBasePath, projectId }) {
    const paginator = timeline?.tasks;
    const bucketCounts = timeline?.bucketCounts ?? {};
    const projectTaskTotal = timeline?.projectTaskTotal ?? 0;

    const [search, setSearch] = useState(filters?.search || "");
    const [collapsed, setCollapsed] = useState(() =>
        TIMELINE_BUCKETS.reduce((acc, b) => ({ ...acc, [b.key]: !b.openByDefault }), {})
    );

    const activeFilters = ["search", "status", "priority", "assignee_id"]
        .filter((key) => filters?.[key])
        .map((key) => ({ key, label: FILTER_LABELS[key], value: filters[key] }));

    const filteredTotal = TIMELINE_BUCKETS.reduce((sum, b) => sum + (bucketCounts[b.key] ?? 0), 0);

    const applyFilters = (overrides = {}) => {
        const params = {
            tab: "timeline",
            search,
            status: filters?.status || "",
            priority: filters?.priority || "",
            assignee_id: filters?.assignee_id || "",
            ...overrides,
        };
        Object.keys(params).forEach((k) => !params[k] && k !== "tab" && delete params[k]);
        router.get(`${basePath}/${projectId}`, params, { preserveState: true, replace: true });
    };

    const clearAll = () => {
        setSearch("");
        router.get(`${basePath}/${projectId}`, { tab: "timeline" }, { preserveState: true, replace: true });
    };

    const rows = paginator?.data ?? [];
    const visibleBuckets = TIMELINE_BUCKETS
        .map((bucket) => ({
            ...bucket,
            count: bucketCounts[bucket.key] ?? 0,
            rows: rows.filter((task) => task.timeline_bucket === bucket.key),
        }))
        .filter((bucket) => bucket.rows.length > 0);

    const hasAnyTaskInProject = projectTaskTotal > 0;
    const onlyUndated = hasAnyTaskInProject
        && filteredTotal > 0
        && (bucketCounts.no_due_date ?? 0) === filteredTotal;

    return (
        <div className="space-y-4">
            {/* Summary strip — always shows every bucket, including zeros */}
            <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-4 sm:p-5">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                    <h2 className="text-base font-bold text-gray-900 flex items-center gap-2">
                        <CalendarDays size={16} className="text-gray-400" />
                        Schedule overview
                    </h2>
                    <a
                        href={`/reports/burndown?project_id=${projectId}`}
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 rounded"
                    >
                        <TrendingDown size={14} />
                        View completion trend →
                    </a>
                </div>
                <div className="flex flex-wrap gap-2">
                    {TIMELINE_BUCKETS.map((bucket) => {
                        const tone = BUCKET_TONE[bucket.tone];
                        return (
                            <span
                                key={bucket.key}
                                className={`px-3 py-1.5 rounded-full text-xs font-semibold ${tone.chip}`}
                            >
                                {bucket.label} · {bucketCounts[bucket.key] ?? 0}
                            </span>
                        );
                    })}
                </div>
            </div>

            {/* Filters */}
            <div className="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                <div className="flex flex-col lg:flex-row lg:items-center gap-3 px-4 sm:px-6 py-4 border-b border-gray-100">
                    <form onSubmit={(e) => { e.preventDefault(); applyFilters(); }} className="relative flex-1 min-w-0">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
                        <label htmlFor="timeline-search" className="sr-only">Search tasks</label>
                        <input
                            id="timeline-search"
                            type="text"
                            placeholder="Search tasks..."
                            className="w-full pl-8 pr-3 py-2 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-xs"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </form>

                    <div className="flex flex-wrap items-center gap-2">
                        <label htmlFor="timeline-status" className="sr-only">Filter by status</label>
                        <select
                            id="timeline-status"
                            value={filters?.status || ""}
                            onChange={(e) => applyFilters({ status: e.target.value })}
                            className="px-3 py-2 rounded-lg border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        >
                            <option value="">All statuses</option>
                            {STATUS_ORDER.map((s) => (
                                <option key={s} value={s}>{STATUS_CONFIG[s].label}</option>
                            ))}
                        </select>

                        <label htmlFor="timeline-priority" className="sr-only">Filter by priority</label>
                        <select
                            id="timeline-priority"
                            value={filters?.priority || ""}
                            onChange={(e) => applyFilters({ priority: e.target.value })}
                            className="px-3 py-2 rounded-lg border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        >
                            <option value="">All priorities</option>
                            {Object.entries(PRIORITY_CONFIG).map(([key, cfg]) => (
                                <option key={key} value={key}>{cfg.label}</option>
                            ))}
                        </select>

                        <label htmlFor="timeline-assignee" className="sr-only">Filter by assignee</label>
                        <select
                            id="timeline-assignee"
                            value={filters?.assignee_id || ""}
                            onChange={(e) => applyFilters({ assignee_id: e.target.value })}
                            className="px-3 py-2 rounded-lg border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary max-w-[10rem]"
                        >
                            <option value="">All assignees</option>
                            {employees.map((u) => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                    </div>
                </div>

                {/* Active-filter context line */}
                {activeFilters.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 px-4 sm:px-6 py-3 border-b border-gray-100 bg-gray-50/60 text-xs">
                        <span className="text-gray-600 font-medium">
                            Showing <span className="font-bold text-gray-900">{filteredTotal}</span> of {projectTaskTotal} tasks
                        </span>
                        {activeFilters.map(({ key, label, value }) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => applyFilters({ [key]: "" })}
                                className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-white border border-gray-200 text-gray-600 hover:text-gray-900 hover:border-gray-300 transition"
                            >
                                {label}: {key === "assignee_id"
                                    ? (employees.find((u) => String(u.id) === String(value))?.name ?? value)
                                    : value}
                                <X size={11} aria-hidden="true" />
                                <span className="sr-only">Remove {label} filter</span>
                            </button>
                        ))}
                        <button
                            type="button"
                            onClick={clearAll}
                            className="text-primary font-semibold hover:underline"
                        >
                            Clear all
                        </button>
                    </div>
                )}

                {/* Bucket list */}
                {rows.length === 0 ? (
                    <div className="py-16 px-6 text-center">
                        <p className="text-gray-500 text-sm font-medium">
                            {!hasAnyTaskInProject
                                ? "This project has no tasks yet."
                                : "No tasks match your filters."}
                        </p>
                        <p className="text-gray-400 text-xs mt-1">
                            {!hasAnyTaskInProject
                                ? "Tasks added to this project will appear here on their due dates."
                                : "Try removing a filter to see more of the schedule."}
                        </p>
                        {hasAnyTaskInProject && activeFilters.length > 0 && (
                            <button
                                type="button"
                                onClick={clearAll}
                                className="mt-3 text-sm text-primary font-semibold hover:underline"
                            >
                                Clear all filters
                            </button>
                        )}
                    </div>
                ) : (
                    <div>
                        {onlyUndated && (
                            <p className="px-4 sm:px-6 py-3 text-xs text-gray-500 bg-amber-50/60 border-b border-amber-100">
                                None of these tasks have a due date yet — set due dates to see them scheduled across the timeline.
                            </p>
                        )}

                        {visibleBuckets.map((bucket, index) => {
                            const tone = BUCKET_TONE[bucket.tone];
                            const isCollapsed = collapsed[bucket.key];
                            const isContinued =
                                index === 0 &&
                                (paginator?.current_page ?? 1) > 1 &&
                                bucket.count > bucket.rows.length;

                            return (
                                <section key={bucket.key} aria-label={bucket.label}>
                                    <h3 className="sticky top-0 z-10">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setCollapsed((prev) => ({ ...prev, [bucket.key]: !prev[bucket.key] }))
                                            }
                                            aria-expanded={!isCollapsed}
                                            className="w-full flex items-center gap-2 px-4 sm:px-6 py-2.5 bg-gray-50/90 border-y border-gray-100 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40"
                                        >
                                            <ChevronDown
                                                size={14}
                                                className={`text-gray-400 transition-transform ${isCollapsed ? "-rotate-90" : ""}`}
                                                aria-hidden="true"
                                            />
                                            <span className={`text-[11px] font-bold uppercase tracking-widest ${tone.heading}`}>
                                                {bucket.label}
                                            </span>
                                            <span className={`px-2 py-0.5 rounded-full text-[11px] font-semibold ${tone.chip}`}>
                                                {bucket.count}
                                            </span>
                                            {isContinued && (
                                                <span className="text-[11px] text-gray-400 font-medium">(continued)</span>
                                            )}
                                        </button>
                                    </h3>

                                    {!isCollapsed && (
                                        <ul className="divide-y divide-gray-100">
                                            {bucket.rows.map((task) => (
                                                <li key={task.id}>
                                                    <TimelineTaskRow
                                                        task={task}
                                                        tone={tone}
                                                        taskBasePath={taskBasePath}
                                                    />
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>
                            );
                        })}
                    </div>
                )}

                {paginator && <Pagination links={paginator} />}
            </div>
        </div>
    );
}

function TimelineTaskRow({ task, tone, taskBasePath }) {
    const sc = STATUS_CONFIG[task.status] ?? STATUS_CONFIG.backlog;
    const pc = PRIORITY_CONFIG[task.priority] ?? PRIORITY_CONFIG.medium;
    const isOverdue = task.timeline_bucket === "overdue";
    const isCompleted = task.timeline_bucket === "completed";
    const dueLabel = formatTaskDate(task.due_date);

    return (
        <Link
            href={`${taskBasePath}/${task.id}`}
            className={`block px-4 sm:px-6 py-3 transition hover:bg-gray-50/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary/40 ${
                isOverdue ? tone.row : ""
            } ${isCompleted ? "opacity-60" : ""}`}
        >
            <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                <div className="min-w-0 flex-1">
                    <p
                        title={task.title}
                        className={`text-sm font-medium text-gray-900 truncate ${isCompleted ? "line-through" : ""}`}
                    >
                        {task.title}
                    </p>
                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xs text-gray-500">
                        <span className="inline-flex items-center gap-1">
                            <User size={11} aria-hidden="true" />
                            {task.assignee?.name ?? <span className="italic text-gray-400">Unassigned</span>}
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <Clock size={11} aria-hidden="true" />
                            Est: {task.estimated_hours ? `${task.estimated_hours}h` : "—"}
                        </span>
                        <span className="inline-flex items-center gap-1">
                            Logged: {formatMinutes(task.logged_minutes)}
                        </span>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 shrink-0">
                    {isOverdue && (
                        <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                            <AlertTriangle size={11} aria-hidden="true" />
                            Overdue
                        </span>
                    )}
                    <Badge config={sc} />
                    <Badge config={pc} />
                    <span
                        className="text-xs text-gray-500 whitespace-nowrap"
                        title={relativeDateHint(task.due_date)}
                    >
                        {dueLabel ?? <span className="text-gray-300">No due date</span>}
                    </span>
                </div>
            </div>
        </Link>
    );
}

// ─── Edit Project Modal ───────────────────────────────────────────────────────

function EditProjectModal({ project, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        name:        project.name        || "",
        description: project.description || "",
        status:      project.status      || "active",
        deadline:    project.deadline    || "",
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/manager/projects/${project.id}`, {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Edit Project</h2>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Project Name <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        required
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                        <select
                            value={data.status}
                            onChange={(e) => setData("status", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        >
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="archived">Archived</option>
                        </select>
                        {errors.status && <p className="mt-1 text-xs text-red-600">{errors.status}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Deadline</label>
                        <input
                            type="date"
                            value={data.deadline}
                            onChange={(e) => setData("deadline", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        />
                        {errors.deadline && <p className="mt-1 text-xs text-red-600">{errors.deadline}</p>}
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                    <textarea
                        rows={4}
                        value={data.description}
                        onChange={(e) => setData("description", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm resize-none"
                    />
                    {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                </div>

                <div className="flex justify-end gap-3 mt-6">
                    <button type="button" onClick={onClose} disabled={processing}
                            className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50">
                        Cancel
                    </button>
                    <button type="submit" disabled={processing}
                            className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2">
                        {processing && <RefreshCw size={16} className="animate-spin" />}
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    );
}
// ─── Add Task Modal ───────────────────────────────────────────────────────────

function AddTaskModal({ project, employees = [], onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        project_id:      project.id,
        assignee_id:     "",
        title:           "",
        description:     "",
        status:          "backlog",
        priority:        "medium",
        impact:          "low",
        effort:          "low",
        estimated_hours: "",
        due_date:        "",
    });

    const MATRIX = {
        "high-low":  { label: "🟢 Quick Win",     cls: "text-emerald-700 bg-emerald-50 border-emerald-200" },
        "high-high": { label: "🟡 Major Project", cls: "text-amber-700 bg-amber-50 border-amber-200" },
        "low-low":   { label: "🔵 Fill-in Task",  cls: "text-sky-700 bg-sky-50 border-sky-200" },
        "low-high":  { label: "🔴 Reconsider",    cls: "text-red-700 bg-red-50 border-red-200" },
    };
    const matrix = MATRIX[`${data.impact}-${data.effort}`] ?? null;

    const submit = (e) => {
        e.preventDefault();
        post("/manager/tasks", {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900">New Task</h2>
            <p className="text-sm text-gray-500 mb-6">
                in <span className="font-medium text-gray-700">{project.name}</span>
            </p>

            <form onSubmit={submit} className="space-y-5">
                {/* Title */}
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Title <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(e) => setData("title", e.target.value)}
                        placeholder="Clear, actionable task title…"
                        autoFocus
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    />
                    {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
                </div>

                {/* Description */}
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                    <textarea
                        rows={3}
                        value={data.description}
                        onChange={(e) => setData("description", e.target.value)}
                        placeholder="Context, acceptance criteria, notes…"
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                    />
                    {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                </div>

                {/* Assignee + Priority */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Assignee</label>
                        <select
                            value={data.assignee_id}
                            onChange={(e) => setData("assignee_id", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        >
                            <option value="">Unassigned</option>
                            {employees.map((u) => (
                                <option key={u.id} value={u.id}>{u.name}</option>
                            ))}
                        </select>
                        {errors.assignee_id && <p className="mt-1 text-xs text-red-600">{errors.assignee_id}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">
                            Priority <span className="text-red-500">*</span>
                        </label>
                        <select
                            value={data.priority}
                            onChange={(e) => setData("priority", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        >
                            {["low", "medium", "high", "urgent"].map((p) => (
                                <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>
                            ))}
                        </select>
                        {errors.priority && <p className="mt-1 text-xs text-red-600">{errors.priority}</p>}
                    </div>
                </div>

                {/* Impact + Effort */}
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-2">Priority Matrix</label>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <p className="text-xs text-gray-500 mb-1.5">Impact</p>
                            <select
                                value={data.impact}
                                onChange={(e) => setData("impact", e.target.value)}
                                className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            >
                                {["low", "medium", "high"].map((v) => (
                                    <option key={v} value={v}>{v.charAt(0).toUpperCase() + v.slice(1)}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500 mb-1.5">Effort</p>
                            <select
                                value={data.effort}
                                onChange={(e) => setData("effort", e.target.value)}
                                className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            >
                                {["low", "medium", "high"].map((v) => (
                                    <option key={v} value={v}>{v.charAt(0).toUpperCase() + v.slice(1)}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    {matrix && (
                        <div className={`mt-2 inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold ${matrix.cls}`}>
                            {matrix.label}
                        </div>
                    )}
                </div>

                {/* Hours + Due date */}
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Estimated Hours</label>
                        <input
                            type="number"
                            min={0}
                            step={0.25}
                            value={data.estimated_hours}
                            onChange={(e) => setData("estimated_hours", e.target.value)}
                            placeholder="0.0"
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        />
                        {errors.estimated_hours && <p className="mt-1 text-xs text-red-600">{errors.estimated_hours}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Due Date</label>
                        <input
                            type="date"
                            value={data.due_date}
                            onChange={(e) => setData("due_date", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        />
                        {errors.due_date && <p className="mt-1 text-xs text-red-600">{errors.due_date}</p>}
                    </div>
                </div>

                {/* Footer */}
                <div className="flex justify-end gap-3 pt-2">
                    <button type="button" onClick={onClose} disabled={processing}
                            className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50">
                        Cancel
                    </button>
                    <button type="submit" disabled={processing || !data.title.trim()}
                            className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2">
                        {processing && <RefreshCw size={16} className="animate-spin" />}
                        Create Task
                    </button>
                </div>
            </form>
        </div>
    );
}
