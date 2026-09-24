import React from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, useForm, router } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import { Plus, Edit, Trash2, RefreshCw } from "lucide-react";
import Pagination from "@/Components/ui/Pagination";

export default function DepartmentsPage({ departments, managers }) {
    const { openModal, closeModal } = useModal();

    const openAddModal = () => {
        openModal(<AddDepartmentForm managers={managers} onClose={closeModal} />, {
            size: "md",
        });
    };

    const openEditModal = (department) => {
        openModal(
            <EditDepartmentForm
                department={department}
                managers={managers}
                onClose={closeModal}
            />,
            { size: "md" }
        );
    };

    const handleDelete = (department) => {
        if (confirm(`Delete ${department.name}?`)) {
            router.delete(`/admin/departments/${department.id}`);
        }
    };

    return (
        <AdminLayout>
            <Head title="Departments" />

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            Departments
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Manage departments and assign managers.
                        </p>
                    </div>

                    <button
                        onClick={openAddModal}
                        className="bg-primary text-white p-2.5 rounded-xl hover:bg-primary/90 transition flex items-center justify-center"
                        title="Add Department"
                    >
                        <Plus size={20} />
                    </button>
                </div>

                <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th className="px-6 py-4">Department</th>
                                <th className="px-6 py-4">Manager</th>
                                <th className="px-6 py-4">Color</th>
                                <th className="px-6 py-4">Description</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                            {departments.data.map((department) => (
                                <tr key={department.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4 font-semibold text-gray-900">
                                        {department.name}
                                    </td>
                                    <td className="px-6 py-4 text-gray-600">
                                        {department.manager?.name || "-"}
                                    </td>
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-2">
                                                <span
                                                    className="w-4 h-4 rounded-full border"
                                                    style={{
                                                        backgroundColor:
                                                            department.color || "#e5e7eb",
                                                    }}
                                                />
                                            <span className="text-gray-600">
                                                    {department.color || "-"}
                                                </span>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4 text-gray-600 max-w-[260px] truncate">
                                        {department.description || "-"}
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button
                                                onClick={() => openEditModal(department)}
                                                className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors"
                                                title="Edit Department"
                                            >
                                                <Edit size={16} />
                                            </button>

                                            <button
                                                onClick={() => handleDelete(department)}
                                                className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                title="Delete Department"
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>

                        {departments.data.length === 0 && (
                            <div className="py-12 text-center">
                                <p className="text-gray-500">No departments found.</p>
                            </div>
                        )}
                    </div>
                </div>

                {departments.total > 0 && <Pagination links={departments.links} />}
            </div>
        </AdminLayout>
    );
}
function AddDepartmentForm({ managers, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        manager_id: "",
        description: "",
        color: "",
    });

    const submit = (e) => {
        e.preventDefault();

        post("/admin/departments", {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">
                Add Department
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
                        Manager
                    </label>
                    <select
                        value={data.manager_id}
                        onChange={(e) => setData("manager_id", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        <option value="">Select manager</option>
                        {managers.map((manager) => (
                            <option key={manager.id} value={manager.id}>
                                {manager.name}
                            </option>
                        ))}
                    </select>
                    {errors.manager_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.manager_id}</p>
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

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Color
                    </label>
                    <input
                        type="text"
                        value={data.color}
                        onChange={(e) => setData("color", e.target.value)}
                        placeholder="#3B82F6"
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.color && (
                        <p className="mt-1 text-xs text-red-600">{errors.color}</p>
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
                        {processing && <RefreshCw size={16} className="animate-spin" />}
                        Create Department
                    </button>
                </div>
            </form>
        </div>
    );
}
function EditDepartmentForm({ department, managers, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        name: department.name || "",
        manager_id: department.manager_id || "",
        description: department.description || "",
        color: department.color || "",
    });

    const submit = (e) => {
        e.preventDefault();

        put(`/admin/departments/${department.id}`, {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">
                Edit Department
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
                        Manager
                    </label>
                    <select
                        value={data.manager_id}
                        onChange={(e) => setData("manager_id", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    >
                        <option value="">Select manager</option>
                        {managers.map((manager) => (
                            <option key={manager.id} value={manager.id}>
                                {manager.name}
                            </option>
                        ))}
                    </select>
                    {errors.manager_id && (
                        <p className="mt-1 text-xs text-red-600">{errors.manager_id}</p>
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

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">
                        Color
                    </label>
                    <input
                        type="text"
                        value={data.color}
                        onChange={(e) => setData("color", e.target.value)}
                        className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
                    />
                    {errors.color && (
                        <p className="mt-1 text-xs text-red-600">{errors.color}</p>
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
                        {processing && <RefreshCw size={16} className="animate-spin" />}
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    );
}
