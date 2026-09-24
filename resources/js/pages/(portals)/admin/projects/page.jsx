import React from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import {Head, useForm, router} from "@inertiajs/react";
import {useModal} from "@/Contexts/ModalContext";
import {Plus, Edit, Trash2, RefreshCw, Eye} from "lucide-react";
import Pagination from "@/Components/ui/Pagination";

export default function ProjectsPage({projects, departments, owners}) {
    const {openModal, closeModal} = useModal();

    const openAddModal = () => {
        openModal(
            <AddProjectForm
                departments={departments}
                owners={owners}
                onClose={closeModal}
            />,
            {size: "md"}
        );
    };

    const openEditModal = (project) => {
        openModal(
            <EditProjectForm
                project={project}
                departments={departments}
                owners={owners}
                onClose={closeModal}
            />,
            {size: "md"}
        );
    };

    const handleDelete = (project) => {
        if (confirm(`Delete ${project.name}?`)) {
            router.delete(`/admin/projects/${project.id}`);
        }
    };

    return (
        <AdminLayout>
            <Head title="Projects"/>

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            Projects
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Manage projects and assign owners.
                        </p>
                    </div>

                    <button
                        onClick={openAddModal}
                        className="bg-primary text-white p-2.5 rounded-xl hover:bg-primary/90 transition flex items-center justify-center"
                        title="Add Project"
                    >
                        <Plus size={20}/>
                    </button>
                </div>

                <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead
                                className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th className="px-6 py-4">Project</th>
                                <th className="px-6 py-4">Department</th>
                                <th className="px-6 py-4">Owner</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4">Collaborators</th>
                                <th className="px-6 py-4">Deadline</th>
                                <th className="px-6 py-4">Description</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                            </thead>

                            <tbody className="divide-y divide-gray-100">
                            {projects.data.map((project) => (
                                <tr
                                    key={project.id}
                                    className="hover:bg-gray-50/50 transition-colors"
                                >
                                    <td className="px-6 py-4 font-semibold text-gray-900">
                                        {project.name}
                                    </td>

                                    <td className="px-6 py-4 text-gray-600">
                                        {project.department?.name || "-"}
                                    </td>

                                    <td className="px-6 py-4 text-gray-600">
                                        {project.owner?.name || "-"}
                                    </td>

                                    <td className="px-6 py-4">
                                            <span
                                                className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold
                                                ${
                                                    project.status === "active"
                                                        ? "bg-green-100 text-green-700"
                                                        : project.status === "on_hold"
                                                            ? "bg-yellow-100 text-yellow-700"
                                                            : "bg-gray-100 text-gray-700"
                                                }`}
                                            >
                                                {project.status || "-"}
                                            </span>
                                    </td>
                                    <td className="px-6 py-4 text-gray-600 max-w-[260px]">
                                        {project.members?.length > 0 ? (
                                            <div className="flex flex-wrap gap-1">
                                                {project.members.map((member) => (
                                                    <span
                                                        key={member.id}
                                                        className="px-2 py-1 rounded-full bg-blue-50 text-blue-700 text-xs font-medium"
                                                    >
                    {member.name}
                </span>
                                                ))}
                                            </div>
                                        ) : (
                                            "-"
                                        )}
                                    </td>
                                    <td className="px-6 py-4 text-gray-600">
                                        {project.deadline || "-"}
                                    </td>

                                    <td className="px-6 py-4 text-gray-600 max-w-[260px] truncate">
                                        {project.description || "-"}
                                    </td>

                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <a
                                                href={`/admin/projects/${project.id}`}
                                                className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                title="View Project"
                                            >
                                                <Eye size={16}/>
                                            </a>

                                            <button
                                                onClick={() => openEditModal(project)}
                                                className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                title="Edit Project"
                                            >
                                                <Edit size={16}/>
                                            </button>

                                            <button
                                                onClick={() => handleDelete(project)}
                                                className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete Project"
                                            >
                                                <Trash2 size={16}/>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>

                        {projects.data.length === 0 && (
                            <div className="py-12 text-center">
                                <p className="text-gray-500">No projects found.</p>
                            </div>
                        )}
                    </div>
                </div>

                {projects.total > 0 && <Pagination links={projects.links}/>}
            </div>
        </AdminLayout>
    );
}

function AddProjectForm({departments, owners, onClose}) {
    const {data, setData, post, processing, errors} = useForm({

        department_id: "",
        owner_id: "",
        name: "",
        description: "",
        status: "active",
        deadline: "",
        member_ids: [],

    });

    const submit = (e) => {
        e.preventDefault();

        post("/admin/projects", {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">
                Add Project
            </h2>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Name
                    </label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        required
                    />
                    {errors.name && (
                        <p className="mt-1 text-xs text-red-600">{errors.name}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Department
                    </label>
                    <select
                        value={data.department_id}
                        onChange={(e) => setData("department_id", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        required
                    >
                        <option value="">Select department</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                    {errors.department_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.department_id}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Owner
                    </label>
                    <select
                        value={data.owner_id}
                        onChange={(e) => setData("owner_id", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        required
                    >
                        <option value="">Select owner</option>
                        {owners.map((owner) => (
                            <option key={owner.id} value={owner.id}>
                                {owner.name}
                            </option>
                        ))}
                    </select>
                    {errors.owner_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.owner_id}</p>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Cross-Department Collaborators
                    </label>

                    <select
                        multiple
                        value={data.member_ids}
                        onChange={(e) =>
                            setData(
                                "member_ids",
                                Array.from(
                                    e.target.selectedOptions,
                                    (option) => option.value
                                )
                            )
                        }
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm min-h-[120px]"
                    >
                        {owners.map((user) => (
                            <option
                                key={user.id}
                                value={user.id}
                            >
                                {user.name}
                                {user.email ? ` - ${user.email}` : ""}
                            </option>
                        ))}
                    </select>

                    <p className="mt-1 text-xs text-gray-500">
                        Hold Ctrl to select multiple collaborators
                    </p>

                    {errors.member_ids && (
                        <p className="mt-1 text-xs text-red-600">
                            {errors.member_ids}
                        </p>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Status
                    </label>
                    <select
                        value={data.status}
                        onChange={(e) => setData("status", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        <option value="active">active</option>
                        <option value="on_hold">on_hold</option>
                        <option value="archived">archived</option>
                    </select>
                    {errors.status && (
                        <p className="mt-1 text-xs text-red-600">{errors.status}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Deadline
                    </label>
                    <input
                        type="date"
                        value={data.deadline}
                        onChange={(e) => setData("deadline", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.deadline && (
                        <p className="mt-1 text-xs text-red-600">{errors.deadline}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Description
                    </label>
                    <textarea
                        rows="4"
                        value={data.description}
                        onChange={(e) => setData("description", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.description && (
                        <p className="mt-1 text-xs text-red-600">{errors.description}</p>
                    )}
                </div>

                <div className="flex justify-end gap-3 mt-8">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50"
                        disabled={processing}
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2"
                        disabled={processing}
                    >
                        {processing && <RefreshCw size={16} className="animate-spin"/>}
                        Create Project
                    </button>
                </div>
            </form>
        </div>
    );
}

function EditProjectForm({project, departments, owners, onClose}) {
    const {data, setData, put, processing, errors} = useForm({
        department_id: project.department_id || "",
        owner_id: project.owner_id || "",
        name: project.name || "",
        description: project.description || "",
        member_ids:
            project.members?.map((member) => String(member.id)) || [],
        status: project.status || "active",
        deadline: project.deadline || "",
    });

    const submit = (e) => {
        e.preventDefault();

        put(`/admin/projects/${project.id}`, {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">
                Edit Project
            </h2>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Name
                    </label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        required
                    />
                    {errors.name && (
                        <p className="mt-1 text-xs text-red-600">{errors.name}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Department
                    </label>
                    <select
                        value={data.department_id}
                        onChange={(e) => setData("department_id", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        required
                    >
                        <option value="">Select department</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
                            </option>
                        ))}
                    </select>
                    {errors.department_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.department_id}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Owner
                    </label>
                    <select
                        value={data.owner_id}
                        onChange={(e) => setData("owner_id", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                        required
                    >
                        <option value="">Select owner</option>
                        {owners.map((owner) => (
                            <option key={owner.id} value={owner.id}>
                                {owner.name}
                            </option>
                        ))}
                    </select>
                    {errors.owner_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.owner_id}</p>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Cross-Department Collaborators
                    </label>

                    <select
                        multiple
                        value={data.member_ids}
                        onChange={(e) =>
                            setData(
                                "member_ids",
                                Array.from(
                                    e.target.selectedOptions,
                                    (option) => option.value
                                )
                            )
                        }
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm min-h-[120px]"
                    >
                        {owners.map((user) => (
                            <option
                                key={user.id}
                                value={user.id}
                            >
                                {user.name}
                                {user.email ? ` - ${user.email}` : ""}
                            </option>
                        ))}
                    </select>

                    <p className="mt-1 text-xs text-gray-500">
                        Hold Ctrl to select multiple collaborators
                    </p>

                    {errors.member_ids && (
                        <p className="mt-1 text-xs text-red-600">
                            {errors.member_ids}
                        </p>
                    )}
                </div>
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Status
                    </label>
                    <select
                        value={data.status}
                        onChange={(e) => setData("status", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        <option value="active">active</option>
                        <option value="on_hold">on_hold</option>
                        <option value="archived">archived</option>
                    </select>
                    {errors.status && (
                        <p className="mt-1 text-xs text-red-600">{errors.status}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Deadline
                    </label>
                    <input
                        type="date"
                        value={data.deadline}
                        onChange={(e) => setData("deadline", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.deadline && (
                        <p className="mt-1 text-xs text-red-600">{errors.deadline}</p>
                    )}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Description
                    </label>
                    <textarea
                        rows="4"
                        value={data.description}
                        onChange={(e) => setData("description", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.description && (
                        <p className="mt-1 text-xs text-red-600">{errors.description}</p>
                    )}
                </div>

                <div className="flex justify-end gap-3 mt-8">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50"
                        disabled={processing}
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2"
                        disabled={processing}
                    >
                        {processing && <RefreshCw size={16} className="animate-spin"/>}
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    );
}
