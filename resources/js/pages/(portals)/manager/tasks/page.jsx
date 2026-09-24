import React, { useState } from "react";
import { RECURRING_TASKS_ENABLED } from "@/Utils/features";
import ManagerLayout from "../layout";
import { Head, useForm, router, Link } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import { Plus, Edit, Eye, RefreshCw, Search, CheckSquare, AlertCircle, RotateCcw } from "lucide-react";
import Pagination from "@/Components/ui/Pagination";

const STATUS_FLOW = ["backlog", "todo", "in_progress", "in_review", "completed"];

const STATUS_LABELS = {
    backlog: "Backlog",
    todo: "Todo",
    in_progress: "In Progress",
    in_review: "In Review",
    completed: "Completed",
};

const STATUS_COLORS = {
    backlog: "bg-gray-100 text-gray-600",
    todo: "bg-blue-100 text-blue-700",
    in_progress: "bg-yellow-100 text-yellow-700",
    in_review: "bg-purple-100 text-purple-700",
    completed: "bg-green-100 text-green-700",
};

const PRIORITY_COLORS = {
    low: "bg-gray-100 text-gray-600",
    medium: "bg-blue-100 text-blue-700",
    high: "bg-orange-100 text-orange-700",
    urgent: "bg-red-100 text-red-700",
};

