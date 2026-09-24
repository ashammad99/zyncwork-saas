// resources/js/pages/(portals)/admin/tasks/partials/CreateTaskForm.jsx

import React from "react";
import { useForm } from "@inertiajs/react";
import { RefreshCw } from "lucide-react";
import TaskFormFields from "./TaskFormFields";

export default function CreateTaskForm({ projects, users, tasks, priorities, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        project_id:         "",
        assignee_id:        null,
        depends_on_task_id: null,
        title:              "",
        description:        "",
        status: "backlog",
        priority:           "medium",
        impact:             "low",
        effort:             "low",
        estimated_hours:    null,
        due_date:           null,
        is_recurring:       false,
        recurrence_rule:    null,
        recurrence_ends_at: null,
    });

    function submit(e) {
        e.preventDefault();
        post("/admin/tasks", {
            onSuccess: () => onClose(),
        });
    }

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Create Task</h2>

            <form onSubmit={submit}>
                <TaskFormFields
                    data={data}
                    setData={setData}
                    errors={errors}
                    projects={projects}
                    users={users}
                    tasks={tasks}
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
                        Create Task
                    </button>
                </div>
            </form>
        </div>
    );
}
