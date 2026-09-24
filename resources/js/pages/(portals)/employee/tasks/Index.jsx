import React, { useState, useEffect } from "react";
import { Head, Link, router } from "@inertiajs/react";
import EmployeeLayout from "../layout";
import {
    Search,
    Clock,
    MessageSquare,
    AlertTriangle,
    CheckCircle2,
    PlayCircle,
    Eye,
    Filter,
    X,
} from "lucide-react";

/* ─── Style maps ─────────────────────────────────────────── */

const STATUS_STYLES = {
    backlog:     { label: "Backlog",     color: "bg-slate-100 text-slate-700 border-slate-200" },
    todo:        { label: "To Do",       color: "bg-blue-50 text-blue-700 border-blue-200" },
    in_progress: { label: "In Progress", color: "bg-amber-50 text-amber-700 border-amber-200" },
    in_review:   { label: "In Review",   color: "bg-violet-50 text-violet-700 border-violet-200" },
    completed:   { label: "Completed",   color: "bg-emerald-50 text-emerald-700 border-emerald-200" },
};

const PRIORITY_STYLES = {
    low:    { label: "Low",    color: "bg-slate-100 text-slate-600", dot: "bg-slate-400" },
    medium: { label: "Medium", color: "bg-sky-100 text-sky-700",     dot: "bg-sky-500" },
    high:   { label: "High",   color: "bg-orange-100 text-orange-700", dot: "bg-orange-500" },
    urgent: { label: "Urgent", color: "bg-red-100 text-red-700",     dot: "bg-red-500" },
};

const DUE_OPTIONS = [
    { value: "",           label: "Any date" },
    { value: "overdue",    label: "Overdue" },
    { value: "today",      label: "Due today" },
    { value: "this_week",  label: "This week" },
    { value: "no_date",    label: "No due date" },
];

/* ─── Page ───────────────────────────────────────────────── */

/**
 * @param {object} props
 * @param {{ data: Array<object>, links: Array<object>, meta: object }} props.tasks
 * @param {object} props.filters
 * @param {Array<{ id: number, name: string }>} props.projects
 * @param {Array<string>} props.statuses
 * @param {Array<string>} props.priorities
 */
