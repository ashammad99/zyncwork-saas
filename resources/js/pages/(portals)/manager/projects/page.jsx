import React, { useState } from "react";
import ManagerLayout from "@/pages/(portals)/manager/layout";
import { Head, useForm, router, usePage } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import { Plus, Edit, Trash2, RefreshCw, Search, Eye } from "lucide-react";
import Pagination from "@/Components/ui/Pagination";

// ─── Status config ────────────────────────────────────────────────────────────

const STATUS_CONFIG = {
    active:   { label: "Active",   className: "bg-green-100 text-green-700" },
    on_hold:  { label: "On Hold",  className: "bg-yellow-100 text-yellow-700" },
    archived: { label: "Archived", className: "bg-gray-100 text-gray-700" },
};

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function ManagerProjectsPage({ projects, filters }) {
    const { flash } = usePage().props;
    const { openModal, closeModal } = useModal();
    const [search, setSearch] = useState(filters?.search || "");
    const [status, setStatus] = useState(filters?.status || "");

    const handleSearch = (e) => {
        e.preventDefault();
        router.get("/manager/projects", { search, status }, { preserveState: true, replace: true });
    };

    const handleStatusFilter = (val) => {
        const next = status === val ? "" : val;
        setStatus(next);
        router.get("/manager/projects", { search, status: next }, { preserveState: true, replace: true });
    };

    const openAddModal = () => {
        openModal(<AddProjectForm onClose={closeModal} />, { size: "md" });
    };

    const openEditModal = (project) => {
        openModal(<EditProjectForm project={project} onClose={closeModal} />, { size: "md" });
    };

    const handleArchive = (project) => {
        if (confirm(`Archive "${project.name}"? It will be locked from new tasks.`)) {
            router.delete(`/manager/projects/${project.id}`);
        }
    };

    return (
        <ManagerLayout>
            <Head title="Projects" />

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">


                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            Projects
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Manage projects within your department.
                        </p>
                    </div>

                    <div className="flex w-full sm:w-auto items-center gap-3">
                        {/* Search */}
                        <form onSubmit={handleSearch} className="relative w-full sm:w-72">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                            <input
                                type="text"
                                placeholder="Search projects..."
                                className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm transition-all"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </form>

                        {/* Add button */}
                        <button
                            onClick={openAddModal}
                            className="bg-primary text-white p-2.5 rounded-xl hover:bg-primary/90 transition flex items-center justify-center shrink-0"
                            title="Add Project"
                        >
                            <Plus size={20} />
                        </button>
                    </div>
                </div>

                {/* Status filter pills */}
                <div className="flex gap-2 flex-wrap">
                    {Object.entries(STATUS_CONFIG).map(([val, cfg]) => (
                        <button
                            key={val}
                            onClick={() => handleStatusFilter(val)}
                            className={`px-3 py-1.5 rounded-full text-xs font-semibold border transition ${
                                status === val
                                    ? cfg.className + " border-transparent"
                                    : "bg-white text-gray-500 border-gray-200 hover:border-gray-300"
                            }`}
                        >
                            {cfg.label}
                        </button>
                    ))}
                    {status && (
                        <button
                            onClick={() => handleStatusFilter("")}
                            className="px-3 py-1.5 rounded-full text-xs font-semibold border border-gray-200 text-gray-400 hover:text-gray-600 transition"
                        >
                            Clear ✕
                        </button>
                    )}
                </div>

                {/* Table */}
                <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th className="px-6 py-4">Project</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4">Tasks</th>
                                <th className="px-6 py-4">Deadline</th>
                                <th className="px-6 py-4">Description</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                            </thead>

                            <tbody className="divide-y divide-gray-100">
                            {projects.data.map((project) => {
                                const sc = STATUS_CONFIG[project.status] ?? STATUS_CONFIG.active;
                                return (
                                    <tr key={project.id} className="hover:bg-gray-50/50 transition-colors">
                                        <td className="px-6 py-4">
                                            <p className="font-semibold text-gray-900">{project.name}</p>
                                            {project.owner && (
                                                <p className="text-xs text-gray-400 mt-0.5">
                                                    Owner: {project.owner.name}
                                                </p>
                                            )}
                                        </td>

                                        <td className="px-6 py-4">
                                                <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${sc.className}`}>
                                                    <span className="w-1.5 h-1.5 rounded-full bg-current opacity-70" />
                                                    {sc.label}
                                                </span>
                                        </td>

                                        <td className="px-6 py-4 text-gray-600">
                                                <span className="px-2 py-1 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                                                    {project.tasks_count ?? 0} tasks
                                                </span>
                                        </td>

                                        <td className="px-6 py-4 text-gray-600">
                                            {project.deadline
                                                ? new Date(project.deadline).toLocaleDateString("en-GB", {
                                                    day: "numeric", month: "short", year: "numeric",
                                                })
                                                : <span className="text-gray-400 italic">No deadline</span>
                                            }
                                        </td>

                                        <td className="px-6 py-4 text-gray-500 max-w-[240px] truncate">
                                            {project.description || <span className="italic text-gray-300">—</span>}
                                        </td>

                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <a
                                                    href={`/manager/projects/${project.id}`}
                                                    className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                    title="View Project"
                                                >
                                                    <Eye size={16} />
                                                </a>

                                                <button
                                                    onClick={() => openEditModal(project)}
                                                    className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                    title="Edit Project"
                                                >
                                                    <Edit size={16} />
                                                </button>

                                                {project.status !== "archived" && (
                                                    <button
                                                        onClick={() => handleArchive(project)}
                                                        className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                        title="Archive Project"
                                                    >
                                                        <Trash2 size={16} />
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            </tbody>
                        </table>

                        {projects.data.length === 0 && (
                            <div className="py-12 text-center">
                                <p className="text-gray-500">No projects found.</p>
                                <button
                                    onClick={openAddModal}
                                    className="mt-4 text-sm text-primary font-semibold hover:underline"
                                >
                                    Create your first project →
                                </button>
                            </div>
                        )}
                    </div>
                </div>

                {projects.total > 0 && <Pagination links={projects.links} />}
            </div>
        </ManagerLayout>
    );
}

// ─── Add Project Form ─────────────────────────────────────────────────────────
// No department_id or owner_id — both are auto-set server-side from auth user

function AddProjectForm({ onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        name:        "",
        description: "",
        status:      "active",
        deadline:    "",
    });

    const submit = (e) => {
        e.preventDefault();
        post("/manager/projects", { onSuccess: () => onClose() });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">New Project</h2>

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
                        placeholder="e.g. Q3 Marketing Campaign"
                        autoFocus
                        required
                    />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">
                            Status <span className="text-red-500">*</span>
                        </label>
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
                        <p className="mt-1 text-xs text-gray-400">Enables burndown chart.</p>
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
                        placeholder="Briefly describe the project goals and scope…"
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
                        Create Project
                    </button>
                </div>
            </form>
        </div>
    );
}

// ─── Edit Project Form ────────────────────────────────────────────────────────

function EditProjectForm({ project, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        name:        project.name        || "",
        description: project.description || "",
        status:      project.status      || "active",
        deadline:    project.deadline    || "",
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/manager/projects/${project.id}`, { onSuccess: () => onClose() });
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
                        <label className="block text-sm font-semibold text-gray-700 mb-1">
                            Status <span className="text-red-500">*</span>
                        </label>
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

                {/* Danger zone — archive */}
                {project.status !== "archived" && (
                    <div className="border border-red-100 rounded-xl p-4 bg-red-50/50">
                        <p className="text-xs font-semibold text-red-500 uppercase tracking-wide mb-1">Danger Zone</p>
                        <p className="text-xs text-gray-500 mb-3">
                            Archiving locks this project from receiving new tasks.
                        </p>
                        <button
                            type="button"
                            onClick={() => {
                                if (confirm("Archive this project?")) {
                                    router.delete(`/manager/projects/${project.id}`, {
                                        onSuccess: () => onClose(),
                                    });
                                }
                            }}
                            className="text-xs font-semibold text-red-500 border border-red-300 px-3 py-1.5 rounded-lg hover:bg-red-100 transition"
                        >
                            Archive Project
                        </button>
                    </div>
                )}

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
