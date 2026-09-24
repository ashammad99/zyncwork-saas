import React, { useState } from "react";
import ManagerLayout from "../layout";
import {Head, Link, router} from "@inertiajs/react";
import {Clock, CheckCircle, XCircle, Search, Filter, ListChecks, BarChart3, Download, FileText} from "lucide-react";
import Pagination from "@/Components/ui/Pagination";

export default function TimesheetsPage({ time_logs, employees, filters }) {
    const [employeeFilter, setEmployeeFilter] = useState(filters?.user_id || "");
    const [typeFilter, setTypeFilter] = useState(filters?.entry_type || "");
    const [dateFrom, setDateFrom] = useState(filters?.date_from || "");
    const [dateTo, setDateTo] = useState(filters?.date_to || "");

    const handleFilter = (e) => {
        e.preventDefault();
        router.get(
            "/manager/timesheets",
            { user_id: employeeFilter, entry_type: typeFilter, date_from: dateFrom, date_to: dateTo },
            { preserveState: true, replace: true }
        );
    };

    const handleApprove = (id) => {
        router.post(`/manager/timesheets/${id}/approve`);
    };

    const handleReject = (id) => {
        router.post(`/manager/timesheets/${id}/reject`);
    };

    const formatDuration = (minutes) => {
        if (!minutes) return "-";
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return h > 0 ? `${h}h ${m}m` : `${m}m`;
    };

    return (
        <ManagerLayout>
            <Head title="Timesheets" />
            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            Timesheets
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Review and approve team time entries.
                        </p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <a
                        href={`/manager/timesheets/export?${new URLSearchParams(
                            Object.fromEntries(
                                Object.entries(filters).filter(([, v]) => v !== "" && v != null)
                            )
                        ).toString()}`}
                        className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-gray-200 rounded-lg hover:bg-gray-50 transition"
                    >
                        <Download size={14}/>
                        Export CSV
                    </a>
                        {dateFrom && dateTo ? (
<a
                                href={"/manager/timesheets/export-pdf?" + new URLSearchParams(Object.fromEntries(Object.entries({ user_id: employeeFilter, entry_type: typeFilter, date_from: dateFrom, date_to: dateTo }).filter(([, v]) => v !== "" && v != null))).toString()}
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
                {/* Filters */}
                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-4">
                    <form onSubmit={handleFilter} className="flex flex-wrap items-end gap-3">
                        <div className="flex-1 min-w-[160px]">
                            <label className="block text-xs font-semibold text-gray-600 mb-1">Employee</label>
                            <select
                                value={employeeFilter}
                                onChange={(e) => setEmployeeFilter(e.target.value)}
                                className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                            >
                                <option value="">All Employees</option>
                                {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                            </select>
                        </div>
                        <div className="flex-1 min-w-[140px]">
                            <label className="block text-xs font-semibold text-gray-600 mb-1">Entry Type</label>
                            <select
                                value={typeFilter}
                                onChange={(e) => setTypeFilter(e.target.value)}
                                className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                            >
                                <option value="">All Types</option>
                                <option value="timer">Timer</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                        <div className="flex-1 min-w-[140px]">
                            <label className="block text-xs font-semibold text-gray-600 mb-1">From</label>
                            <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm" />
                        </div>
                        <div className="flex-1 min-w-[140px]">
                            <label className="block text-xs font-semibold text-gray-600 mb-1">To</label>
                            <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm" />
                        </div>
                        <button type="submit" className="px-5 py-2 bg-primary text-white rounded-xl font-semibold text-sm hover:bg-primary/90 transition flex items-center gap-2 shrink-0">
                            <Filter size={16} />
                            Apply
                        </button>
                    </form>
                </div>

                {/* Table */}
                <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                                <tr>
                                    <th className="px-6 py-4">Employee</th>
                                    <th className="px-6 py-4">Task</th>
                                    <th className="px-6 py-4">Type</th>
                                    <th className="px-6 py-4">Started</th>
                                    <th className="px-6 py-4">Ended</th>
                                    <th className="px-6 py-4">Duration</th>
                                    <th className="px-6 py-4">Notes</th>
                                    <th className="px-6 py-4">Approved By</th>
                                    <th className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {time_logs.data.map((log) => (
                                    <tr key={log.id} className="hover:bg-gray-50/50 transition-colors">
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs shrink-0">
                                                    {log.user?.name?.charAt(0)}
                                                </div>
                                                <span className="font-semibold text-gray-900">{log.user?.name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-gray-600 max-w-[180px] truncate">{log.task?.title || "-"}</td>
                                        <td className="px-6 py-4">
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${log.entry_type === "timer" ? "bg-blue-100 text-blue-700" : "bg-gray-100 text-gray-700"}`}>
                                                {log.entry_type}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-gray-600 text-xs">
                                            {log.started_at ? new Date(log.started_at).toLocaleString() : "-"}
                                        </td>
                                        <td className="px-6 py-4 text-gray-600 text-xs">
                                            {log.ended_at ? new Date(log.ended_at).toLocaleString() : (
                                                <span className="inline-flex items-center gap-1 text-green-600 font-semibold">
                                                    <Clock size={12} className="animate-pulse" /> Running
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 font-semibold text-gray-900">{formatDuration(log.duration_minutes)}</td>
                                        <td className="px-6 py-4 text-gray-600 max-w-[160px] truncate">{log.notes || "-"}</td>
                                        <td className="px-6 py-4">
                                            {log.approved_by ? (
                                                <span className="px-2.5 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Approved</span>
                                            ) : (
                                                <span className="px-2.5 py-1 rounded-full bg-gray-100 text-gray-500 text-xs font-semibold">Pending</span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            {!log.approved_by && log.ended_at && (
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        onClick={() => handleApprove(log.id)}
                                                        className="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                                        title="Approve"
                                                    >
                                                        <CheckCircle size={16} />
                                                    </button>
                                                    <button
                                                        onClick={() => handleReject(log.id)}
                                                        className="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                                        title="Reject"
                                                    >
                                                        <XCircle size={16} />
                                                    </button>
                                                </div>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        {time_logs.data.length === 0 && (
                            <div className="py-12 text-center text-gray-500">No time logs found for the selected filters.</div>
                        )}
                    </div>
                </div>

                {time_logs.total > 0 && <Pagination links={time_logs.links} />}
            </div>
        </ManagerLayout>
    );
}
