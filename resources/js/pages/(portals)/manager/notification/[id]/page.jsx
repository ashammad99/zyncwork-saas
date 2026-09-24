import React from "react";
import ManagerLayout from "../../layout";
import { Head, usePage, Link } from "@inertiajs/react";
import { Bell, ArrowLeft, CheckCircle } from "lucide-react";

export default function NotificationDetailPage() {
    const { props } = usePage();
    const notification = props.notification;

    return (
        <ManagerLayout>
            <Head title="Notification" />
            <div className="max-w-2xl mx-auto space-y-6 md:pb-12">
                <Link href="/user/notification" className="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-primary transition">
                    <ArrowLeft size={16} />
                    Back to Notifications
                </Link>

                <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-8">
                    <div className="flex items-start gap-4 mb-6">
                        <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                            <Bell size={24} className="text-primary" />
                        </div>
                        <div>
                            <h1 className="text-xl font-bold text-gray-900">{notification?.title}</h1>
                            <p className="text-sm text-gray-500 mt-1">
                                {notification?.created_at ? new Date(notification.created_at).toLocaleString() : ""}
                            </p>
                        </div>
                        {notification?.read_at && (
                            <span className="ml-auto flex items-center gap-1 text-xs text-green-600 font-semibold">
                                <CheckCircle size={14} /> Read
                            </span>
                        )}
                    </div>

                    <div className="text-gray-700 text-sm leading-relaxed whitespace-pre-wrap border-t border-gray-50 pt-6">
                        {notification.description ?? "No content."}
                    </div>
                </div>
            </div>
        </ManagerLayout>
    );
}
