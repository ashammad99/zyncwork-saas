import React, { useState } from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, Link, usePage } from "@inertiajs/react";
import {
    History,
    LogIn,
    UserPlus,
    User,
    Key,
    Activity,
    ChevronLeft,
    ChevronRight,
    Search,
    Shield,
    Globe,
} from "lucide-react";

const ACTION_META = {
    login: {
        label: "Login",
        icon: LogIn,
        color: "bg-emerald-100 text-emerald-700",
        dot: "bg-emerald-500",
    },
    register: {
        label: "Register",
        icon: UserPlus,
        color: "bg-blue-100 text-blue-700",
        dot: "bg-blue-500",
    },
    profile_updated: {
        label: "Profile Updated",
        icon: User,
        color: "bg-violet-100 text-violet-700",
        dot: "bg-violet-500",
    },
    password_changed: {
        label: "Password Changed",
        icon: Key,
        color: "bg-amber-100 text-amber-700",
        dot: "bg-amber-500",
    },
    password_reset: {
        label: "Password Reset",
        icon: Key,
        color: "bg-amber-100 text-amber-700",
        dot: "bg-amber-500",
    },
};

function getActionMeta(action) {
    if (ACTION_META[action]) return ACTION_META[action];

    let meta = {
        label: action
            .replace(/_/g, " ")
            .replace(/\b\w/g, (c) => c.toUpperCase()),
        icon: Activity,
        color: "bg-gray-100 text-gray-700",
        dot: "bg-gray-400",
    };

    const act = action.toLowerCase();
    if (act.includes("create") || act.includes("add") || act.includes("store")) {
        meta.color = "bg-green-100 text-green-700";
        meta.dot = "bg-green-500";
    } else if (act.includes("update") || act.includes("edit")) {
        meta.color = "bg-blue-100 text-blue-700";
        meta.dot = "bg-blue-500";
    } else if (act.includes("delete") || act.includes("remove") || act.includes("destroy")) {
        meta.color = "bg-red-100 text-red-700";
        meta.dot = "bg-red-500";
    } else if (act.includes("login") || act.includes("auth")) {
        meta.color = "bg-emerald-100 text-emerald-700";
        meta.dot = "bg-emerald-500";
    } else if (act.includes("fail") || act.includes("error")) {
        meta.color = "bg-red-100 text-red-700";
        meta.dot = "bg-red-500";
    } else if (act.includes("password") || act.includes("security")) {
        meta.color = "bg-amber-100 text-amber-700";
        meta.dot = "bg-amber-500";
    } else if (act.includes("setting") || act.includes("config")) {
        meta.color = "bg-purple-100 text-purple-700";
        meta.dot = "bg-purple-500";
    } else if (act.includes("view") || act.includes("read") || act.includes("download") || act.includes("export")) {
        meta.color = "bg-indigo-100 text-indigo-700";
        meta.dot = "bg-indigo-500";
    }

    return meta;
}

