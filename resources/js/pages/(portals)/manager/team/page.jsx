import React from "react";
import ManagerLayout from "../layout";
import { Head, Link } from "@inertiajs/react";
import { Users, Clock, CheckSquare, TrendingUp } from "lucide-react";

export default function TeamPage({ members }) {
    return (
        <ManagerLayout>
            <Head title="Team" />
            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                        Team
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Overview of your department members and their current workload.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
                    {members.map((member) => (
                        <MemberCard key={member.id} member={member} />
                    ))}
                </div>

                {members.length === 0 && (
                    <div className="bg-white border border-gray-200 rounded-2xl py-16 text-center text-gray-500">
                        <Users size={40} className="mx-auto mb-3 opacity-20" />
                        No team members found.
                    </div>
                )}
            </div>
        </ManagerLayout>
    );
}

function MemberCard({ member }) {
    const utilization = member.utilization ?? 0;

    const getUtilizationColor = () => {
        if (utilization > 120) return { bar: "bg-red-500", badge: "bg-red-100 text-red-700", label: "Overloaded" };
        if (utilization > 100) return { bar: "bg-yellow-500", badge: "bg-yellow-100 text-yellow-700", label: "Near Limit" };
        if (utilization >= 60) return { bar: "bg-green-500", badge: "bg-green-100 text-green-700", label: "On Track" };
        return { bar: "bg-gray-300", badge: "bg-gray-100 text-gray-600", label: "Under-utilized" };
    };

    const colors = getUtilizationColor();
    const barWidth = Math.min(utilization, 150);

    return (
        <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 hover:shadow-md transition-all">
            <div className="flex items-start gap-4 mb-5">
                <div className="w-12 h-12 rounded-full overflow-hidden border-2 border-gray-100 shrink-0 bg-primary/10 flex items-center justify-center">
                    {member.profile_image ? (
                        <img
                            src={`/user/profile-image/${member.profile_image}`}
                            alt={member.name}
                            className="w-full h-full object-cover"
                            onError={(e) => { e.target.style.display = "none"; }}
                        />
                    ) : (
                        <span className="text-lg font-bold text-primary">{member.name.charAt(0)}</span>
                    )}
                </div>
                <div className="flex-1 min-w-0">
                    <p className="font-bold text-gray-900 truncate">{member.name}</p>
                    <p className="text-xs text-gray-500 truncate">{member.email}</p>
                    <span className={`inline-block mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold ${colors.badge}`}>
                        {colors.label}
                    </span>
                </div>
            </div>

            {/* Utilization Bar */}
            <div className="mb-5">
                <div className="flex justify-between items-center mb-1.5">
                    <span className="text-xs font-semibold text-gray-600">Capacity Utilization</span>
                    <span className="text-xs font-bold text-gray-900">{utilization}%</span>
                </div>
                <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        className={`h-full rounded-full transition-all ${colors.bar}`}
                        style={{ width: `${barWidth}%` }}
                    />
                </div>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-3 gap-3">
                <StatItem icon={<CheckSquare size={14} />} label="Tasks" value={member.assigned_tasks ?? 0} />
                <StatItem icon={<Clock size={14} />} label="Hrs / Week" value={`${member.logged_hours_week ?? 0}h`} />
                <StatItem icon={<TrendingUp size={14} />} label="Capacity" value={`${member.daily_capacity_hours ?? 8}h/d`} />
            </div>

            <Link
                href={`/manager/timesheets?user_id=${member.id}`}
                className="mt-4 block text-center text-xs font-semibold text-primary hover:underline"
            >
                View Timesheet →
            </Link>
        </div>
    );
}

function StatItem({ icon, label, value }) {
    return (
        <div className="text-center p-2.5 bg-gray-50 rounded-xl">
            <div className="flex justify-center text-gray-400 mb-1">{icon}</div>
            <p className="text-sm font-bold text-gray-900">{value}</p>
            <p className="text-[10px] text-gray-500">{label}</p>
        </div>
    );
}
