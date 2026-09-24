import React, {useState, useMemo} from "react";
import {Head, Link, router} from "@inertiajs/react";
import AdminLayout from "../layout";
import {
    Clock,
    CheckCircle2,
    RotateCcw,
    Search,
    X,
    ListChecks,
    BarChart3,
    Download,
    FileText
} from "lucide-react";

const APPROVAL_OPTIONS = [
    {value: "", label: "All statuses"},
    {value: "pending", label: "Pending"},
    {value: "approved", label: "Approved"},
    {value: "running", label: "Running now"},
];

/**
 * @param {object} props
 * @param {{ data: Array<object>, links: Array<object>, total: number }} props.logs
 * @param {object} props.filters
 * @param {{ users: Array, projects: Array, departments: Array, entry_types: Array }} props.options
 */
export default function AdminTimeLogIndex({logs, filters = {}, options = {}}) {
    const [selected, setSelected] = useState([]);

    const rows = logs?.data ?? [];

    // Only pending + finished rows are selectable for bulk approve
    const selectableIds = useMemo(
        () => rows.filter((l) => !l.approved_by && l.ended_at).map((l) => l.id),
        [rows]
    );

    const selectedMinutes = useMemo(
        () =>
            rows
                .filter((l) => selected.includes(l.id))
                .reduce((sum, l) => sum + Number(l.duration_minutes || 0), 0),
        [rows, selected]
    );

    const allSelected = selectableIds.length > 0 && selected.length === selectableIds.length;

    const toggleAll = () => setSelected(allSelected ? [] : selectableIds);

    const toggleOne = (id) =>
        setSelected((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
        );

    // ── Actions ────────────────────────────────────────────────────────────
    const applyFilter = (key, value) => {
        const next = {...filters, [key]: value};
        const clean = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== "" && v !== null && v !== undefined)
        );
        router.get("/admin/timesheets", clean, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
        setSelected([]);
    };

    const clearFilters = () => {
        router.get("/admin/timesheets", {}, {preserveScroll: true, replace: true});
        setSelected([]);
    };

    const bulkApprove = () => {
        if (selected.length === 0) return;
        if (!confirm(`Approve ${selected.length} time entries? Total: ${formatMinutes(selectedMinutes)}`)) return;

        router.post("/admin/timesheets/bulk-approve", {time_log_ids: selected}, {
            preserveScroll: true,
            onSuccess: () => setSelected([]),
        });
    };

    const approveOne = (log) => {
        router.patch(`/tasks/${log.task_id}/time-logs/${log.id}/approve`, {}, {
            preserveScroll: true,
        });
    };

    const revokeOne = (log) => {
        if (!confirm("Revoke this approval? The employee will be able to edit or delete it again.")) return;
        router.patch(`/tasks/${log.task_id}/time-logs/${log.id}/reject`, {}, {
            preserveScroll: true,
        });
    };

    const hasActiveFilters = Object.values(filters).some((v) => v !== "" && v != null);

    return (
        <AdminLayout>
            <Head title="Time Logs"/>

            <div className="max-w-7xl mx-auto space-y-6 py-6">

                {/* Header + view switcher */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Time Logs</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Every time entry across the organization.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link
                            href="/admin/timesheets/pending"
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                        >
                            <ListChecks size={14}/>
                            Approval Queue
                        </Link>
                        <Link
                            href="/admin/timesheets/reports"
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                        >
                            <BarChart3 size={14}/>
                            Reports
                        </Link>
                        <a
                            href={`/admin/timesheets/export?${new URLSearchParams(
                                Object.fromEntries(
                                    Object.entries(filters).filter(([, v]) => v !== "" && v != null)
                                )
                            ).toString()}`}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                        >
                            <Download size={14}/>
                            Export CSV
                        </a>
                        {filters.date_from && filters.date_to ? (
<a
                                href={"/admin/timesheets/export-pdf?" + new URLSearchParams(Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== "" && v != null))).toString()}
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                                >
                                <FileText size={14} />
                            Export PDF
                            </a>
                            ) : (
                            <button
                            type="button"
                            disabled
                            title="Set a From and To date first"
                            className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg opacity-50 cursor-not-allowed"
                            >
                            <FileText size={14} />
                    Export PDF
                </button>
                )}
                    </div>
                </div>

                {/* Filters */}
                <div className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                        <FilterSelect
                            label="Employee"
                            value={filters.user_id ?? ""}
                            onChange={(v) => applyFilter("user_id", v)}
                            options={[
                                {value: "", label: "All"},
                                ...(options.users ?? []).map((u) => ({value: u.id, label: u.name})),
                            ]}
                        />
                        <FilterSelect
                            label="Project"
                            value={filters.project_id ?? ""}
                            onChange={(v) => applyFilter("project_id", v)}
                            options={[
                                {value: "", label: "All"},
                                ...(options.projects ?? []).map((p) => ({value: p.id, label: p.name})),
                            ]}
                        />
                        <FilterSelect
                            label="Department"
                            value={filters.department_id ?? ""}
                            onChange={(v) => applyFilter("department_id", v)}
                            options={[
                                {value: "", label: "All"},
                                ...(options.departments ?? []).map((d) => ({value: d.id, label: d.name})),
                            ]}
                        />
                        <FilterSelect
                            label="Type"
                            value={filters.entry_type ?? ""}
                            onChange={(v) => applyFilter("entry_type", v)}
                            options={[
                                {value: "", label: "All"},
                                ...(options.entry_types ?? []).map((t) => ({
                                    value: t,
                                    label: t.charAt(0).toUpperCase() + t.slice(1),
                                })),
                            ]}
                        />
                        <FilterSelect
                            label="Status"
                            value={filters.approval ?? ""}
                            onChange={(v) => applyFilter("approval", v)}
                            options={APPROVAL_OPTIONS}
                        />
                        <FilterDate
                            label="From"
                            value={filters.date_from ?? ""}
                            onChange={(v) => applyFilter("date_from", v)}
                        />
                        <FilterDate
                            label="To"
                            value={filters.date_to ?? ""}
                            onChange={(v) => applyFilter("date_to", v)}
                        />
                    </div>

                    {hasActiveFilters && (
                        <button
                            onClick={clearFilters}
                            className="mt-3 flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700 transition"
                        >
                            <X size={12}/> Clear filters
                        </button>
                    )}
                </div>

                {/* Bulk action bar */}
                {selected.length > 0 && (
                    <div
                        className="sticky top-2 z-10 flex items-center justify-between gap-4 bg-primary text-white rounded-xl px-5 py-3 shadow-lg">
                        <div className="text-sm font-medium">
                            {selected.length} selected · {formatMinutes(selectedMinutes)}
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={() => setSelected([])}
                                className="px-3 py-1.5 text-xs font-medium bg-white/10 rounded-lg hover:bg-white/20 transition"
                            >
                                Clear
                            </button>
                            <button
                                onClick={bulkApprove}
                                className="inline-flex items-center gap-1.5 px-4 py-1.5 text-xs font-bold bg-white text-primary rounded-lg hover:bg-gray-100 transition"
                            >
                                <CheckCircle2 size={14}/>
                                Approve Selected
                            </button>
                        </div>
                    </div>
                )}

                {/* Table */}
                <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 text-xs text-gray-600 uppercase tracking-wider">
                        <tr>
                            <th className="px-4 py-3 w-10">
                                <input
                                    type="checkbox"
                                    checked={allSelected}
                                    onChange={toggleAll}
                                    disabled={selectableIds.length === 0}
                                    className="rounded border-gray-300"
                                />
                            </th>
                            <th className="text-left px-4 py-3 font-semibold">Employee</th>
                            <th className="text-left px-4 py-3 font-semibold">Task / Project</th>
                            <th className="text-left px-4 py-3 font-semibold">When</th>
                            <th className="text-left px-4 py-3 font-semibold">Duration</th>
                            <th className="text-left px-4 py-3 font-semibold">Type</th>
                            <th className="text-left px-4 py-3 font-semibold">Status</th>
                            <th className="text-right px-4 py-3 font-semibold w-24">Actions</th>
                        </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-12 text-center text-gray-400">
                                    No time logs match your filters.
                                </td>
                            </tr>
                        )}
                        {rows.map((log) => {
                            const isRunning = !log.ended_at;
                            const isApproved = !!log.approved_by;
                            const selectable = !isApproved && !isRunning;

                            return (
                                <tr key={log.id} className="hover:bg-gray-50 transition">
                                    <td className="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            checked={selected.includes(log.id)}
                                            onChange={() => toggleOne(log.id)}
                                            disabled={!selectable}
                                            className="rounded border-gray-300 disabled:opacity-30"
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-gray-900">{log.user?.name}</div>
                                        <div className="text-xs text-gray-500">{log.user?.department?.name ?? "—"}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div
                                            className="text-gray-900 truncate max-w-[220px]">{log.task?.title ?? "—"}</div>
                                        <div className="text-xs text-gray-500">{log.task?.project?.name ?? "—"}</div>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 whitespace-nowrap">
                                        {formatDateTime(log.started_at)}
                                    </td>
                                    <td className="px-4 py-3 font-semibold text-gray-900">
                                        {isRunning ? (
                                            <span className="text-emerald-600 text-xs font-medium">running…</span>
                                        ) : (
                                            formatMinutes(log.duration_minutes)
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                            <span className={`rounded px-2 py-0.5 text-xs font-medium ${
                                                log.entry_type === "timer"
                                                    ? "bg-blue-50 text-blue-700"
                                                    : "bg-purple-50 text-purple-700"
                                            }`}>
                                                {log.entry_type}
                                            </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        {isApproved ? (
                                            <span
                                                className="inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700">
                                                    <CheckCircle2 size={11}/>
                                                    Approved
                                                </span>
                                        ) : isRunning ? (
                                            <span className="text-xs text-gray-400">—</span>
                                        ) : (
                                            <span
                                                className="rounded px-2 py-0.5 text-xs font-medium bg-amber-50 text-amber-700">
                                                    Pending
                                                </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right whitespace-nowrap">
                                        {selectable && (
                                            <button
                                                onClick={() => approveOne(log)}
                                                title="Approve"
                                                className="p-1.5 text-emerald-600 hover:bg-emerald-50 rounded-lg transition"
                                            >
                                                <CheckCircle2 size={16}/>
                                            </button>
                                        )}
                                        {isApproved && (
                                            <button
                                                onClick={() => revokeOne(log)}
                                                title="Revoke approval"
                                                className="p-1.5 text-orange-500 hover:bg-orange-50 rounded-lg transition"
                                            >
                                                <RotateCcw size={16}/>
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {logs?.links && logs.links.length > 3 && (
                    <div className="flex justify-center gap-1 flex-wrap">
                        {logs.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url ?? "#"}
                                preserveScroll
                                preserveState
                                dangerouslySetInnerHTML={{__html: link.label}}
                                className={`min-w-[36px] px-3 py-1.5 text-sm rounded-lg text-center transition ${
                                    link.active
                                        ? "bg-primary text-white font-semibold"
                                        : "border border-gray-200 text-gray-700 hover:bg-gray-50"
                                } ${!link.url && "opacity-40 pointer-events-none"}`}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

/* ─── Filter controls ────────────────────────────────────── */

function FilterSelect({label, value, onChange, options}) {
    return (
        <div>
            <label className="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">
                {label}
            </label>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
            >
                {options.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                        {opt.label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function FilterDate({label, value, onChange}) {
    return (
        <div>
            <label className="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">
                {label}
            </label>
            <input
                type="date"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="w-full px-2 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
            />
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
