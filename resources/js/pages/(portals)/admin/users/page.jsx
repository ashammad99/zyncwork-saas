import React, { useState } from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, Link, useForm, router } from "@inertiajs/react";
import Pagination from "@/Components/ui/Pagination";
import { useModal } from "@/Contexts/ModalContext";
import {
    Search, Edit, Ban, RefreshCw, AlertTriangle, UserPlus, Send, X,
} from "lucide-react";

/** Shown when no user can be added, so the reason (and where to fix it) is visible before trying. */
function SeatBanner({ seatStatus }) {
    if (!seatStatus) return null;

    let text = null;
    if (!seatStatus.can_add_seats) {
        text = "Your plan isn't active or your trial has ended, so no new users can be added.";
    } else if (seatStatus.used >= seatStatus.seats) {
        text = `All ${seatStatus.seats} seats are in use (active users and pending invitations).`;
    }

    if (!text) return null;

    return (
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
            <span className="flex items-start gap-2">
                <AlertTriangle size={18} className="mt-0.5 shrink-0" /> {text}
            </span>
            <Link href="/admin/billing" className="font-semibold underline shrink-0">
                View billing
            </Link>
        </div>
    );
}

export default function UsersPage({ users, filters, departments, invitations = [], seatStatus }) {
    const [search, setSearch] = useState(filters?.search || "");
    const { openModal, closeModal } = useModal();

    const handleSearch = (e) => {
        e.preventDefault();
        router.get("/admin/users", { search }, { preserveState: true, replace: true });
    };

    const openEditModal = (user) => {
        openModal(<EditUserForm user={user} departments={departments} onClose={closeModal} />, { size: "md" });
    };

    const openInviteModal = () => {
        openModal(<InviteUserForm departments={departments} onClose={closeModal} />, { size: "md" });
    };

    const resendInvitation = (invitation) => {
        router.post(`/admin/invitations/${invitation.id}/resend`, {}, { preserveScroll: true });
    };

    const confirmRevoke = (invitation) => {
        openModal(
            <div className="p-6">
                <h3 className="text-lg font-bold text-gray-900 mb-2">Revoke invitation?</h3>
                <p className="mb-6 text-gray-700">
                    The link sent to <strong>{invitation.email}</strong> will stop working.
                </p>
                <div className="flex justify-end gap-3">
                    <button onClick={closeModal} className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button
                        onClick={() => {
                            router.delete(`/admin/invitations/${invitation.id}`, { preserveScroll: true });
                            closeModal();
                        }}
                        className="px-4 py-2 font-semibold text-white rounded-lg transition bg-red-600 hover:bg-red-700"
                    >
                        Revoke
                    </button>
                </div>
            </div>,
            { size: "md" }
        );
    };

    const confirmSuspension = (user) => {
        openModal(
            <div className="p-6">
                <div className="flex items-center gap-4 mb-4 text-amber-600 bg-amber-50 p-4 rounded-xl border border-amber-200">
                    <AlertTriangle size={32} />
                    <div>
                        <h3 className="text-lg font-bold">Confirm Action</h3>
                        <p className="text-sm opacity-80">You are about to change the access status for this user.</p>
                    </div>
                </div>
                <p className="mb-6 text-gray-700">
                    Are you sure you want to {user.is_active ? "suspend/ban" : "reactivate"}{" "}
                    <strong>{user.email}</strong>?
                    {user.is_active ? " They will immediately lose access to their portal." : " They will regain access to their portal."}
                </p>
                <div className="flex justify-end gap-3 mt-6">
                    <button onClick={closeModal} className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition">
                        Cancel
                    </button>
                    <button
                        onClick={() => {
                            router.put(`/admin/users/${user.id}`, {
                                name: user.name,
                                email: user.email,
                                phone: user.phone,

                                role: user.role,
                                is_active: !user.is_active,
                                daily_capacity_hours: user.daily_capacity_hours || 8,
                                department_id: user.department_id,
                            });
                            closeModal();
                        }}
                        className={`px-4 py-2 font-semibold text-white rounded-lg transition ${user.is_active ? "bg-red-600 hover:bg-red-700" : "bg-green-600 hover:bg-green-700"}`}
                    >
                        {user.is_active ? "Suspend User" : "Reactivate User"}
                    </button>
                </div>
            </div>,
            { size: "md" }
        );
    };

    return (
        <AdminLayout>
            <Head title="User Management" />
            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            User Management
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Manage access, daily capacity hours, suspend accounts, and view user details.
                        </p>
                    </div>
                    <div className="flex w-full sm:w-auto items-center gap-3">
                        <form onSubmit={handleSearch} className="relative w-full sm:w-80">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={18} />
                            <input
                                type="text"
                                placeholder="Search by name or email..."
                                className="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm transition-all"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </form>
                        <button onClick={openInviteModal} className="bg-primary text-white p-2.5 rounded-xl hover:bg-primary/90 transition flex items-center justify-center shrink-0" title="Invite User">
                            <UserPlus size={20} />
                        </button>
                    </div>
                </div>

                <SeatBanner seatStatus={seatStatus} />

                {invitations.length > 0 && (
                    <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                        <div className="px-6 py-4 border-b border-gray-200">
                            <h2 className="font-bold text-gray-900">Pending invitations</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left whitespace-nowrap">
                                <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                                <tr>
                                    <th className="px-6 py-4">Email</th>
                                    <th className="px-6 py-4">Role</th>
                                    <th className="px-6 py-4">Department</th>
                                    <th className="px-6 py-4">Expires</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                {invitations.map((invitation) => (
                                    <tr key={invitation.id} className="hover:bg-gray-50/50 transition-colors">
                                        <td className="px-6 py-4 font-semibold text-gray-900">{invitation.email}</td>
                                        <td className="px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-700">{invitation.role}</td>
                                        <td className="px-6 py-4">
                                            {invitation.department
                                                ? <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">{invitation.department}</span>
                                                : <span className="text-xs text-gray-400 italic">No department</span>}
                                        </td>
                                        <td className="px-6 py-4 text-xs">
                                            {invitation.expired
                                                ? <span className="px-2.5 py-1 rounded-full font-semibold bg-red-100 text-red-700">Expired</span>
                                                : <span className="text-gray-500">{new Date(invitation.expires_at).toLocaleDateString()}</span>}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button onClick={() => resendInvitation(invitation)} className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Resend (new link, new expiry)">
                                                    <Send size={16} />
                                                </button>
                                                <button onClick={() => confirmRevoke(invitation)} className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Revoke">
                                                    <X size={16} />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                            <tr>
                                <th className="px-6 py-4">User Details</th>
                                <th className="px-6 py-4">Role</th>
                                <th className="px-6 py-4">Department</th>
                                <th className="px-6 py-4">Daily Capacity</th>
                                <th className="px-6 py-4">Status</th>
                                <th className="px-6 py-4">Joined</th>
                                <th className="px-6 py-4 text-right">Actions</th>
                            </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                            {users.data.map((user) => (
                                <tr key={user.id} className="hover:bg-gray-50/50 transition-colors">
                                    <td className="px-6 py-4">
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold">
                                                {user.name.charAt(0).toUpperCase()}
                                            </div>
                                            <div>
                                                <p className="font-semibold text-gray-900">{user.name}</p>
                                                <p className="text-xs text-gray-500">{user.email}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-6 py-4">
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-semibold uppercase tracking-wider ${
                                                user.role === "admin" ? "bg-purple-100 text-purple-700"
                                                    : user.role === "manager" ? "bg-blue-100 text-blue-700"
                                                        : "bg-gray-100 text-gray-700"
                                            }`}>
                                                {user.role}
                                            </span>
                                    </td>

                                    {/* ── Department ── */}
                                    <td className="px-6 py-4">
                                        {user.department ? (
                                            <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                                                    {user.department.name}
                                                </span>
                                        ) : (
                                            <span className="text-xs text-gray-400 italic">No department</span>
                                        )}
                                    </td>

                                    <td className="px-6 py-4">
                                            <span className="px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700">
                                                {user.daily_capacity_hours ?? 8} hours/day
                                            </span>
                                    </td>
                                    <td className="px-6 py-4">
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-semibold flex items-center gap-1.5 w-max ${
                                                user.is_active ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"
                                            }`}>
                                                <span className={`w-1.5 h-1.5 rounded-full ${user.is_active ? "bg-green-500" : "bg-red-500"}`} />
                                                {user.is_active ? "Active" : "Suspended"}
                                            </span>
                                    </td>
                                    <td className="px-6 py-4 text-gray-500 text-xs">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => openEditModal(user)} className="p-2 text-gray-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Edit User">
                                                <Edit size={16} />
                                            </button>
                                            <button
                                                onClick={() => confirmSuspension(user)}
                                                className={`p-2 rounded-lg transition-colors ${
                                                    user.is_active ? "text-gray-400 hover:text-red-600 hover:bg-red-50" : "text-gray-400 hover:text-green-600 hover:bg-green-50"
                                                }`}
                                                title={user.is_active ? "Suspend User" : "Reactivate User"}
                                            >
                                                {user.is_active ? <Ban size={16} /> : <RefreshCw size={16} />}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            </tbody>
                        </table>
                        {users.data.length === 0 && (
                            <div className="py-12 text-center">
                                <p className="text-gray-500">No users found matching your criteria.</p>
                            </div>
                        )}
                    </div>
                </div>
                {users.total > 0 && <Pagination links={users.links} />}
            </div>
        </AdminLayout>
    );
}

// ─── Shared Department Select ─────────────────────────────────────────────────

function DepartmentSelect({ value, onChange, departments, error }) {
    return (
        <div>
            <label className="block text-sm font-semibold text-gray-700 mb-1">
                Department
            </label>
            <select
                value={value ?? ""}
                onChange={(e) => onChange(e.target.value === "" ? null : Number(e.target.value))}
                className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm"
            >
                <option value="">— No Department (Admin only) —</option>
                {departments.map((dept) => (
                    <option key={dept.id} value={dept.id}>
                        {dept.name}
                    </option>
                ))}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
            <p className="mt-1 text-xs text-gray-500">
                Admins have no department. Managers and Employees must be assigned one.
            </p>
        </div>
    );
}

// ─── Edit User Form ───────────────────────────────────────────────────────────

function EditUserForm({ user, departments, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        name:                 user.name,
        email:                user.email,
        phone:                user.phone || "",
        role:                 user.role,
        is_active:            user.is_active,
        daily_capacity_hours: user.daily_capacity_hours || 8,
        department_id:        user.department_id ?? null,
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/admin/users/${user.id}`, { onSuccess: () => onClose() });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-6">Edit User: {user.name}</h2>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Full Name</label>
                    <input type="text" value={data.name} onChange={(e) => setData("name", e.target.value)}
                           className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" required />
                    {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                        <input type="email" value={data.email} onChange={(e) => setData("email", e.target.value)}
                               className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" required />
                        {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                        {data.email !== user.email && (
                            <p className="mt-1 text-xs text-amber-600">They'll need to confirm the new address before they can use the app again.</p>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Phone</label>
                        <input type="tel" value={data.phone} onChange={(e) => setData("phone", e.target.value)}
                               className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" />
                        {errors.phone && <p className="mt-1 text-xs text-red-600">{errors.phone}</p>}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Role</label>
                        <select value={data.role} onChange={(e) => setData("role", e.target.value)}
                                className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                            <option value="admin">Administrator</option>
                            <option value="manager">Manager</option>
                            <option value="employee">Employee</option>
                        </select>
                        {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-semibold text-gray-700 mb-1">Daily Capacity Hours</label>
                        <input type="number" min="1" max="12" value={data.daily_capacity_hours}
                               onChange={(e) => setData("daily_capacity_hours", e.target.value)}
                               className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" required />
                        {errors.daily_capacity_hours && <p className="mt-1 text-xs text-red-600">{errors.daily_capacity_hours}</p>}
                    </div>
                </div>

                <DepartmentSelect
                    value={data.department_id}
                    onChange={(val) => setData("department_id", val)}
                    departments={departments}
                    error={errors.department_id}
                />

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Account Status</label>
                    <div className="flex gap-4 mt-2">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="is_active" checked={data.is_active === true} onChange={() => setData("is_active", true)} className="text-primary focus:ring-primary" />
                            <span className="text-sm text-gray-700">Active</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer text-red-600">
                            <input type="radio" name="is_active" checked={data.is_active === false} onChange={() => setData("is_active", false)} className="text-red-600 focus:ring-red-600" />
                            <span className="text-sm font-medium">Suspended</span>
                        </label>
                    </div>
                </div>

                <div className="flex justify-end gap-3 mt-8">
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

// ─── Invite User Form ─────────────────────────────────────────────────────────
// The invitee sets their own name and password from the emailed link.

function InviteUserForm({ departments, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        email:         "",
        role:          "employee",
        department_id: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/admin/invitations`, { onSuccess: () => onClose() });
    };

    return (
        <div className="p-6">
            <h2 className="text-xl font-bold text-gray-900 mb-2">Invite a User</h2>
            <p className="text-sm text-gray-500 mb-6">
                They'll get an email with a link to set up their account. The link works once and expires after 7 days.
            </p>
            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Email</label>
                    <input type="email" value={data.email} onChange={(e) => setData("email", e.target.value)}
                           className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm" required />
                    {errors.email && <p className="mt-1 text-xs text-red-600">{errors.email}</p>}
                </div>

                <div>
                    <label className="block text-sm font-semibold text-gray-700 mb-1">Role</label>
                    <select value={data.role} onChange={(e) => setData("role", e.target.value)}
                            className="w-full px-4 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary text-sm">
                        <option value="admin">Administrator</option>
                        <option value="manager">Manager</option>
                        <option value="employee">Employee</option>
                    </select>
                    {errors.role && <p className="mt-1 text-xs text-red-600">{errors.role}</p>}
                </div>

                <DepartmentSelect
                    value={data.department_id}
                    onChange={(val) => setData("department_id", val)}
                    departments={departments}
                    error={errors.department_id}
                />

                <div className="flex justify-end gap-3 mt-8">
                    <button type="button" onClick={onClose} disabled={processing}
                            className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition disabled:opacity-50">
                        Cancel
                    </button>
                    <button type="submit" disabled={processing}
                            className="px-4 py-2 bg-primary font-semibold text-white rounded-lg hover:bg-primary/90 transition disabled:opacity-50 flex items-center gap-2">
                        {processing && <RefreshCw size={16} className="animate-spin" />}
                        Send Invitation
                    </button>
                </div>
            </form>
        </div>
    );
}
