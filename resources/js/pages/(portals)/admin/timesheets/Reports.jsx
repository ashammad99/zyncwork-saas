import React from "react";
import { Head, Link, router } from "@inertiajs/react";
import AdminLayout from "../layout";
import {
    ArrowLeft,
    Clock,
    CheckCircle2,
    Hourglass,
    Timer,
    PencilLine,
    Users,
    FolderKanban,
    Building2,
} from "lucide-react";

/**
 * @param {object} props
 * @param {string} props.period - 'week' | 'month'
 * @param {object} props.reports
 */
export default function AdminTimeLogReports({ period = "week", reports = {} }) {
    const { range = {}, totals = {}, by_user = [], by_project = [], by_department = [] } = reports;

    const setPeriod = (p) => {
        router.get("/admin/timesheets/reports", { period: p }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Time Reports" />

            <div className="max-w-7xl mx-auto space-y-6 py-6">

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
                        <h1 className="text-2xl font-bold text-gray-900">Time Reports</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            {range.from} → {range.to}
                        </p>
                    </div>

                    {/* Period toggle */}
                    <div className="inline-flex rounded-lg border border-gray-200 overflow-hidden">
                        <button
                            onClick={() => setPeriod("week")}
                            className={`px-4 py-2 text-sm font-medium transition ${
                                period === "week"
                                    ? "bg-primary text-white"
                                    : "bg-white text-gray-600 hover:bg-gray-50"
                            }`}
                        >
                            This Week
                        </button>
                        <button
                            onClick={() => setPeriod("month")}
                            className={`px-4 py-2 text-sm font-medium transition ${
                                period === "month"
                                    ? "bg-primary text-white"
                                    : "bg-white text-gray-600 hover:bg-gray-50"
                            }`}
                        >
                            This Month
                        </button>
                    </div>
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <SummaryCard
                        icon={<Clock size={18} />}
                        label="Total Logged"
                        value={formatMinutes(totals.total_minutes)}
                        color="text-gray-700 bg-gray-50 border-gray-100"
                    />
                    <SummaryCard
                        icon={<CheckCircle2 size={18} />}
                        label="Approved"
                        value={formatMinutes(totals.approved_minutes)}
                        sub={pctOf(totals.approved_minutes, totals.total_minutes)}
                        color="text-emerald-700 bg-emerald-50 border-emerald-100"
                    />
                    <SummaryCard
                        icon={<Hourglass size={18} />}
                        label="Pending"
                        value={formatMinutes(totals.pending_minutes)}
                        sub={pctOf(totals.pending_minutes, totals.total_minutes)}
                        color="text-amber-700 bg-amber-50 border-amber-100"
                    />
                    <SummaryCard
                        icon={<Timer size={18} />}
                        label="Timer Entries"
                        value={totals.timer_count ?? 0}
                        color="text-blue-700 bg-blue-50 border-blue-100"
                    />
                    <SummaryCard
                        icon={<PencilLine size={18} />}
                        label="Manual Entries"
                        value={totals.manual_count ?? 0}
                        color="text-purple-700 bg-purple-50 border-purple-100"
                    />
                </div>

                {/* Aggregate tables */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <AggregateTable
                        icon={<Users size={16} />}
                        title="By Employee"
                        rows={by_user}
                        totalMinutes={totals.total_minutes}
                        secondaryKey="department"
                    />
                    <AggregateTable
                        icon={<FolderKanban size={16} />}
                        title="By Project"
                        rows={by_project}
                        totalMinutes={totals.total_minutes}
                    />
                    <AggregateTable
                        icon={<Building2 size={16} />}
                        title="By Department"
                        rows={by_department}
                        totalMinutes={totals.total_minutes}
                    />
                </div>
            </div>
        </AdminLayout>
    );
}

/* ─── Summary card ───────────────────────────────────────── */

function SummaryCard({ icon, label, value, sub, color }) {
    return (
        <div className={`rounded-2xl border p-4 ${color}`}>
            <div className="flex items-center gap-2 mb-2 opacity-80">
                {icon}
                <span className="text-[10px] font-bold uppercase tracking-wider">{label}</span>
            </div>
            <div className="text-2xl font-bold">{value}</div>
            {sub && <div className="text-xs opacity-70 mt-0.5">{sub}</div>}
        </div>
    );
}

/* ─── Aggregate table with proportion bars ───────────────── */

function AggregateTable({ icon, title, rows = [], totalMinutes = 0, secondaryKey }) {
    const max = rows.length > 0 ? Math.max(...rows.map((r) => r.total_minutes)) : 0;

    return (
        <div className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div className="px-6 py-4 flex items-center gap-2 border-b border-gray-100">
                <span className="text-gray-400">{icon}</span>
                <h3 className="text-xs font-bold uppercase tracking-wider text-gray-500">{title}</h3>
            </div>

            {rows.length === 0 ? (
                <div className="px-6 py-8 text-center text-sm text-gray-400">
                    No data for this period.
                </div>
            ) : (
                <ul className="divide-y divide-gray-50">
                    {rows.map((row, i) => {
                        const barPct = max > 0 ? (row.total_minutes / max) * 100 : 0;
                        const sharePct = totalMinutes > 0
                            ? Math.round((row.total_minutes / totalMinutes) * 100)
                            : 0;

                        return (
                            <li key={i} className="px-6 py-3">
                                <div className="flex items-center justify-between gap-4 mb-1.5">
                                    <div className="min-w-0">
                                        <span className="text-sm font-medium text-gray-900 truncate block">
                                            {row.name ?? "—"}
                                        </span>
                                        {secondaryKey && row[secondaryKey] && (
                                            <span className="text-xs text-gray-500">
                                                {row[secondaryKey]}
                                            </span>
                                        )}
                                    </div>
                                    <div className="text-right shrink-0">
                                        <span className="text-sm font-semibold text-gray-900">
                                            {formatMinutes(row.total_minutes)}
                                        </span>
                                        <span className="text-xs text-gray-400 ml-2">
                                            {sharePct}%
                                        </span>
                                    </div>
                                </div>
                                <div className="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        className="h-full bg-primary/70 rounded-full transition-all duration-500"
                                        style={{ width: `${barPct}%` }}
                                    />
                                </div>
                            </li>
                        );
                    })}
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

function pctOf(part, whole) {
    if (!whole || whole <= 0) return null;
    return `${Math.round((part / whole) * 100)}% of total`;
}