function ActionBadge({ action }) {
    const meta = getActionMeta(action);
    const Icon = meta.icon;
    return (
        <span
            className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ${meta.color}`}
        >
            <Icon size={11} strokeWidth={2.5} />
            {meta.label}
        </span>
    );
}

export default function Page() {
    const { props } = usePage();
    const logs = props.logs;
    const [search, setSearch] = useState("");

    const filtered =
        search.trim().length > 0
            ? (logs?.data ?? []).filter(
                  (l) =>
                      l.action.includes(search.toLowerCase()) ||
                      (l.user?.name ?? "")
                          .toLowerCase()
                          .includes(search.toLowerCase()) ||
                      (l.description ?? "")
                          .toLowerCase()
                          .includes(search.toLowerCase()),
              )
            : (logs?.data ?? []);

    return (
        <AdminLayout>
            <Head title="Activity Logs" />

            <div className="py-8 max-w-7xl mx-auto w-full">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center">
                            <History size={20} className="text-primary" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">
                                Activity Logs
                            </h1>
                            <p className="text-sm text-gray-500">
                                All user activity across the platform
                            </p>
                        </div>
                    </div>

                    {/* Stats */}
                    <div className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-100 rounded-xl shadow-sm text-sm text-gray-600">
                        <Shield size={14} className="text-primary" />
                        <span>
                            <strong className="text-gray-900">
                                {logs?.total ?? 0}
                            </strong>{" "}
                            total events
                        </span>
                    </div>
                </div>

                {/* Search */}
                <div className="relative mb-6">
                    <Search
                        size={16}
                        className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        id="activity-log-search"
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by user, action, or description…"
                        className="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                    />
                </div>

                {/* Table */}
                <div className="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-gray-100 bg-gray-50/60">
                                    <th className="px-5 py-3.5 text-left font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                        User
                                    </th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                        Action
                                    </th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-gray-500 text-xs uppercase tracking-wide hidden md:table-cell">
                                        Description
                                    </th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-gray-500 text-xs uppercase tracking-wide hidden lg:table-cell">
                                        IP Address
                                    </th>
                                    <th className="px-5 py-3.5 text-left font-semibold text-gray-500 text-xs uppercase tracking-wide">
                                        Time
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-50">
                                {filtered.length > 0 ? (
                                    filtered.map((log) => {
                                        const meta = getActionMeta(log.action);
                                        return (
                                            <tr
                                                key={log.id}
                                                className="hover:bg-gray-50/50 transition-colors group"
                                            >
                                                {/* User */}
                                                <td className="px-5 py-3.5">
                                                    <div className="flex items-center gap-2.5">
                                                        <div
                                                            className={`h-7 w-7 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0`}
                                                            style={{
                                                                background:
                                                                    "linear-gradient(135deg,#6366f1,#8b5cf6)",
                                                            }}
                                                        >
                                                            {(log.user?.name ??
                                                                "?")[0].toUpperCase()}
                                                        </div>
                                                        <div>
                                                            <p className="font-semibold text-gray-900 leading-tight">
                                                                {log.user
                                                                    ?.name ??
                                                                    "Deleted User"}
                                                            </p>
                                                            <p className="text-[11px] text-gray-400 leading-tight">
                                                                {log.user
                                                                    ?.email ??
                                                                    "—"}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </td>

                                                {/* Action */}
                                                <td className="px-5 py-3.5">
                                                    <ActionBadge
                                                        action={log.action}
                                                    />
                                                </td>

                                                {/* Description */}
                                                <td className="px-5 py-3.5 hidden md:table-cell text-gray-600 max-w-xs truncate">
                                                    {log.description || "—"}
                                                </td>

                                                {/* IP */}
                                                <td className="px-5 py-3.5 hidden lg:table-cell">
                                                    {log.ip_address ? (
                                                        <span className="inline-flex items-center gap-1 text-gray-500 font-mono text-xs">
                                                            <Globe
                                                                size={11}
                                                                className="text-gray-400"
                                                            />
                                                            {log.ip_address}
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-300">
                                                            —
                                                        </span>
                                                    )}
                                                </td>

                                                {/* Time */}
                                                <td className="px-5 py-3.5 text-gray-500 text-xs whitespace-nowrap">
                                                    <div>
                                                        {new Date(
                                                            log.created_at,
                                                        ).toLocaleDateString(
                                                            undefined,
                                                            {
                                                                month: "short",
                                                                day: "numeric",
                                                                year: "numeric",
                                                            },
                                                        )}
                                                    </div>
                                                    <div className="text-gray-400">
                                                        {new Date(
                                                            log.created_at,
                                                        ).toLocaleTimeString(
                                                            undefined,
                                                            {
                                                                hour: "2-digit",
                                                                minute: "2-digit",
                                                            },
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-5 py-16 text-center"
                                        >
                                            <History
                                                size={32}
                                                className="mx-auto mb-3 text-gray-200"
                                            />
                                            <p className="text-gray-400 font-medium">
                                                No activity logs found
                                            </p>
                                            <p className="text-gray-300 text-xs mt-1">
                                                Logs will appear as users
                                                interact with the platform
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {logs?.last_page > 1 && (
                        <div className="px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                            <p className="text-xs text-gray-500">
                                Showing <strong>{logs.from ?? 0}</strong>–
                                <strong>{logs.to ?? 0}</strong> of{" "}
                                <strong>{logs.total}</strong>
                            </p>
                            <div className="flex items-center gap-1">
                                {logs.links?.map((link, i) => {
                                    if (
                                        link.label === "&laquo; Previous" ||
                                        link.label === "Next &raquo;"
                                    ) {
                                        return (
                                            <Link
                                                key={i}
                                                href={link.url ?? "#"}
                                                className={`p-1.5 rounded-lg transition-colors ${link.url ? "text-gray-600 hover:bg-gray-100" : "text-gray-300 cursor-not-allowed pointer-events-none"}`}
                                            >
                                                {link.label ===
                                                "&laquo; Previous" ? (
                                                    <ChevronLeft size={16} />
                                                ) : (
                                                    <ChevronRight size={16} />
                                                )}
                                            </Link>
                                        );
                                    }
                                    return (
                                        <Link
                                            key={i}
                                            href={link.url ?? "#"}
                                            className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${link.active ? "bg-primary text-white" : link.url ? "text-gray-600 hover:bg-gray-100" : "text-gray-300 cursor-not-allowed pointer-events-none"}`}
                                        >
                                            {link.label}
                                        </Link>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
