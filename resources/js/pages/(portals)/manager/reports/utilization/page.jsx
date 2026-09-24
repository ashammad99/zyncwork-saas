import React from "react";
import ManagerLayout from "../../layout";
import { Head } from "@inertiajs/react";
import { Users } from "lucide-react";

export default function UtilizationPage({ report }) {
    return (
        <ManagerLayout>
            <Head title="Utilization Report" />
            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                        Team Utilization Report
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Current week's assigned hours vs capacity for each team member.
                    </p>
                </div>

                {/* Legend */}
                <div className="flex flex-wrap gap-4 text-xs font-semibold">
                    <LegendItem color="bg-gray-300 text-gray-600" label="Under-utilized (<60%)" />
                    <LegendItem color="bg-green-500 text-green-700" label="On Track (60–100%)" />
                    <LegendItem color="bg-yellow-500 text-yellow-700" label="Near Limit (100–120%)" />
                    <LegendItem color="bg-red-500 text-red-700" label="Overloaded (>120%)" />
                </div>

                <div className="bg-white border text-sm border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left whitespace-nowrap">
                            <thead className="bg-gray-50/50 border-b border-gray-200 text-gray-500 uppercase tracking-wider text-[10px] font-bold">
                                <tr>
                                    <th className="px-6 py-4">Employee</th>
                                    <th className="px-6 py-4">Assigned Hrs</th>
                                    <th className="px-6 py-4">Logged Hrs</th>
                                    <th className="px-6 py-4">Capacity</th>
                                    <th className="px-6 py-4">Utilization</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 w-64">Load Bar</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {report.map((row) => {
                                    const util = row.utilization_percent ?? 0;
                                    const colors = getColors(util);

                                    return (
                                        <tr key={row.user_id} className="hover:bg-gray-50/50 transition-colors">
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs shrink-0">
                                                        {row.name.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <p className="font-semibold text-gray-900">{row.name}</p>
                                                        <p className="text-xs text-gray-500">{row.department}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 font-semibold text-gray-900">{row.assigned_hours}h</td>
                                            <td className="px-6 py-4 text-gray-600">{row.logged_hours}h</td>
                                            <td className="px-6 py-4 text-gray-600">{row.capacity}h</td>
                                            <td className="px-6 py-4 font-bold text-gray-900">{util}%</td>
                                            <td className="px-6 py-4">
                                                <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${colors.badge}`}>
                                                    {colors.label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="h-2.5 bg-gray-100 rounded-full overflow-hidden w-full">
                                                    <div
                                                        className={`h-full rounded-full transition-all ${colors.bar}`}
                                                        style={{ width: `${Math.min(util, 150)}%` }}
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>

                        {report.length === 0 && (
                            <div className="py-12 text-center text-gray-500">
                                <Users size={32} className="mx-auto mb-2 opacity-20" />
                                No data available.
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </ManagerLayout>
    );
}

function getColors(util) {
    if (util > 120) return { bar: "bg-red-500", badge: "bg-red-100 text-red-700", label: "Overloaded" };
    if (util > 100) return { bar: "bg-yellow-500", badge: "bg-yellow-100 text-yellow-700", label: "Near Limit" };
    if (util >= 60) return { bar: "bg-green-500", badge: "bg-green-100 text-green-700", label: "On Track" };
    return { bar: "bg-gray-300", badge: "bg-gray-100 text-gray-600", label: "Under-utilized" };
}

function LegendItem({ color, label }) {
    return (
        <div className="flex items-center gap-2">
            <span className={`w-3 h-3 rounded-full ${color.split(" ")[0]}`} />
            <span className="text-gray-600">{label}</span>
        </div>
    );
}
