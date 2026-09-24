import React from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, Link, usePage } from "@inertiajs/react";
import Pagination from "@/Components/ui/Pagination";

export default function Page() {
    const { props } = usePage();
    const notifications = props.notifications;

    return (
        <AdminLayout>
            <Head title="Notifications" />
            <div className="w-full">
                <h1 className="text-xl font-bold text-center mb-4 mt-4">
                    Admin Notifications
                </h1>

                <div className="w-full">
                    {notifications?.data?.length > 0 ? (
                        notifications.data.map((notification) => (
                            <Link
                                href={"/admin/notification/" + notification.id}
                                className="p-3 border flex flex-col md:flex-row justify-between border-gray-200 hover:bg-gray-50 transition"
                                key={notification.id}
                            >
                                <p
                                    className={`font-semibold ${
                                        notification.read_at
                                            ? "text-gray-400"
                                            : "text-gray-900"
                                    }`}
                                >
                                    {notification.title}
                                </p>

                                <p className="text-sm text-gray-500">
                                    {new Date(
                                        notification.created_at,
                                    ).toLocaleString()}
                                </p>
                            </Link>
                        ))
                    ) : (
                        <p>No notifications found</p>
                    )}
                </div>

                <Pagination links={notifications.links} />
            </div>
        </AdminLayout>
    );
}
