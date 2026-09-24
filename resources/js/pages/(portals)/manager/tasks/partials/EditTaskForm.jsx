// resources/js/pages/(portals)/manager/tasks/partials/EditTaskForm.jsx

import React from "react";
import { useForm } from "@inertiajs/react";
import { RefreshCw } from "lucide-react";
import TaskFormFields from "./TaskFormFields";

export default function EditTaskForm({ task, projects, users, tasks, priorities, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        project_id:         task.project_id         ?? "",
        assignee_id:        task.assignee_id         ?? null,
        depends_on_task_id: task.depends_on_task_id  ?? null,
        title:              task.title               ?? "",
        description:        task.description         ?? "",
        priority:           task.priority            ?? "medium",
        impact:             task.impact              ?? "low",
        effort:             task.effort              ?? "low",
        estimated_hours:    task.estimated_hours     ?? null,
        due_date:           task.due_date            ? task.due_date.split("T")[0]            : null,
        recurrence_ends_at: task.recurrence_ends_at  ? task.recurrence_ends_at.split("T")[0] : null,
        recurrence_rule:    task.recurrence_rule     ?? null,
        is_recurring:       task.is_recurring        ?? false,
    });

    function submit(e) {
        e.preventDefault();
        put(`/manager/tasks/${task.id}`, {
            onSuccess: () => onClose(),
        });
    }

    const dependencyOptions = (tasks ?? []).filter(t => t.id !== task.id);

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-1">Edit Task</h2>
            <p className="text-sm text-gray-500 mb-6 truncate max-w-md">{task.title}</p>

            <form onSubmit={submit}>
                <TaskFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    projects={projects}
                    users={users}
                    tasks={dependencyOptions}
                    priorities={priorities}
                />

                <div className="flex justify-end gap-3 mt-8">
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={processing}
                        className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2"
                    >
                        {processing && <RefreshCw size={16} className="animate-spin" />}
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    );
}
