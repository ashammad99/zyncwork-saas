import React from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { CreditCard, Users, Mail, AlertTriangle, Save, Sparkles, Download } from "lucide-react";

const STATUS_LABELS = {
    trialing: "Trial",
    active: "Active",
    past_due: "Payment overdue",
    canceled: "Canceled",
    none: "No plan",
};

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString(undefined, { day: "numeric", month: "long", year: "numeric" }) : null;
}

function daysUntil(value) {
    return Math.ceil((new Date(value) - new Date()) / 86400000);
}

/** The one message that matters most right now, if any. */
function Notice({ billing }) {
    let text = null;
    if (billing.trial_expired) {
        text = "Your trial has ended. Existing users keep working, but you can't add new users until you choose a plan.";
    } else if (!billing.can_add_seats) {
        text = "Your plan isn't active, so no new users can be added. Existing users keep working.";
    } else if (billing.seats_full) {
        text = `All ${billing.seats} seats are in use. Deactivate a user or revoke a pending invitation to free one up.`;
    }

    if (!text) return null;

    return (
        <div className="flex items-start gap-3 rounded-2xl border border-error/30 bg-error/5 px-5 py-4 text-sm text-error">
            <AlertTriangle size={18} className="mt-0.5 shrink-0" />
            <p>{text}</p>
        </div>
    );
}

function BillingEmailForm({ email }) {
    const { data, setData, put, processing, errors, isDirty } = useForm({ billing_email: email ?? "" });

    const submit = (e) => {
        e.preventDefault();
        put("/admin/billing/email", { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-3">
            <label htmlFor="billing_email" className="block text-sm font-medium text-gray-700">
                Billing email
            </label>
            <div className="flex flex-col sm:flex-row gap-3">
                <input
                    id="billing_email"
                    type="email"
                    value={data.billing_email}
                    onChange={(e) => setData("billing_email", e.target.value)}
                    className="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary"
                />
                <button
                    type="submit"
                    disabled={processing || !isDirty}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary/90 disabled:opacity-50 shrink-0"
                >
                    <Save size={16} /> Save
                </button>
            </div>
            {errors.billing_email && <p className="text-xs text-red-600">{errors.billing_email}</p>}
            <p className="text-xs text-gray-500">
                Receipts and billing notices go here. When it changes, the previous address is told.
            </p>
        </form>
    );
}

const EXPORT_STATUS = {
    queued: "Preparing…",
    running: "Preparing…",
    ready: "Ready",
    failed: "Failed",
    expired: "Expired",
};

function formatSize(bytes) {
    if (!bytes) return null;
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function DataExport({ exports }) {
    const start = () => router.post("/admin/exports", {}, { preserveScroll: true });

    return (
        <section className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 space-y-4">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2 text-gray-900 font-bold">
                        <Download size={18} /> Data export
                    </div>
                    <p className="text-sm text-gray-500 mt-1">
                        A ZIP of CSV files with your users, departments, projects, tasks, time logs, comments, settings,
                        activity log and invitations. We email you a link when it's ready; only you can download it.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={start}
                    disabled={!exports.can_request}
                    className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary/90 disabled:opacity-50 shrink-0"
                >
                    Start export
                </button>
            </div>
            {!exports.can_request && (
                <p className="text-xs text-gray-500">An export is being prepared, or the limit of 3 exports in 24 hours has been reached.</p>
            )}

            {exports.recent.length > 0 && (
                <ul className="divide-y divide-gray-100 text-sm">
                    {exports.recent.map((item) => (
                        <li key={item.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 py-3">
                            <span className="text-gray-700">
                                {formatDate(item.created_at)} · {item.requested_by ?? "Unknown"}
                                {item.size_bytes ? ` · ${formatSize(item.size_bytes)}` : ""}
                            </span>
                            <span className="flex items-center gap-3">
                                <span className="text-gray-500">{EXPORT_STATUS[item.status] ?? item.status}</span>
                                {item.download_url && (
                                    <a href={item.download_url} className="font-semibold text-primary hover:underline">
                                        Download
                                    </a>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function BillingPage({ billing, exports }) {
    const statusLabel = billing.trial_expired ? "Trial ended" : STATUS_LABELS[billing.status] ?? "No plan";
    const goodStanding = billing.can_add_seats;
    const usedPct = billing.seats > 0 ? Math.min(100, Math.round((billing.seats_used / billing.seats) * 100)) : 100;

    return (
        <AdminLayout>
            <Head title="Billing" />
            <div className="max-w-4xl mx-auto space-y-6 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Billing</h1>
                    <p className="text-sm text-gray-500 mt-1">Your plan, seats and billing contact.</p>
                </div>

                <Notice billing={billing} />

                <div className="grid gap-6 md:grid-cols-2">
                    <section className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 space-y-4">
                        <div className="flex items-center gap-2 text-gray-900 font-bold">
                            <CreditCard size={18} /> Plan
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-xl font-semibold text-gray-900">{billing.plan ?? "No plan"}</span>
                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${goodStanding ? "bg-success/10 text-success" : "bg-error/10 text-error"}`}>
                                {statusLabel}
                            </span>
                        </div>
                        {billing.status === "trialing" && billing.trial_ends_at && (
                            <p className="text-sm text-gray-600">
                                {billing.trial_expired
                                    ? `Your trial ended on ${formatDate(billing.trial_ends_at)}.`
                                    : `Your trial ends on ${formatDate(billing.trial_ends_at)} (in ${daysUntil(billing.trial_ends_at)} days).`}
                            </p>
                        )}
                    </section>

                    <section className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 space-y-4">
                        <div className="flex items-center gap-2 text-gray-900 font-bold">
                            <Users size={18} /> Seats
                        </div>
                        <p className="text-xl font-semibold text-gray-900 tabular-nums">
                            {billing.seats_used} of {billing.seats} used
                        </p>
                        <div className="h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div className={`h-full ${billing.seats_full ? "bg-error" : "bg-primary"}`} style={{ width: `${usedPct}%` }} />
                        </div>
                        <p className="text-sm text-gray-600">
                            {billing.active_users} active {billing.active_users === 1 ? "user" : "users"} and{" "}
                            {billing.pending_invitations} pending {billing.pending_invitations === 1 ? "invitation" : "invitations"}.
                            Deactivated users don't use a seat. <Link href="/admin/users" className="text-primary hover:underline">Manage users</Link>
                        </p>
                    </section>
                </div>

                {/* Placeholder: the subscribe / add-seats action goes here once a payment provider is connected (Phase 1D, Paddle). */}
                <section className="border border-dashed border-gray-300 rounded-2xl p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 font-bold text-gray-900">
                            <Sparkles size={18} /> Upgrade or add seats
                        </div>
                        <p className="text-sm text-gray-500 mt-1">Online plans and payment are coming soon.</p>
                    </div>
                    <button type="button" disabled className="rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-400 cursor-not-allowed shrink-0">
                        Coming soon
                    </button>
                </section>

                <section className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 space-y-4">
                    <div className="flex items-center gap-2 text-gray-900 font-bold">
                        <Mail size={18} /> Billing contact
                    </div>
                    <BillingEmailForm email={billing.billing_email} />
                </section>

                {exports && <DataExport exports={exports} />}
            </div>
        </AdminLayout>
    );
}
