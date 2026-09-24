// resources/js/pages/(portals)/admin/tasks/partials/TaskFormFields.jsx
// Shared form body — used by both CreateTaskForm and EditTaskForm modals

import React from "react";
import { RECURRING_TASKS_ENABLED } from "@/Utils/features";

const MATRIX_MAP = {
    "high-low":  { label: "🟢 Quick Win",      cls: "text-emerald-700 bg-emerald-50 border-emerald-200" },
    "high-high": { label: "🟡 Major Project",  cls: "text-amber-700 bg-amber-50 border-amber-200" },
    "low-low":   { label: "🔵 Fill-in Task",   cls: "text-sky-700 bg-sky-50 border-sky-200" },
    "low-high":  { label: "🔴 Reconsider",     cls: "text-red-700 bg-red-50 border-red-200" },
};

const STATUSES = [
    { value: "backlog",     label: "Backlog" },
    { value: "todo",        label: "To Do" },
    { value: "in_progress", label: "In Progress" },
    { value: "in_review",   label: "In Review" },
    { value: "completed",   label: "Completed" },
];

export default function TaskFormFields({ data, setData, errors, projects, users, tasks, priorities }) {
    const matrixKey = data.impact && data.effort ? `${data.impact}-${data.effort}` : null;
    const matrix = matrixKey ? MATRIX_MAP[matrixKey] : null;

    return (
        <div className="space-y-5">
            {/* Title */}
            <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">
                    Title <span className="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    value={data.title}
                    onChange={e => setData("title", e.target.value)}
                    placeholder="What needs to be done?"
                    className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                />
                {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
            </div>

            {/* Description */}
            <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Description</label>
                <textarea
                    rows={3}
                    value={data.description ?? ""}
                    onChange={e => setData("description", e.target.value)}
                    placeholder="Details, context, or acceptance criteria… (Markdown supported)"
                    className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm resize-none"
                />
                {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
            </div>

            {/* Project + Assignee */}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Project <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.project_id ?? ""}
                        onChange={e => setData("project_id", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        <option value="">Select project…</option>
                        {(projects ?? []).map(p => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </select>
                    {errors.project_id && <p className="mt-1 text-xs text-red-600">{errors.project_id}</p>}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Assignee</label>
                    <select
                        value={data.assignee_id ?? ""}
                        onChange={e => setData("assignee_id", e.target.value || null)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        <option value="">Unassigned</option>
                        {(users ?? []).map(u => (
                            <option key={u.id} value={u.id}>{u.name}</option>
                        ))}
                    </select>
                    {errors.assignee_id && <p className="mt-1 text-xs text-red-600">{errors.assignee_id}</p>}
                </div>
            </div>

            {/* Status + Priority */}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Status <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.status}
                        onChange={e => setData("status", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        {STATUSES.map(s => (
                            <option key={s.value} value={s.value}>{s.label}</option>
                        ))}
                    </select>
                    {errors.status && <p className="mt-1 text-xs text-red-600">{errors.status}</p>}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Priority <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.priority}
                        onChange={e => setData("priority", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        {(priorities ?? []).map(p => (
                            <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>
                        ))}
                    </select>
                    {errors.priority && <p className="mt-1 text-xs text-red-600">{errors.priority}</p>}
                </div>
            </div>

            {/* Est. Hours + Due Date */}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Est. Hours</label>
                    <input
                        type="number"
                        min={0}
                        step={0.25}
                        value={data.estimated_hours ?? ""}
                        onChange={e => setData("estimated_hours", e.target.value || null)}
                        placeholder="e.g. 4.5"
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.estimated_hours && <p className="mt-1 text-xs text-red-600">{errors.estimated_hours}</p>}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Due Date</label>
                    <input
                        type="date"
                        value={data.due_date ?? ""}
                        onChange={e => setData("due_date", e.target.value || null)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.due_date && <p className="mt-1 text-xs text-red-600">{errors.due_date}</p>}
                </div>
            </div>

            {/* Impact + Effort + Matrix */}
            <div>
                <label className="block text-sm font-semibold text-gray-700 mb-2">Priority Matrix</label>
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <p className="text-xs text-gray-500 mb-1.5">Impact</p>
                        <div className="flex gap-2">
                            {["low", "high"].map(v => (
                                <button
                                    key={v}
                                    type="button"
                                    onClick={() => setData("impact", v)}
                                    className={`flex-1 py-1.5 rounded-lg border text-sm font-medium transition
                                        ${data.impact === v
                                        ? "border-primary bg-primary text-white"
                                        : "border-gray-200 text-gray-600 hover:border-gray-300"
                                    }`}
                                >
                                    {v.charAt(0).toUpperCase() + v.slice(1)}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div>
                        <p className="text-xs text-gray-500 mb-1.5">Effort</p>
                        <div className="flex gap-2">
                            {["low", "high"].map(v => (
                                <button
                                    key={v}
                                    type="button"
                                    onClick={() => setData("effort", v)}
                                    className={`flex-1 py-1.5 rounded-lg border text-sm font-medium transition
                                        ${data.effort === v
                                        ? "border-primary bg-primary text-white"
                                        : "border-gray-200 text-gray-600 hover:border-gray-300"
                                    }`}
                                >
                                    {v.charAt(0).toUpperCase() + v.slice(1)}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
                {matrix && (
                    <div className={`mt-2 inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold ${matrix.cls}`}>
                        {matrix.label}
                    </div>
                )}
            </div>

            {/* Dependency */}
            <div>
                <label className="block text-sm font-semibold text-gray-700 mb-1">Blocked by (depends on)</label>
                <select
                    value={data.depends_on_task_id ?? ""}
                    onChange={e => setData("depends_on_task_id", e.target.value || null)}
                    className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                >
                    <option value="">No dependency</option>
                    {(tasks ?? []).map(t => (
                        <option key={t.id} value={t.id}>#{t.id} — {t.title}</option>
                    ))}
                </select>
                <p className="mt-1 text-xs text-gray-400">This task cannot start until the selected task is completed.</p>
                {errors.depends_on_task_id && <p className="mt-1 text-xs text-red-600">{errors.depends_on_task_id}</p>}
            </div>

            {/* Recurrence */}
            {RECURRING_TASKS_ENABLED && (
            <div>
                <label className="flex items-center gap-2.5 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={data.is_recurring ?? false}
                        onChange={e => setData("is_recurring", e.target.checked)}
                        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                    />
                    <span className="text-sm font-semibold text-gray-700">Recurring task</span>
                </label>

                {data.is_recurring && (
                    <div className="mt-3 grid grid-cols-2 gap-4 pl-6">
                        <div>
                            <label className="block text-xs text-gray-500 mb-1">Repeat frequency</label>
                            <select
                                value={data.recurrence_rule ?? ""}
                                onChange={e => setData("recurrence_rule", e.target.value || null)}
                                className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                            >
                                <option value="">Select…</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            {errors.recurrence_rule && <p className="mt-1 text-xs text-red-600">{errors.recurrence_rule}</p>}
                        </div>
                        <div>
                            <label className="block text-xs text-gray-500 mb-1">Ends on (optional)</label>
                            <input
                                type="date"
                                value={data.recurrence_ends_at ?? ""}
                                onChange={e => setData("recurrence_ends_at", e.target.value || null)}
                                className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                            />
                            {errors.recurrence_ends_at && <p className="mt-1 text-xs text-red-600">{errors.recurrence_ends_at}</p>}
                        </div>
                    </div>
                )}
            </div>
            )}
        </div>
    );
}
