import React, { useState } from "react";
import ManagerLayout from "../../layout";
import { Head, router } from "@inertiajs/react";
import { Download, FileText, Table, AlertCircle } from "lucide-react";

export default function ExportPage({ projects, employees }) {
    const [dateFrom, setDateFrom] = useState("");
    const [dateTo, setDateTo] = useState("");
    const [userId, setUserId] = useState("");
    const [projectId, setProjectId] = useState("");

    const dateRangeValid = dateFrom && dateTo;

    const handleExport = (type, format) => {
        if (!dateRangeValid) return;
        const params = new URLSearchParams({ date_from: dateFrom, date_to: dateTo });
        if (userId) params.append("user_id", userId);
        if (projectId) params.append("project_id", projectId);
        window.location.href = `/manager/reports/export/${type}/${format}?${params.toString()}`;
    };

    return (
        <ManagerLayout>
            <Head title="Export Reports" />
            <div className="max-w-5xl mx-auto space-y-6 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                        Export Reports
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Download time logs, task summaries, and utilization data.
                    </p>
                </div>

                {/* Filters Card */}
                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 space-y-4">
                    <h2 className="font-bold text-gray-900">Export Filters</h2>

                    {!dateRangeValid && (
                        <div className="flex items-center gap-2 text-amber-700 bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm">
                            <AlertCircle size={16} className="shrink-0" />
                            A date range is required for all exports.
                        </div>
                    )}

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">From Date *</label>
                            <input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">To Date *</label>
                            <input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">Employee</label>
                            <select
                                value={userId}
                                onChange={(e) => setUserId(e.target.value)}
                                className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                            >
                                <option value="">All Employees</option>
                                {employees.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-gray-600 mb-1">Project</label>
                            <select
                                value={projectId}
                                onChange={(e) => setProjectId(e.target.value)}
                                className="w-full px-3 py-2 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary/20 text-sm"
                            >
                                <option value="">All Projects</option>
                                {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                            </select>
                        </div>
                    </div>
                </div>

                {/* Export Options */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <ExportCard
                        title="Time Logs"
                        description="Employee, Task, Project, Date, Start, End, Duration, Type, Notes, Approval status."
                        icon={<Clock />}
                        color="bg-blue-50 text-blue-600 border-blue-100"
                        disabled={!dateRangeValid}
                        actions={[
                            { label: "CSV", icon: <Table size={14} />, onClick: () => handleExport("time-logs", "csv") },
                            { label: "PDF", icon: <FileText size={14} />, onClick: () => handleExport("time-logs", "pdf") },
                        ]}
                    />

                    <ExportCard
                        title="Project Tasks"
                        description="Task ID, Title, Assignee, Status, Priority, Estimated Hours, Due Date, Completed At."
                        icon={<CheckSquare />}
                        color="bg-green-50 text-green-600 border-green-100"
                        disabled={!dateRangeValid}
                        actions={[
                            { label: "CSV", icon: <Table size={14} />, onClick: () => handleExport("tasks", "csv") },
                        ]}
                    />

                    <ExportCard
                        title="Utilization Report"
                        description="Employee, Department, Assigned Hours, Logged Hours, Capacity, Utilization %."
                        icon={<BarChart />}
                        color="bg-purple-50 text-purple-600 border-purple-100"
                        disabled={!dateRangeValid}
                        actions={[
                            { label: "CSV", icon: <Table size={14} />, onClick: () => handleExport("utilization", "csv") },
                        ]}
                    />
                </div>
            </div>
        </ManagerLayout>
    );
}

function ExportCard({ title, description, icon, color, actions, disabled }) {
    return (
        <div className={`bg-white border rounded-2xl shadow-sm p-6 flex flex-col gap-4 ${disabled ? "opacity-60" : ""}`}>
            <div className={`w-12 h-12 rounded-xl border flex items-center justify-center ${color}`}>
                {icon}
            </div>
            <div>
                <h3 className="font-bold text-gray-900 mb-1">{title}</h3>
                <p className="text-xs text-gray-500 leading-relaxed">{description}</p>
            </div>
            <div className="flex gap-2 mt-auto">
                {actions.map((action) => (
                    <button
                        key={action.label}
                        onClick={action.onClick}
                        disabled={disabled}
                        className="flex items-center gap-1.5 px-4 py-2 bg-primary text-white rounded-xl text-sm font-semibold hover:bg-primary/90 transition disabled:cursor-not-allowed flex-1 justify-center"
                    >
                        {action.icon}
                        {action.label}
                        <Download size={13} />
                    </button>
                ))}
            </div>
        </div>
    );
}

// Inline icon components used in ExportCard since they're just for display
function Clock() { return <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>; }
function CheckSquare() { return <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>; }
function BarChart() { return <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="2" y1="20" x2="22" y2="20"/></svg>; }
