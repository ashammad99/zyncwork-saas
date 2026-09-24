import React from "react";
import ManagerLayout from "../layout";
import { Head, Link, usePage } from "@inertiajs/react";
import Pagination from "@/Components/ui/Pagination";
import { Bell } from "lucide-react";

export default function NotificationPage() {
    const { props } = usePage();
    const notifications = props.notifications;

    return (
        <ManagerLayout>
            <Head title="Notifications" />
            <div className="max-w-4xl mx-auto space-y-6 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                        Notifications
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">All your recent notifications.</p>
                </div>

                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
                    {notifications?.data?.length > 0 ? (
                        notifications.data.map((notification) => (
                            <Link
                                href={`/user/notification/${notification.id}`}
                                key={notification.id}
                                className={`flex flex-col md:flex-row justify-between items-start md:items-center gap-2 px-6 py-4 border-b border-gray-50 last:border-0 hover:bg-gray-50 transition ${notification.read_at ? "" : "bg-blue-50/30"}`}
                            >
                                <div className="flex items-center gap-3">
                                    <div className={`w-2 h-2 rounded-full shrink-0 ${notification.read_at ? "bg-gray-300" : "bg-primary"}`} />
                                    <p className={`text-sm ${notification.read_at ? "text-gray-500 font-normal" : "text-gray-900 font-semibold"}`}>
                                        {notification.title}
                                    </p>
                                </div>
                                <p className="text-xs text-gray-400 shrink-0">
                                    {new Date(notification.created_at).toLocaleString()}
                                </p>
                            </Link>
                        ))
                    ) : (
                        <div className="py-16 text-center text-gray-500">
                            <Bell size={32} className="mx-auto mb-3 opacity-20" />
                            <p>No notifications yet.</p>
                        </div>
                    )}
                </div>

                {notifications?.links && <Pagination links={notifications.links} />}
            </div>
        </ManagerLayout>
    );
}
