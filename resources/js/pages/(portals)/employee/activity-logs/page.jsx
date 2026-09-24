import React, { useState } from "react";
import EmployeeLayout from "../layout";
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
    Globe,
    Search,
    Clock,
} from "lucide-react";

const ACTION_META = {
    login: {
        label: "Login",
        icon: LogIn,
        color: "bg-emerald-100 text-emerald-700",
    },
    register: {
        label: "Register",
        icon: UserPlus,
        color: "bg-blue-100 text-blue-700",
    },
    profile_updated: {
        label: "Profile Updated",
        icon: User,
        color: "bg-violet-100 text-violet-700",
    },
    password_changed: {
        label: "Password Changed",
        icon: Key,
        color: "bg-amber-100 text-amber-700",
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
    };

    const act = action.toLowerCase();
    if (act.includes("create") || act.includes("add") || act.includes("store")) {
        meta.color = "bg-green-100 text-green-700";
    } else if (act.includes("update") || act.includes("edit")) {
        meta.color = "bg-blue-100 text-blue-700";
    } else if (act.includes("delete") || act.includes("remove") || act.includes("destroy")) {
        meta.color = "bg-red-100 text-red-700";
    } else if (act.includes("login") || act.includes("auth")) {
        meta.color = "bg-emerald-100 text-emerald-700";
    } else if (act.includes("fail") || act.includes("error")) {
        meta.color = "bg-red-100 text-red-700";
    } else if (act.includes("password") || act.includes("security")) {
        meta.color = "bg-amber-100 text-amber-700";
    } else if (act.includes("setting") || act.includes("config")) {
        meta.color = "bg-purple-100 text-purple-700";
    } else if (act.includes("view") || act.includes("read") || act.includes("download") || act.includes("export")) {
        meta.color = "bg-indigo-100 text-indigo-700";
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
                      (l.description ?? "")
                          .toLowerCase()
                          .includes(search.toLowerCase()),
              )
            : logs?.data ?? [];

    return (
        <EmployeeLayout>
            <Head title="My Activity" />

            <div className="py-8 max-w-4xl mx-auto w-full">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded-xl bg-primary/10 flex items-center justify-center">
                            <History size={20} className="text-primary" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">
                                My Activity
                            </h1>
                            <p className="text-sm text-gray-500">
                                A log of all actions on your account
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-100 rounded-xl shadow-sm text-sm text-gray-600">
                        <Clock size={14} className="text-primary" />
                        <span>
                            <strong className="text-gray-900">
                                {logs?.total ?? 0}
                            </strong>{" "}
                            events recorded
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
                        id="user-activity-log-search"
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search by action or description…"
                        className="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition"
                    />
                </div>

                {/* Timeline */}
                <div className="space-y-3">
                    {filtered.length > 0 ? (
                        filtered.map((log) => {
                            const meta = getActionMeta(log.action);
                            const Icon = meta.icon;
                            return (
                                <div
                                    key={log.id}
                                    className="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-start gap-4 hover:border-primary/20 hover:shadow-md transition-all duration-200"
                                >
                                    {/* Icon */}
                                    <div
                                        className={`h-9 w-9 rounded-xl flex items-center justify-center shrink-0 ${meta.color}`}
                                    >
                                        <Icon size={16} strokeWidth={2.5} />
                                    </div>

                                    {/* Content */}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <ActionBadge action={log.action} />
                                        </div>
                                        {log.description && (
                                            <p className="mt-1 text-sm text-gray-600">
                                                {log.description}
                                            </p>
                                        )}
                                        {log.ip_address && (
                                            <p className="mt-1 inline-flex items-center gap-1 text-[11px] text-gray-400 font-mono">
                                                <Globe size={10} />
                                                {log.ip_address}
                                            </p>
                                        )}
                                    </div>

                                    {/* Time */}
                                    <div className="text-right shrink-0">
                                        <p className="text-xs font-medium text-gray-700">
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleDateString(undefined, {
                                                month: "short",
                                                day: "numeric",
                                                year: "numeric",
                                            })}
                                        </p>
                                        <p className="text-[11px] text-gray-400 mt-0.5">
                                            {new Date(
                                                log.created_at,
                                            ).toLocaleTimeString(undefined, {
                                                hour: "2-digit",
                                                minute: "2-digit",
                                            })}
                                        </p>
                                    </div>
                                </div>
                            );
                        })
                    ) : (
                        <div className="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
                            <History
                                size={32}
                                className="mx-auto mb-3 text-gray-200"
                            />
                            <p className="text-gray-400 font-medium">
                                No activity yet
                            </p>
                            <p className="text-gray-300 text-xs mt-1">
                                Your account actions will be shown here
                            </p>
                        </div>
                    )}
                </div>

                {/* Pagination */}
                {logs?.last_page > 1 && (
                    <div className="mt-6 flex items-center justify-between">
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
        </EmployeeLayout>
    );
}
