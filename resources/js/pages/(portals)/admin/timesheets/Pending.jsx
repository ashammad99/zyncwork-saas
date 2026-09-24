import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import AdminLayout from "../layout";
import {
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    ArrowLeft,
    Inbox,
    Clock,
} from "lucide-react";

/**
 * @param {object} props
 * @param {Array<{ user: {id, name, department}, total_minutes: number, entry_count: number, logs: Array }>} props.groups
 */
export default function AdminTimeLogPending({ groups = [] }) {
    const totalPending = groups.reduce((sum, g) => sum + g.total_minutes, 0);
    const totalEntries = groups.reduce((sum, g) => sum + g.entry_count, 0);

    return (
        <AdminLayout>
            <Head title="Approval Queue" />

            <div className="max-w-5xl mx-auto space-y-6 py-6">

                {/* Header */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <Link
                            href="/admin/timesheets"
                            className="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 transition mb-2"
                        >
                            <ArrowLeft size={14} />
                            All Time Logs
                        </Link>
                        <h1 className="text-2xl font-bold text-gray-900">Approval Queue</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {totalEntries} pending entr{totalEntries === 1 ? "y" : "ies"} ·{" "}
                            {formatMinutes(totalPending)} awaiting approval
                        </p>
                    </div>
                </div>

                {/* Empty state */}
                {groups.length === 0 && (
                    <div className="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center">
                        <div className="w-12 h-12 mx-auto mb-4 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500">
                            <Inbox size={24} />
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-1">All caught up</h3>
                        <p className="text-sm text-gray-500">
                            No time entries are waiting for approval.
                        </p>
                    </div>
                )}

                {/* Groups */}
                {groups.map((group) => (
                    <UserGroup key={group.user.id} group={group} />
                ))}
            </div>
        </AdminLayout>
    );
}

/* ─── One employee's group ───────────────────────────────── */

function UserGroup({ group }) {
    const [expanded, setExpanded] = useState(true);
    const { user, total_minutes, entry_count, logs } = group;

    const approveAll = () => {
        const ids = logs.map((l) => l.id);
        if (!confirm(`Approve all ${entry_count} entries for ${user.name}? Total: ${formatMinutes(total_minutes)}`)) return;

        router.post("/admin/timesheets/bulk-approve", { time_log_ids: ids }, {
            preserveScroll: true,
        });
    };

    const approveOne = (log) => {
        router.patch(`/tasks/${log.task_id}/time-logs/${log.id}/approve`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            {/* Group header */}
            <div className="px-6 py-4 flex items-center justify-between gap-4 bg-gray-50/50">
                <button
                    onClick={() => setExpanded((v) => !v)}
                    className="flex items-center gap-3 min-w-0 text-left"
                >
                    <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-sm shrink-0">
                        {user.name?.charAt(0)}
                    </div>
                    <div className="min-w-0">
                        <div className="font-semibold text-gray-900 truncate">{user.name}</div>
                        <div className="text-xs text-gray-500">
                            {user.department ?? "No department"} · {entry_count} entr{entry_count === 1 ? "y" : "ies"} ·{" "}
                            <span className="font-semibold text-gray-700">{formatMinutes(total_minutes)}</span>
                        </div>
                    </div>
                    {expanded ? (
                        <ChevronUp size={16} className="text-gray-400 shrink-0" />
                    ) : (
                        <ChevronDown size={16} className="text-gray-400 shrink-0" />
                    )}
                </button>

                <button
                    onClick={approveAll}
                    className="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 transition shrink-0"
                >
                    <CheckCircle2 size={14} />
                    Approve All
                </button>
            </div>

            {/* Entries */}
            {expanded && (
                <ul className="divide-y divide-gray-100">
                    {logs.map((log) => (
                        <li key={log.id} className="px-6 py-3 flex items-center gap-4 hover:bg-gray-50 transition">
                            <div
                                className={`w-1 h-10 rounded-full shrink-0 ${
                                    log.entry_type === "timer" ? "bg-blue-400" : "bg-purple-400"
                                }`}
                            />
                            <div className="flex-1 min-w-0">
                                <div className="text-sm font-medium text-gray-900 truncate">
                                    {log.task?.title ?? "—"}
                                </div>
                                <div className="text-xs text-gray-500 flex items-center gap-2">
                                    <span>{log.task?.project?.name ?? "—"}</span>
                                    <span>·</span>
                                    <span className="inline-flex items-center gap-1">
                                        <Clock size={10} />
                                        {formatDateTime(log.started_at)}
                                    </span>
                                    <span className={`rounded px-1.5 py-0.5 text-[10px] font-medium ${
                                        log.entry_type === "timer"
                                            ? "bg-blue-50 text-blue-700"
                                            : "bg-purple-50 text-purple-700"
                                    }`}>
                                        {log.entry_type}
                                    </span>
                                </div>
                                {log.notes && (
                                    <div className="text-xs text-gray-500 italic truncate mt-0.5">
                                        "{log.notes}"
                                    </div>
                                )}
                            </div>
                            <div className="text-sm font-semibold text-gray-900 shrink-0">
                                {formatMinutes(log.duration_minutes)}
                            </div>
                            <button
                                onClick={() => approveOne(log)}
                                title="Approve"
                                className="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition shrink-0"
                            >
                                <CheckCircle2 size={16} />
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

/* ─── Helpers ────────────────────────────────────────────── */

function formatMinutes(min) {
    if (!min || min <= 0) return "0m";
    const h = Math.floor(min / 60);
    const m = Math.round(min % 60);
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}

function formatDateTime(iso) {
    return new Date(iso).toLocaleString(undefined, {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}
