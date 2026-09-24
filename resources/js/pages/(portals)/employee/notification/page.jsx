import React from "react";
import EmployeeLayout from "../layout";
import { Head, Link, usePage } from "@inertiajs/react";
import Pagination from "@/Components/ui/Pagination";

export default function page() {
    const { props } = usePage();
    const notifications = props.notifications;
    return (
        <EmployeeLayout>
            <Head title="Notifications" />
            <div className="w-full">
                <h1 className="text-xl font-bold text-center mb-4 mt-4">
                    Notifications
                </h1>
                <div className="w-full">
                    {notifications?.data?.length > 0 ? (
                        notifications.data.map((notification) => (
                            <Link
                                href={`/user/notification/${notification.id}`}
                                className="p-2 border-t border-gray-50 flex flex-col md:flex-row justify-between border border-gray-200"
                                key={notification.id}
                            >
                                <p
                                    className={`font-bold ${
                                        notification.read_at
                                            ? "text-gray-400"
                                            : "text-gray-900"
                                    }`}
                                >
                                    {notification.title}
                                </p>
                                <p className="hidden md:block">
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
        </EmployeeLayout>
    );
}
