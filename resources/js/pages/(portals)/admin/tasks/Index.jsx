import React, { useState } from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, router } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import {
    Plus,
    Edit,
    Trash2,
    Eye,
    RefreshCw,
    Search,
    Filter,
    ChevronDown,
    AlertCircle,
    CheckSquare,
} from "lucide-react";
import Pagination from "@/Components/ui/Pagination";
import CreateTaskForm from "./partials/CreateTaskForm";
import EditTaskForm from "./partials/EditTaskForm";

// ── Badge helpers ──────────────────────────────────────────────────────────────

const STATUS_STYLES = {
    backlog:     "bg-gray-100 text-gray-600",
    todo:        "bg-blue-50 text-blue-700",
    in_progress: "bg-amber-50 text-amber-700",
    in_review:   "bg-violet-50 text-violet-700",
    completed:   "bg-emerald-50 text-emerald-700",
};

const STATUS_LABELS = {
    backlog:     "Backlog",
    todo:        "To Do",
    in_progress: "In Progress",
    in_review:   "In Review",
    completed:   "Completed",
};

const PRIORITY_STYLES = {
    low:    "bg-gray-100 text-gray-500",
    medium: "bg-sky-50 text-sky-700",
    high:   "bg-orange-50 text-orange-700",
    urgent: "bg-red-50 text-red-700 font-semibold",
};

const PRIORITY_DOTS = {
    low:    "bg-gray-400",
    medium: "bg-sky-500",
    high:   "bg-orange-500",
    urgent: "bg-red-500",
};