export default function EmployeeTaskIndex({ tasks, filters = {}, projects = [], statuses = [], priorities = [] }) {
    const [localFilters, setLocalFilters] = useState({
        search:     filters.search ?? "",
        status:     filters.status ?? "",
        priority:   filters.priority ?? "",
        project_id: filters.project_id ?? "",
        due:        filters.due ?? "",
    });

    // Debounce search input
    useEffect(() => {
        const t = setTimeout(() => {
            if (localFilters.search !== (filters.search ?? "")) {
                applyFilters();
            }
        }, 400);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [localFilters.search]);

    const applyFilters = (override = {}) => {
        const payload = { ...localFilters, ...override };
        // Strip empty values so URL stays clean
        const clean = Object.fromEntries(
            Object.entries(payload).filter(([, v]) => v !== "" && v !== null)
        );
        router.get("/employee/tasks", clean, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const setFilter = (key, value) => {
        setLocalFilters((prev) => ({ ...prev, [key]: value }));
        if (key !== "search") {
            applyFilters({ [key]: value });
        }
    };

    const clearFilters = () => {
        setLocalFilters({ search: "", status: "", priority: "", project_id: "", due: "" });
        router.get("/employee/tasks", {}, { preserveScroll: true, replace: true });
    };

    const hasActiveFilters = Object.values(localFilters).some((v) => v !== "");

    const rows = tasks?.data ?? [];
    const meta = tasks?.meta ?? tasks; // Laravel returns meta at top level or nested depending on config

    return (
        <EmployeeLayout>
            <Head title="My Tasks" />

            <div className="max-w-7xl mx-auto space-y-6 py-6">

                {/* Header */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">My Tasks</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Tasks assigned to you across all projects.
                        </p>
                    </div>
                    <div className="text-sm text-gray-500">
                        {meta?.total ?? rows.length} total
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm space-y-4">
                    {/* Search bar */}
                    <div className="relative">
                        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            value={localFilters.search}
                            onChange={(e) => setLocalFilters((p) => ({ ...p, search: e.target.value }))}
                            placeholder="Search task titles..."
                            className="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        />
                    </div>

                    {/* Dropdowns row */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <FilterSelect
                            label="Status"
                            value={localFilters.status}
                            onChange={(v) => setFilter("status", v)}
                            options={[
                                { value: "", label: "All statuses" },
                                ...statuses.map((s) => ({
                                    value: s,
                                    label: STATUS_STYLES[s]?.label ?? s,
                                })),
                            ]}
                        />
                        <FilterSelect
                            label="Priority"
                            value={localFilters.priority}
                            onChange={(v) => setFilter("priority", v)}
                            options={[
                                { value: "", label: "All priorities" },
                                ...priorities.map((p) => ({
                                    value: p,
                                    label: PRIORITY_STYLES[p]?.label ?? p,
                                })),
                            ]}
                        />
                        <FilterSelect
                            label="Project"
                            value={localFilters.project_id}
                            onChange={(v) => setFilter("project_id", v)}
                            options={[
                                { value: "", label: "All projects" },
                                ...projects.map((p) => ({ value: p.id, label: p.name })),
                            ]}
                        />
                        <FilterSelect
                            label="Due"
                            value={localFilters.due}
                            onChange={(v) => setFilter("due", v)}
                            options={DUE_OPTIONS}
                        />
                    </div>

                    {hasActiveFilters && (
                        <button
                            onClick={clearFilters}
                            className="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 transition"
                        >
                            <X size={12} /> Clear filters
                        </button>
                    )}
                </div>

                {/* Task list */}
                {rows.length === 0 ? (
                    <EmptyState hasFilters={hasActiveFilters} onClear={clearFilters} />
                ) : (
                    <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 text-xs text-gray-600 uppercase tracking-wider">
                            <tr>
                                <th className="text-left px-4 py-3 font-semibold">Task</th>
                                <th className="text-left px-4 py-3 font-semibold">Status</th>
                                <th className="text-left px-4 py-3 font-semibold">Priority</th>
                                <th className="text-left px-4 py-3 font-semibold">Due</th>
                                <th className="text-left px-4 py-3 font-semibold">Activity</th>
                                <th className="text-right px-4 py-3 font-semibold w-20"></th>
                            </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                            {rows.map((task) => (
                                <TaskRow key={task.id} task={task} />
                            ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {/* Pagination */}
                {meta?.last_page > 1 && (
                    <Pagination links={tasks.links} />
                )}
            </div>
        </EmployeeLayout>
    );
}

/* ─── Task row ───────────────────────────────────────────── */

function TaskRow({ task }) {
    const status = STATUS_STYLES[task.status] ?? {};
    const priority = PRIORITY_STYLES[task.priority] ?? {};

    const isBlocked = task.dependency && task.dependency.status !== "completed";
    const isOverdue =
        task.due_date &&
        new Date(task.due_date) < new Date() &&
        task.status !== "completed";

    return (
        <tr className="hover:bg-gray-50 transition">
            {/* Title + project */}
            <td className="px-4 py-3">
                <Link
                    href={`/employee/tasks/${task.id}`}
                    className="block group"
                >
                    <div className="font-semibold text-gray-900 group-hover:text-primary transition">
                        {task.title}
                    </div>
                    {task.project && (
                        <div className="text-xs text-gray-500 mt-0.5">
                            {task.project.name}
                        </div>
                    )}
                    {isBlocked && (
                        <div className="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-orange-700 bg-orange-50 border border-orange-200 rounded px-1.5 py-0.5">
                            <AlertTriangle size={10} />
                            Blocked by "{task.dependency.title}"
                        </div>
                    )}
                </Link>
            </td>

            {/* Status */}
            <td className="px-4 py-3">
                <span className={`inline-flex items-center rounded-full border text-xs px-2 py-0.5 font-medium ${status.color}`}>
                    {status.label ?? task.status}
                </span>
            </td>

            {/* Priority */}
            <td className="px-4 py-3">
                <span className={`inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium ${priority.color}`}>
                    <span className={`h-1.5 w-1.5 rounded-full ${priority.dot}`} />
                    {priority.label ?? task.priority}
                </span>
            </td>

            {/* Due date */}
            <td className="px-4 py-3">
                {task.due_date ? (
                    <span className={`text-sm ${isOverdue ? "text-red-600 font-semibold" : "text-gray-700"}`}>
                        {formatDate(task.due_date)}
                        {isOverdue && (
                            <span className="ml-1 text-[10px] font-bold uppercase">Overdue</span>
                        )}
                    </span>
                ) : (
                    <span className="text-xs text-gray-400">No date</span>
                )}
            </td>

            {/* Activity counts */}
            <td className="px-4 py-3">
                <div className="flex items-center gap-3 text-xs text-gray-500">
                    {task.time_logs_count > 0 && (
                        <span className="inline-flex items-center gap-1">
                            <Clock size={12} />
                            {task.time_logs_count}
                        </span>
                    )}
                    {task.comments_count > 0 && (
                        <span className="inline-flex items-center gap-1">
                            <MessageSquare size={12} />
                            {task.comments_count}
                        </span>
                    )}
                    {!task.time_logs_count && !task.comments_count && (
                        <span className="text-gray-300">—</span>
                    )}
                </div>
            </td>

            {/* Action */}
            <td className="px-4 py-3 text-right">
                <Link
                    href={`/employee/tasks/${task.id}`}
                    className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:text-primary/80 transition"
                >
                    <Eye size={14} />
                    View
                </Link>
            </td>
        </tr>
    );
}

/* ─── Filter select ──────────────────────────────────────── */

function FilterSelect({ label, value, onChange, options }) {
    return (
        <div>
            <label className="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">
                {label}
            </label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
            >
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

/* ─── Empty state ────────────────────────────────────────── */

function EmptyState({ hasFilters, onClear }) {
    return (
        <div className="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center">
            <div className="w-12 h-12 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center text-gray-400">
                <CheckCircle2 size={24} />
            </div>
            <h3 className="font-semibold text-gray-900 mb-1">
                {hasFilters ? "No matching tasks" : "No tasks yet"}
            </h3>
            <p className="text-sm text-gray-500 mb-4">
                {hasFilters
                    ? "Try adjusting your filters."
                    : "You'll see your assigned tasks here when your manager creates them."}
            </p>
            {hasFilters && (
                <button
                    onClick={onClear}
                    className="text-sm font-medium text-primary hover:text-primary/80"
                >
                    Clear filters
                </button>
            )}
        </div>
    );
}

/* ─── Pagination ─────────────────────────────────────────── */

function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return (
        <div className="flex justify-center gap-1">
            {links.map((link, i) => (
                <Link
                    key={i}
                    href={link.url ?? "#"}
                    preserveScroll
                    dangerouslySetInnerHTML={{ __html: link.label }}
                    className={`
                        min-w-[36px] px-3 py-1.5 text-sm rounded-lg text-center transition
                        ${link.active
                        ? "bg-primary text-white font-semibold"
                        : "border border-gray-200 text-gray-700 hover:bg-gray-50"}
                        ${!link.url && "opacity-40 pointer-events-none"}
                    `}
                />
            ))}
        </div>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

function formatDate(iso) {
    return new Date(iso).toLocaleDateString(undefined, {
        month: "short",
        day: "numeric",
        year: "numeric",
    });
}
