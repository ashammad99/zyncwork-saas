import React from "react";
import { usePage, Link, Head } from "@inertiajs/react";
import AdminLayout from "@/pages/(portals)/admin/layout";

export default function Page() {
    const { props } = usePage();
    const notification = props.notification;

    return (
        <AdminLayout>
            <Head title={notification.title} />
            <div className="w-full max-w-3xl mx-auto p-4">
                <Link
                    href={"/admin/notification"}
                    className="text-sm text-blue-600 mb-4 inline-block"
                >
                    ← Back to Notifications
                </Link>

                <div className="border border-gray-200 rounded-lg p-4 shadow-sm">
                    <h1 className="text-xl font-bold mb-2">
                        {notification.title}
                    </h1>

                    <div className="text-sm text-gray-500 mb-4 flex justify-between">
                        <span>Category: {notification.category}</span>
                        <span>
                            {new Date(notification.created_at).toLocaleString()}
                        </span>
                    </div>

                    <div
                        className="prose max-w-none"
                        dangerouslySetInnerHTML={{
                            __html: notification.description,
                        }}
                    />
                </div>
            </div>
        </AdminLayout>
    );
}