function StatusBadge({ status }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_STYLES[status] ?? "bg-gray-100 text-gray-600"}`}>
            {STATUS_LABELS[status] ?? status}
        </span>
    );
}

function PriorityBadge({ priority }) {
    return (
        <span className={`inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium ${PRIORITY_STYLES[priority] ?? ""}`}>
            <span className={`h-1.5 w-1.5 rounded-full ${PRIORITY_DOTS[priority] ?? "bg-gray-400"}`} />
            {priority?.charAt(0).toUpperCase() + priority?.slice(1)}
        </span>
    );
}

// ── Main Page ──────────────────────────────────────────────────────────────────

export default function TasksPage({ tasks, filters, departments, projects, users, statuses, priorities }) {
    const { openModal, closeModal } = useModal();

    const [search, setSearch]           = useState(filters.search ?? "");
    const [statusFilter, setStatus]     = useState(filters.status ?? "");
    const [priorityFilter, setPriority] = useState(filters.priority ?? "");
    const [deptFilter, setDept]         = useState(filters.department_id ?? "");
    const [projectFilter, setProject]   = useState(filters.project_id ?? "");
    const [showFilters, setShowFilters] = useState(false);

    // Apply filters on any change
    function applyFilters(overrides = {}) {
        const params = {
            search:        search,
            status:        statusFilter,
            priority:      priorityFilter,
            department_id: deptFilter,
            project_id:    projectFilter,
            ...overrides,
        };
        // strip empty values
        Object.keys(params).forEach(k => !params[k] && delete params[k]);
        router.get("/admin/tasks", params, { preserveState: true, replace: true });
    }

    function handleSearch(e) {
        e.preventDefault();
        applyFilters();
    }

    function clearFilters() {
        setSearch("");
        setStatus("");
        setPriority("");
        setDept("");
        setProject("");
        router.get("/admin/tasks");
    }

    function handleDelete(task) {
        if (confirm(`Delete "${task.title}"? This cannot be undone.`)) {
            router.delete(`/admin/tasks/${task.id}`);
        }
    }

    function openCreateModal() {
        openModal(
            <CreateTaskForm
                projects={projects}
                users={users}
                tasks={tasks.data}
                priorities={priorities}
                onClose={closeModal}
            />,
            { size: "xl" }
        );
    }

    function openEditModal(task) {
        openModal(
            <EditTaskForm
                task={task}
                projects={projects}
                users={users}
                tasks={tasks.data}
                priorities={priorities}
                onClose={closeModal}
            />,
            { size: "xl" }
        );
    }

    const activeFilterCount = [statusFilter, priorityFilter, deptFilter, projectFilter].filter(Boolean).length;

    return (
        <AdminLayout>
            <Head title="Tasks" />

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">

                {/* ── Page Header ── */}
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            Tasks
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Manage all tasks across every department and project.
                        </p>
                    </div>

                    <button
                        onClick={openCreateModal}
                        className="bg-primary text-white p-2.5 rounded-xl hover:bg-primary/90 transition flex items-center justify-center"
                        title="Add Task"
                    >
                        <Plus size={20} />
                    </button>
                </div>

                {/* ── Search + Filter Bar ── */}
                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-4 space-y-4">
                    <div className="flex items-center gap-3">
                        {/* Search */}
                        <form onSubmit={handleSearch} className="flex-1 flex items-center gap-2">
                            <div className="relative flex-1">
                                <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    placeholder="Search tasks by title…"
                                    className="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                />
                            </div>
                            <button
                                type="submit"
                                className="px-4 py-2 bg-primary text-white text-sm font-semibold rounded-xl hover:bg-primary/90 transition"
                            >
                                Search
                            </button>
                        </form>

                        {/* Filter Toggle */}
                        <button
                            onClick={() => setShowFilters(!showFilters)}
                            className={`flex items-center gap-2 px-4 py-2 border rounded-xl text-sm font-medium transition
                                ${showFilters || activeFilterCount > 0
                                ? "border-primary bg-primary/5 text-primary"
                                : "border-gray-200 text-gray-600 hover:border-gray-300"
                            }`}
                        >
                            <Filter size={15} />
                            Filters
                            {activeFilterCount > 0 && (
                                <span className="bg-primary text-white text-[10px] font-bold rounded-full h-4 w-4 flex items-center justify-center">
                                    {activeFilterCount}
                                </span>
                            )}
                            <ChevronDown size={14} className={`transition-transform ${showFilters ? "rotate-180" : ""}`} />
                        </button>

                        {activeFilterCount > 0 && (
                            <button
                                onClick={clearFilters}
                                className="text-xs text-gray-400 hover:text-gray-700 underline whitespace-nowrap"
                            >
                                Clear all
                            </button>
                        )}
                    </div>

                    {/* Expandable Filter Row */}
                    {showFilters && (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 pt-2 border-t border-gray-100">
                            <select
                                value={statusFilter}
                                onChange={e => { setStatus(e.target.value); applyFilters({ status: e.target.value }); }}
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            >
                                <option value="">All Statuses</option>
                                {statuses.map(s => (
                                    <option key={s} value={s}>{STATUS_LABELS[s] ?? s}</option>
                                ))}
                            </select>

                            <select
                                value={priorityFilter}
                                onChange={e => { setPriority(e.target.value); applyFilters({ priority: e.target.value }); }}
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            >
                                <option value="">All Priorities</option>
                                {priorities.map(p => (
                                    <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>
                                ))}
                            </select>

                            <select
                                value={deptFilter}
                                onChange={e => {
                                    setDept(e.target.value);
                                    setProject("");
                                    applyFilters({ department_id: e.target.value, project_id: "" });
                                }}
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            >
                                <option value="">All Departments</option>
                                {departments.map(d => (
                                    <option key={d.id} value={d.id}>{d.name}</option>
                                ))}
                            </select>

                            <select
                                value={projectFilter}
                                onChange={e => { setProject(e.target.value); applyFilters({ project_id: e.target.value }); }}
                                className="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            >
                                <option value="">All Projects</option>
                                {projects
                                    .filter(p => !deptFilter || String(p.department_id) === String(deptFilter))
                                    .map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))
                                }
                            </select>
                        </div>
                    )}
                </div>

                {/* ── Table ── */}
                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm whitespace-nowrap">
                            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th className="px-6 py-4">Task</th>
                                <th className="px-6 py-4">Project / Dept</th>
                                <th className="px-6 py-4">Assignee</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4">Priority</th>
                                <th className="px-6 py-4">Due Date</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                            {tasks.data.map(task => (
                                <tr key={task.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4 max-w-xs">
                                        <p className="font-semibold text-gray-900 truncate">{task.title}</p>
                                        {task.dependency && (
                                            <span className="inline-flex items-center gap-1 text-[11px] text-red-500 mt-0.5">
                                                    <AlertCircle size={10} />
                                                    Blocked by #{task.dependency.id}
                                                </span>
                                        )}
                                    </td>

                                    <td className="px-6 py-4">
                                        <p className="font-medium text-gray-700">{task.project?.name ?? "—"}</p>
                                        <p className="text-xs text-gray-400">{task.project?.department?.name ?? "—"}</p>
                                    </td>

                                    <td className="px-6 py-4">
                                        {task.assignee ? (
                                            <div className="flex items-center gap-2">
                                                <div className="h-7 w-7 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary shrink-0">
                                                    {task.assignee.name.charAt(0).toUpperCase()}
                                                </div>
                                                <span className="text-gray-700 text-xs">{task.assignee.name}</span>
                                            </div>
                                        ) : (
                                            <span className="text-gray-400 text-xs italic">Unassigned</span>
                                        )}
                                    </td>

                                    <td className="px-6 py-4">
                                        <StatusBadge status={task.status} />
                                    </td>

                                    <td className="px-6 py-4">
                                        <PriorityBadge priority={task.priority} />
                                    </td>

                                    <td className="px-6 py-4 text-gray-500 text-xs">
                                        {task.due_date
                                            ? new Date(task.due_date).toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" })
                                            : <span className="text-gray-300">—</span>
                                        }
                                    </td>

                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <a
                                                href={`/admin/tasks/${task.id}`}
                                                className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                title="View Task"
                                            >
                                                <Eye size={16} />
                                            </a>
                                            <button
                                                onClick={() => openEditModal(task)}
                                                className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                title="Edit Task"
                                            >
                                                <Edit size={16} />
                                            </button>
                                            <button
                                                onClick={() => handleDelete(task)}
                                                className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete Task"
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>

                        {tasks.data.length === 0 && (
                            <div className="py-16 text-center">
                                <CheckSquare size={32} className="mx-auto mb-3 text-gray-300" />
                                <p className="text-gray-500 font-medium">No tasks found</p>
                                <p className="text-gray-400 text-sm mt-1">Try adjusting your filters or create a new task</p>
                            </div>
                        )}
                    </div>
                </div>

                {tasks.total > 0 && <Pagination links={tasks.links} />}
            </div>
        </AdminLayout>
    );
}
