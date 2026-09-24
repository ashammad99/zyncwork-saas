import React from "react";
import { usePage, Link, Head } from "@inertiajs/react";
import EmployeeLayout from "../../layout";


export default function Page() {
    const { props } = usePage();
    const notification = props.notification;

    return (
        <EmployeeLayout>
            <Head title={`${notification.title}`} />
            <div className="w-full mx-auto p-4">
                {/* Back button */}
                <Link
                    href="/user/notification"
                    className="text-sm text-blue-600 mb-4 inline-block"
                >
                    ← Back to Notifications
                </Link>

                {/* Card */}
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

                    {/* Description (HTML) */}
                    <div
                        className="prose max-w-none"
                        dangerouslySetInnerHTML={{
                            __html: notification.description,
                        }}
                    />
                </div>
            </div>
        </EmployeeLayout>
    );
}
