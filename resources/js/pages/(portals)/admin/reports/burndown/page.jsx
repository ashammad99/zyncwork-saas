import React, { useEffect, useRef } from "react";
import { Head, router } from "@inertiajs/react";
import AdminLayout  from "../../layout";
import {
    TrendingDown,
    CalendarX,
    ListTodo,
    CheckCircle2,
    CalendarClock,
} from "lucide-react";
import Chart from "chart.js/auto";

/**
 * @param {object} props
 * @param {Array<{ id, name, deadline }>} props.projects
 * @param {number|null} props.selectedId
 * @param {{ labels, ideal, actual, total_tasks, completed, deadline }|null} props.burndown
 */
export default function BurndownPage({ projects = [], selectedId = null, burndown = null }) {
    const selectProject = (id) => {
        router.get("/reports/burndown", { project_id: id }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const selected = projects.find((p) => p.id === selectedId);
    const remaining = burndown ? burndown.total_tasks - burndown.completed : 0;

    return (
        <AdminLayout>
            <Head title="Burndown Chart" />

            <div className="max-w-6xl mx-auto space-y-6 py-6">

                {/* Header + project selector */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Burndown Chart</h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Remaining tasks vs. the ideal pace to hit the deadline.
                        </p>
                    </div>

                    <div className="min-w-[220px]">
                        <label className="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">
                            Project
                        </label>
                        <select
                            value={selectedId ?? ""}
                            onChange={(e) => selectProject(Number(e.target.value))}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition"
                        >
                            {projects.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}{p.deadline ? "" : " (no deadline)"}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                {projects.length === 0 ? (
                    <EmptyState
                        icon={<ListTodo size={24} />}
                        title="No projects yet"
                        text="Create a project to see its burndown."
                    />
                ) : !burndown ? (
                    <EmptyState
                        icon={<CalendarX size={24} />}
                        title="No deadline set"
                        text={`"${selected?.name}" has no deadline, so a burndown can't be plotted. Set a deadline on the project to enable this chart.`}
                    />
                ) : (
                    <>
                        {/* Stat chips */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <StatChip
                                icon={<ListTodo size={16} />}
                                label="Total Tasks"
                                value={burndown.total_tasks}
                            />
                            <StatChip
                                icon={<CheckCircle2 size={16} />}
                                label="Completed"
                                value={burndown.completed}
                                color="text-emerald-600"
                            />
                            <StatChip
                                icon={<TrendingDown size={16} />}
                                label="Remaining"
                                value={remaining}
                                color={remaining > 0 ? "text-amber-600" : "text-emerald-600"}
                            />
                            <StatChip
                                icon={<CalendarClock size={16} />}
                                label="Deadline"
                                value={new Date(burndown.deadline).toLocaleDateString(undefined, {
                                    month: "short", day: "numeric", year: "numeric",
                                })}
                            />
                        </div>

                        {/* Chart */}
                        <div className="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
                            <BurndownChart burndown={burndown} />
                        </div>
                    </>
                )}
            </div>
        </AdminLayout>
    );
}

/* ─── The chart itself ───────────────────────────────────── */

function BurndownChart({ burndown }) {
    const canvasRef = useRef(null);
    const chartRef = useRef(null);

    useEffect(() => {
        if (!canvasRef.current) return;

        // Destroy previous instance on re-render / project switch
        chartRef.current?.destroy();

        chartRef.current = new Chart(canvasRef.current, {
            type: "line",
            data: {
                labels: burndown.labels.map(formatLabel),
                datasets: [
                    {
                        label: "Ideal",
                        data: burndown.ideal,
                        borderColor: "#9ca3af",
                        borderDash: [6, 6],
                        borderWidth: 2,
                        pointRadius: 0,
                        fill: false,
                        tension: 0,
                    },
                    {
                        label: "Actual",
                        data: burndown.actual,
                        borderColor: "#6366f1",
                        backgroundColor: "rgba(99, 102, 241, 0.08)",
                        borderWidth: 2.5,
                        pointRadius: 2,
                        pointHoverRadius: 5,
                        fill: true,
                        tension: 0.15,
                        spanGaps: false,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: "index", intersect: false },
                plugins: {
                    legend: {
                        position: "top",
                        align: "end",
                        labels: { boxWidth: 24, boxHeight: 2, usePointStyle: false },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) =>
                                ` ${ctx.dataset.label}: ${ctx.parsed.y ?? "—"} tasks remaining`,
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        title: { display: true, text: "Tasks remaining" },
                    },
                    x: {
                        ticks: { maxTicksLimit: 12, maxRotation: 0 },
                        grid: { display: false },
                    },
                },
            },
        });

        return () => chartRef.current?.destroy();
    }, [burndown]);

    return (
        <div className="relative h-[380px]">
            <canvas ref={canvasRef} />
        </div>
    );
}

/* ─── Bits ───────────────────────────────────────────────── */

function StatChip({ icon, label, value, color = "text-gray-900" }) {
    return (
        <div className="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
            <div className="flex items-center gap-1.5 text-gray-400 mb-1.5">
                {icon}
                <span className="text-[10px] font-bold uppercase tracking-wider">{label}</span>
            </div>
            <div className={`text-xl font-bold ${color}`}>{value}</div>
        </div>
    );
}

function EmptyState({ icon, title, text }) {
    return (
        <div className="bg-white border border-dashed border-gray-300 rounded-2xl p-12 text-center">
            <div className="w-12 h-12 mx-auto mb-4 rounded-full bg-gray-50 flex items-center justify-center text-gray-400">
                {icon}
            </div>
            <h3 className="font-semibold text-gray-900 mb-1">{title}</h3>
            <p className="text-sm text-gray-500 max-w-md mx-auto">{text}</p>
        </div>
    );
}

function formatLabel(iso) {
    return new Date(iso).toLocaleDateString(undefined, { month: "short", day: "numeric" });
}