export default function TasksPage({ tasks, projects, employees, filters }) {
    const { openModal, closeModal } = useModal();
    const [search, setSearch] = useState(filters?.search || "");
    const [statusFilter, setStatusFilter] = useState(filters?.status || "");
    const [projectFilter, setProjectFilter] = useState(filters?.project_id || "");

    const handleSearch = (e) => {
        e.preventDefault();
        router.get("/manager/tasks", { search, status: statusFilter, project_id: projectFilter }, { preserveState: true, replace: true });
    };

    const openCreateModal = () => {
        openModal(<TaskForm projects={projects} employees={employees} onClose={closeModal} />, { size: "md" });
    };

    const openEditModal = (task) => {
        openModal(<TaskForm task={task} projects={projects} employees={employees} onClose={closeModal} />, { size: "md" });
    };

    const confirmReactivate = (task) => {
        openModal(
            <div className="p-6">
                <div className="flex items-center gap-4 mb-4 text-blue-600 bg-blue-50 p-4 rounded-xl border border-blue-200">
                    <RotateCcw size={28} />
                    <div>
                        <h3 className="text-lg font-bold">Reactivate Task</h3>
                        <p className="text-sm opacity-80">This will reset the task status to Todo.</p>
                    </div>
                </div>
                <p className="mb-6 text-gray-700">
                    Reactivating <strong>{task.title}</strong> will clear its completion timestamp and push dependent tasks back to Todo.
                </p>
                <div className="flex justify-end gap-3">
                    <button onClick={closeModal} className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button
                        onClick={() => { router.post(`/manager/tasks/${task.id}/reactivate`); closeModal(); }}
                        className="px-4 py-2 bg-blue-600 font-semibold text-white rounded-lg hover:bg-blue-700 transition"
                    >
                        Reactivate
                    </button>
                </div>
            </div>,
            { size: "md" }
        );
    };

    const handleAdvanceStatus = (task) => {
        const currentIndex = STATUS_FLOW.indexOf(task.status);
        const next = STATUS_FLOW[currentIndex + 1];
        if (!next) return;
        router.post(`/manager/tasks/${task.id}/advance-status`, { status: next });
    };

    return (
        <ManagerLayout>
            <Head title="Tasks" />
            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            Tasks
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">Manage, assign, and track all tasks in your department.</p>
                    </div>

                    <div className="flex w-full sm:w-auto items-center gap-3">
                        <form onSubmit={handleSearch} className="flex items-center gap-2 flex-1 sm:flex-none">
                            <div className="relative flex-1 sm:w-56">
                                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                                <input
                                    type="text"
                                    placeholder="Search tasks..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm transition-all"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                            >
                                <option value="">All Status</option>
                                {STATUS_FLOW.map((s) => <option key={s} value={s}>{STATUS_LABELS[s]}</option>)}
                            </select>
                            <select
                                value={projectFilter}
                                onChange={(e) => setProjectFilter(e.target.value)}
                                className="px-3 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                            >
                                <option value="">All Projects</option>
                                {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </form>
                        <button
                            onClick={openCreateModal}
                            className="bg-primary text-white p-2.5 rounded-xl hover:bg-primary/90 transition flex items-center justify-center shrink-0"
                            title="Create Task"
                        >
                            <Plus size={20} />
                        </button>
                    </div>
                </div>

                <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                                <tr>
                                    <th className="px-6 py-4">Task</th>
                                    <th className="px-6 py-4">Project</th>
                                    <th className="px-6 py-4">Assignee</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4">Priority</th>
                                    <th className="px-6 py-4">Due Date</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {tasks.data.map((task) => (
                                    <tr key={task.id} className="hover:bg-gray-50/50 transition-colors">
                                        <td className="px-6 py-4 max-w-[220px]">
                                            <div className="flex items-start gap-2">
                                                <CheckSquare size={16} className="text-gray-400 mt-0.5 shrink-0" />
                                                <div>
                                                    <p className="font-semibold text-gray-900 truncate">{task.title}</p>
                                                    {task.depends_on_task_id && task.dependency?.status !== "completed" && (
                                                        <span className="inline-flex items-center gap-1 text-[10px] text-orange-600 font-semibold mt-0.5">
                                                            <AlertCircle size={10} /> Blocked by #{task.depends_on_task_id}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-gray-600 max-w-[140px] truncate">{task.project?.name || "-"}</td>
                                        <td className="px-6 py-4">
                                            {task.assignee ? (
                                                <div className="flex items-center gap-2">
                                                    <div className="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs shrink-0">
                                                        {task.assignee.name.charAt(0)}
                                                    </div>
                                                    <span className="text-gray-700 text-xs">{task.assignee.name}</span>
                                                </div>
                                            ) : (
                                                <span className="text-gray-400 text-xs">Unassigned</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${STATUS_COLORS[task.status]}`}>
                                                {STATUS_LABELS[task.status]}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-semibold capitalize ${PRIORITY_COLORS[task.priority]}`}>
                                                {task.priority}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-gray-500 text-xs">{task.due_date || "-"}</td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-1">
                                                <Link href={`/manager/tasks/${task.id}`} className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="View">
                                                    <Eye size={15} />
                                                </Link>
                                                <button onClick={() => openEditModal(task)} className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit">
                                                    <Edit size={15} />
                                                </button>
                                                {task.status !== "completed" && (
                                                    <button
                                                        onClick={() => handleAdvanceStatus(task)}
                                                        className="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                                        title={`Move to ${STATUS_LABELS[STATUS_FLOW[STATUS_FLOW.indexOf(task.status) + 1]] || ""}`}
                                                    >
                                                        <RefreshCw size={15} />
                                                    </button>
                                                )}
                                                {task.status === "completed" && (
                                                    <button onClick={() => confirmReactivate(task)} className="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Reactivate">
                                                        <RotateCcw size={15} />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {tasks.data.length === 0 && (
                            <div className="py-12 text-center text-gray-500">No tasks found.</div>
                        )}
                    </div>
                </div>

                {tasks.total > 0 && <Pagination links={tasks.links} />}
            </div>
        </ManagerLayout>
    );
}

function TaskForm({ task, projects, employees, onClose }) {
    const isEdit = !!task;
    const { data, setData, post, put, processing, errors } = useForm({
        title: task?.title || "",
        description: task?.description || "",
        project_id: task?.project_id || "",
        assignee_id: task?.assignee_id || "",
        status: task?.status || "backlog",
        priority: task?.priority || "medium",
        impact: task?.impact || "low",
        effort: task?.effort || "low",
        estimated_hours: task?.estimated_hours || "",
        due_date: task?.due_date || "",
        depends_on_task_id: task?.depends_on_task_id || "",
        is_recurring: task?.is_recurring || false,
        recurrence_rule: task?.recurrence_rule || "",
        recurrence_ends_at: task?.recurrence_ends_at || "",
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEdit) {
            put(`/manager/tasks/${task.id}`, { onSuccess: () => onClose() });
        } else {
            post("/manager/tasks", { onSuccess: () => onClose() });
        }
    };

    return (
        <div className="p-6 max-h-[85vh] overflow-y-auto">
            <h2 className="text-xl font-bold text-gray-900 mb-6">{isEdit ? "Edit Task" : "Create Task"}</h2>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Title" error={errors.title}>
                    <input type="text" value={data.title} onChange={(e) => setData("title", e.target.value)} className={inputClass(errors.title)} required />
                </Field>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Project" error={errors.project_id}>
                        <select value={data.project_id} onChange={(e) => setData("project_id", e.target.value)} className={inputClass(errors.project_id)} required>
                            <option value="">Select project</option>
                            {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                    </Field>
                    <Field label="Assignee" error={errors.assignee_id}>
                        <select value={data.assignee_id} onChange={(e) => setData("assignee_id", e.target.value)} className={inputClass(errors.assignee_id)}>
                            <option value="">Unassigned</option>
                            {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Priority" error={errors.priority}>
                        <select value={data.priority} onChange={(e) => setData("priority", e.target.value)} className={inputClass(errors.priority)}>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </Field>
                    <Field label="Status" error={errors.status}>
                        <select value={data.status} onChange={(e) => setData("status", e.target.value)} className={inputClass(errors.status)}>
                            {STATUS_FLOW.map((s) => <option key={s} value={s}>{STATUS_LABELS[s]}</option>)}
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Impact" error={errors.impact}>
                        <select value={data.impact} onChange={(e) => setData("impact", e.target.value)} className={inputClass(errors.impact)}>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                        </select>
                    </Field>
                    <Field label="Effort" error={errors.effort}>
                        <select value={data.effort} onChange={(e) => setData("effort", e.target.value)} className={inputClass(errors.effort)}>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                        </select>
                    </Field>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <Field label="Estimated Hours" error={errors.estimated_hours}>
                        <input type="number" step="0.5" min="0" value={data.estimated_hours} onChange={(e) => setData("estimated_hours", e.target.value)} className={inputClass(errors.estimated_hours)} />
                    </Field>
                    <Field label="Due Date" error={errors.due_date}>
                        <input type="date" value={data.due_date} onChange={(e) => setData("due_date", e.target.value)} className={inputClass(errors.due_date)} />
                    </Field>
                </div>

                <Field label="Description" error={errors.description}>
                    <textarea rows="3" value={data.description} onChange={(e) => setData("description", e.target.value)} className={inputClass(errors.description)} />
                </Field>

                {/* Recurring */}
                {RECURRING_TASKS_ENABLED && (
                <div className="border-t border-gray-100 pt-4">
                    <label className="flex items-center gap-2 cursor-pointer mb-3">
                        <input
                            type="checkbox"
                            checked={data.is_recurring}
                            onChange={(e) => setData("is_recurring", e.target.checked)}
                            className="text-primary focus:ring-primary rounded"
                        />
                        <span className="text-sm font-semibold text-gray-700">Recurring Task</span>
                    </label>

                    {data.is_recurring && (
                        <div className="grid grid-cols-2 gap-4">
                            <Field label="Recurrence Rule" error={errors.recurrence_rule}>
                                <select value={data.recurrence_rule} onChange={(e) => setData("recurrence_rule", e.target.value)} className={inputClass(errors.recurrence_rule)}>
                                    <option value="">Select frequency</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </Field>
                            <Field label="Ends At (optional)" error={errors.recurrence_ends_at}>
                                <input type="date" value={data.recurrence_ends_at} onChange={(e) => setData("recurrence_ends_at", e.target.value)} className={inputClass(errors.recurrence_ends_at)} />
                            </Field>
                        </div>
                    )}
                </div>
                )}

                <FormActions onClose={onClose} processing={processing} label={isEdit ? "Save Changes" : "Create Task"} />
            </form>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">{label}</label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function FormActions({ onClose, processing, label }) {
    return (
        <div className="flex justify-end gap-3 mt-8">
            <button type="button" onClick={onClose} disabled={processing} className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50">
                Cancel
            </button>
            <button type="submit" disabled={processing} className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2">
                {processing && <RefreshCw size={16} className="animate-spin" />}
                {label}
            </button>
        </div>
    );
}

const inputClass = (error) =>
    `w-full px-4 py-2 border ${error ? "border-red-400" : "border-gray-200"} rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm`;
