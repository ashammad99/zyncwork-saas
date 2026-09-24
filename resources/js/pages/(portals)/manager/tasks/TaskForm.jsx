import { useEffect, useState } from 'react';
import { RECURRING_TASKS_ENABLED } from "@/Utils/features";
import { router } from '@inertiajs/react';

const PRIORITY_MATRIX = {
    'high-low':  { label: 'Quick Win',       color: 'text-green-700 bg-green-50 border-green-200',  icon: '🟢' },
    'high-high': { label: 'Major Project',   color: 'text-yellow-700 bg-yellow-50 border-yellow-200', icon: '🟡' },
    'low-low':   { label: 'Fill-in Task',    color: 'text-blue-700 bg-blue-50 border-blue-200',    icon: '🔵' },
    'low-high':  { label: 'Reconsider',      color: 'text-red-700 bg-red-50 border-red-200',       icon: '🔴' },
};

export default function TaskForm({ data, setData, errors, projects, employees, dependencyOptions = [], isEdit = false }) {
    const [loadingDeps, setLoadingDeps] = useState(false);
    const [localDepOptions, setLocalDepOptions] = useState(dependencyOptions);

    const matrixKey = data.impact && data.effort ? `${data.impact}-${data.effort}` : null;
    const matrixHint = matrixKey ? PRIORITY_MATRIX[matrixKey] : null;

    // Fetch dependency options when project changes
    useEffect(() => {
        if (!data.project_id) {
            setLocalDepOptions([]);
            return;
        }

        if (!isEdit) {
            setLoadingDeps(true);
            fetch(`/manager/tasks/dependency-options/${data.project_id}` + (isEdit ? `?exclude=${data.id}` : ''))
                .then(r => r.json())
                .then(d => setLocalDepOptions(d))
                .finally(() => setLoadingDeps(false));
        }
    }, [data.project_id]);

    const inputClass = (field) =>
        `w-full rounded-lg border px-3 py-2 text-sm placeholder-slate-400 focus:outline-none focus:ring-2 transition-colors ${
            errors[field]
                ? 'border-red-300 focus:border-red-400 focus:ring-red-100'
                : 'border-slate-200 focus:border-indigo-400 focus:ring-indigo-100'
        }`;

    return (
        <div className="space-y-6">
            {/* Project & Assignee */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                        Project <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.project_id || ''}
                        onChange={e => setData('project_id', e.target.value)}
                        disabled={isEdit}
                        className={inputClass('project_id') + (isEdit ? ' bg-slate-50 cursor-not-allowed' : '')}
                    >
                        <option value="">Select project...</option>
                        {projects.map(p => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </select>
                    {errors.project_id && <p className="mt-1 text-xs text-red-600">{errors.project_id}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Assignee</label>
                    <select
                        value={data.assignee_id || ''}
                        onChange={e => setData('assignee_id', e.target.value || null)}
                        className={inputClass('assignee_id')}
                    >
                        <option value="">Unassigned</option>
                        {employees.map(e => (
                            <option key={e.id} value={e.id}>{e.name}</option>
                        ))}
                    </select>
                    {errors.assignee_id && <p className="mt-1 text-xs text-red-600">{errors.assignee_id}</p>}
                </div>
            </div>

            {/* Title */}
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">
                    Title <span className="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    value={data.title || ''}
                    onChange={e => setData('title', e.target.value)}
                    placeholder="Clear, actionable task title..."
                    className={inputClass('title')}
                />
                {errors.title && <p className="mt-1 text-xs text-red-600">{errors.title}</p>}
            </div>

            {/* Description */}
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Description</label>
                <textarea
                    value={data.description || ''}
                    onChange={e => setData('description', e.target.value)}
                    rows={4}
                    placeholder="Task context, acceptance criteria, notes..."
                    className={inputClass('description')}
                />
            </div>

            {/* Priority + Impact + Effort */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                        Priority <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.priority || 'medium'}
                        onChange={e => setData('priority', e.target.value)}
                        className={inputClass('priority')}
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                        Impact <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.impact || 'low'}
                        onChange={e => setData('impact', e.target.value)}
                        className={inputClass('impact')}
                    >
                        <option value="low">Low Impact</option>
                        <option value="high">High Impact</option>
                    </select>
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">
                        Effort <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={data.effort || 'low'}
                        onChange={e => setData('effort', e.target.value)}
                        className={inputClass('effort')}
                    >
                        <option value="low">Low Effort</option>
                        <option value="high">High Effort</option>
                    </select>
                </div>
            </div>

            {/* Priority Matrix hint */}
            {matrixHint && (
                <div className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium ${matrixHint.color}`}>
                    <span>{matrixHint.icon}</span>
                    <span>{matrixHint.label}</span>
                </div>
            )}

            {/* Estimated Hours + Due Date */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Estimated Hours</label>
                    <input
                        type="number"
                        step="0.5"
                        min="0"
                        value={data.estimated_hours || ''}
                        onChange={e => setData('estimated_hours', e.target.value)}
                        placeholder="0.0"
                        className={inputClass('estimated_hours')}
                    />
                    {errors.estimated_hours && <p className="mt-1 text-xs text-red-600">{errors.estimated_hours}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Due Date</label>
                    <input
                        type="date"
                        value={data.due_date || ''}
                        onChange={e => setData('due_date', e.target.value)}
                        className={inputClass('due_date')}
                    />
                    {errors.due_date && <p className="mt-1 text-xs text-red-600">{errors.due_date}</p>}
                </div>
            </div>

            {/* Dependency */}
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Blocked by (dependency)</label>
                <select
                    value={data.depends_on_task_id || ''}
                    onChange={e => setData('depends_on_task_id', e.target.value || null)}
                    disabled={!data.project_id || loadingDeps}
                    className={inputClass('depends_on_task_id') + (!data.project_id ? ' bg-slate-50' : '')}
                >
                    <option value="">No dependency</option>
                    {(isEdit ? dependencyOptions : localDepOptions).map(t => (
                        <option key={t.id} value={t.id}>{t.title} [{t.status}]</option>
                    ))}
                </select>
                {!data.project_id && (
                    <p className="mt-1 text-xs text-slate-400">Select a project first</p>
                )}
                {errors.depends_on_task_id && (
                    <p className="mt-1 text-xs text-red-600">{errors.depends_on_task_id}</p>
                )}
            </div>

            {/* Recurring */}
            {RECURRING_TASKS_ENABLED && (
            <div className="rounded-xl border border-slate-200 p-4 space-y-3">
                <label className="flex items-center gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={!!data.is_recurring}
                        onChange={e => setData('is_recurring', e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <span className="text-sm font-medium text-slate-700">Recurring task</span>
                        <p className="text-xs text-slate-400">Automatically clones this task on a schedule</p>
                    </div>
                </label>

                {data.is_recurring && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 pt-2 border-t border-slate-100">
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">Repeat every</label>
                            <select
                                value={data.recurrence_rule || ''}
                                onChange={e => setData('recurrence_rule', e.target.value)}
                                className={inputClass('recurrence_rule')}
                            >
                                <option value="">Select...</option>
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                            </select>
                            {errors.recurrence_rule && (
                                <p className="mt-1 text-xs text-red-600">{errors.recurrence_rule}</p>
                            )}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">End date (optional)</label>
                            <input
                                type="date"
                                value={data.recurrence_ends_at || ''}
                                onChange={e => setData('recurrence_ends_at', e.target.value)}
                                className={inputClass('recurrence_ends_at')}
                            />
                        </div>
                    </div>
                )}
            </div>
            )}
        </div>
    );
}
