import React, { useState } from "react";
import { router } from "@inertiajs/react";
import { X, Loader2 } from "lucide-react";

const PRIORITIES = ["low", "medium", "high", "urgent"];
const LEVELS = ["low", "medium", "high"];

const MATRIX = {
    "high-low":    { label: "🟢 Quick Win",     cls: "text-emerald-700 bg-emerald-50 border-emerald-200" },
    "high-high":   { label: "🟡 Major Project", cls: "text-amber-700 bg-amber-50 border-amber-200" },
    "low-low":     { label: "🔵 Fill-in Task",  cls: "text-sky-700 bg-sky-50 border-sky-200" },
    "low-high":    { label: "🔴 Reconsider",    cls: "text-red-700 bg-red-50 border-red-200" },
};

/**
 * Create a task already scoped to one project.
 * @param {{ id: number, name: string }} project
 * @param {Array<{ id: number, name: string }>} employees
 * @param {() => void} onClose
 */
export default function AddTaskModal({ project, employees = [], onClose }) {
    const [data, setData] = useState({
        project_id: project.id,
        assignee_id: "",
        title: "",
        description: "",
        status: "backlog",
        priority: "medium",
        impact: "low",
        effort: "low",
        estimated_hours: "",
        due_date: "",
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const set = (key, value) => setData((d) => ({ ...d, [key]: value }));

    const matrix = MATRIX[`${data.impact}-${data.effort}`] ?? null;

    const submit = () => {
        if (!data.title.trim()) {
            setErrors({ title: "Title is required." });
            return;
        }
        setProcessing(true);
        setErrors({});

        router.post("/manager/tasks", {
            ...data,
            assignee_id: data.assignee_id || null,
            estimated_hours: data.estimated_hours || null,
            due_date: data.due_date || null,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 p-4 py-10"
            onClick={onClose}
        >
            <div
                className="w-full max-w-2xl bg-white rounded-2xl shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="flex items-start justify-between gap-4 px-6 py-5 border-b border-gray-100">
                    <div>
                        <h2 className="text-xl font-bold text-gray-900">New Task</h2>
                        <p className="text-sm text-gray-500 mt-0.5">
                            in <span className="font-medium text-gray-700">{project.name}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition"
                    >
                        <X size={18} />
                    </button>
                </div>

                {/* Body */}
                <div className="px-6 py-5 space-y-5">
                    {/* Title */}
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">
                            Title <span className="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            value={data.title}
                            onChange={(e) => set("title", e.target.value)}
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
                            onChange={(e) => set("description", e.target.value)}
                            placeholder="Context, acceptance criteria, notes…"
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm resize-none focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                        />
                        {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description}</p>}
                    </div>

                    {/* Assignee + Status */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-semibold text-gray-700 mb-1">Assignee</label>
                            <select
                                value={data.assignee_id}
                                onChange={(e) => set("assignee_id", e.target.value)}
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
                                onChange={(e) => set("priority", e.target.value)}
                                className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            >
                                {PRIORITIES.map((p) => (
                                    <option key={p} value={p}>{p.charAt(0).toUpperCase() + p.slice(1)}</option>
                                ))}
                            </select>
                            {errors.priority && <p className="mt-1 text-xs text-red-600">{errors.priority}</p>}
                        </div>
                    </div>

                    {/* Impact + Effort + matrix hint */}
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-2">Priority Matrix</label>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <p className="text-xs text-gray-500 mb-1.5">Impact</p>
                                <select
                                    value={data.impact}
                                    onChange={(e) => set("impact", e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                >
                                    {LEVELS.map((v) => (
                                        <option key={v} value={v}>{v.charAt(0).toUpperCase() + v.slice(1)}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <p className="text-xs text-gray-500 mb-1.5">Effort</p>
                                <select
                                    value={data.effort}
                                    onChange={(e) => set("effort", e.target.value)}
                                    className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                                >
                                    {LEVELS.map((v) => (
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
                                onChange={(e) => set("estimated_hours", e.target.value)}
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
                                onChange={(e) => set("due_date", e.target.value)}
                                className="w-full px-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                            />
                            {errors.due_date && <p className="mt-1 text-xs text-red-600">{errors.due_date}</p>}
                        </div>
                    </div>
                </div>

                {/* Footer */}
                <div className="flex justify-end gap-3 px-6 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={processing}
                        className="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={processing || !data.title.trim()}
                        className="inline-flex items-center gap-2 px-5 py-2 text-sm font-semibold bg-primary text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
                    >
                        {processing && <Loader2 size={15} className="animate-spin" />}
                        Create Task
                    </button>
                </div>
            </div>
        </div>
    );
}
