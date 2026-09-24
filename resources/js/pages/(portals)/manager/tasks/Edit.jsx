import { Head, Link, useForm, router } from '@inertiajs/react';
import TaskForm from './TaskForm';

export default function TaskEdit({ task, projects, employees, dependencyOptions }) {
    const { data, setData, put, processing, errors } = useForm({
        project_id:         task.project_id ?? '',
        assignee_id:        task.assignee_id ?? '',
        title:              task.title ?? '',
        description:        task.description ?? '',
        priority:           task.priority ?? 'medium',
        impact:             task.impact ?? 'low',
        effort:             task.effort ?? 'low',
        estimated_hours:    task.estimated_hours ?? '',
        due_date:           task.due_date ?? '',
        depends_on_task_id: task.depends_on_task_id ?? '',
        is_recurring:       task.is_recurring ?? false,
        recurrence_rule:    task.recurrence_rule ?? '',
        recurrence_ends_at: task.recurrence_ends_at ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/manager/tasks/${task.id}`);

    };

    const handleDelete = () => {
        if (!confirm(`Delete "${task.title}"? This cannot be undone.`)) return;
        router.delete(`/manager/tasks/${task.id}`);

    };

    return (
        <>
            <Head title={`Edit: ${task.title}`} />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href={`/manager/tasks/${task.id}`}
                            className="rounded-lg border border-slate-200 p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-50 transition-colors"
                        >
                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">Edit Task</h1>
                            <p className="text-sm text-slate-500 truncate max-w-xs">{task.title}</p>
                        </div>
                    </div>

                    {task.status !== 'completed' && (
                        <button
                            onClick={handleDelete}
                            className="text-sm text-red-500 hover:text-red-700 transition-colors"
                        >
                            Delete task
                        </button>
                    )}
                </div>

                {/* Completed warning */}
                {task.status === 'completed' && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        This task is completed. Some fields may be locked.
                    </div>
                )}

                {/* Form card */}
                <div className="rounded-xl border border-slate-200 bg-white shadow-sm p-6">
                    <form onSubmit={submit} className="space-y-6">
                        <TaskForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            projects={projects}
                            employees={employees}
                            dependencyOptions={dependencyOptions}
                            isEdit
                        />

                        <div className="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <Link
                                href={`/manager/tasks/${task.id}`}
                                className="rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50 transition-colors"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
                            >
                                {processing && (
                                    <svg className="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                                    </svg>
                                )}
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
