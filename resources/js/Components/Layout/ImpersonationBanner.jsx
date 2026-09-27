import React from "react";
import { usePage, router } from "@inertiajs/react";
import { UserCog } from "lucide-react";

/**
 * Shown on every tenant page during a support session (shared prop
 * `impersonation`, set by HandleInertiaRequests). Ending the session sends
 * the browser back to the platform console.
 */
export default function ImpersonationBanner() {
    const session = usePage().props.impersonation;

    if (!session) {
        return null;
    }

    const endsAt = new Date(session.expires_at).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });

    return (
        <div role="status" className="sticky top-0 z-[60] bg-error text-error-foreground text-sm">
            <div className="px-4 py-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                <span className="flex items-center gap-2">
                    <UserCog size={16} className="shrink-0" />
                    <span>
                        Support session: <strong>{session.admin_name}</strong> is acting as{" "}
                        <strong>{session.user_name}</strong>. Changes are recorded. Ends at {endsAt}.
                    </span>
                </span>
                <button
                    onClick={() => router.post("/impersonation/stop")}
                    className="px-3 py-1 rounded-md font-semibold bg-error-foreground text-error hover:opacity-90"
                >
                    End session
                </button>
            </div>
        </div>
    );
}
