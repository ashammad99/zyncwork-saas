import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { ArrowLeft, Ban, CheckCircle2, UserCog, AlertTriangle } from "lucide-react";
import { useModal } from "@/Contexts/ModalContext";
import PlatformLayout from "../layout";
import { StatusBadge } from "./Index";

/**
 * Asks for a reason, then posts it. The reason ends up in the audit log, and
 * for support sessions and suspensions also in the tenant's own activity log.
 */
function ReasonForm({ title, intro, submitLabel, minLength = 1, url, data = {}, tone = "primary", onDone }) {
    const [reason, setReason] = useState("");
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const submit = (e) => {
        e.preventDefault();
        router.post(url, { ...data, reason }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: onDone,
        });
    };

    const error = errors.reason ?? errors.user_id;

    return (
        <form onSubmit={submit} className="p-6">
            <h3 className="text-lg font-bold text-surface-foreground">{title}</h3>
            <p className="mt-2 text-sm text-surface-muted-foreground">{intro}</p>

            <label htmlFor="reason" className="block mt-5 mb-2 text-sm font-semibold text-surface-foreground">
                Reason
            </label>
            <textarea
                id="reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                rows={3}
                maxLength={500}
                minLength={minLength}
                required
                autoFocus
                className={`w-full px-4 py-3 border-2 rounded-xl bg-surface outline-none focus:ring-4 focus:ring-primary/10 ${
                    error ? "border-error" : "border-surface-muted focus:border-primary"
                }`}
            />
            {error && <p className="mt-2 text-sm text-error font-semibold">{error}</p>}

            <div className="flex justify-end gap-3 mt-6">
                <button type="button" onClick={onDone} className="px-4 py-2 rounded-lg font-semibold bg-surface-muted text-surface-muted-foreground">
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={processing}
                    className={`px-4 py-2 rounded-lg font-semibold disabled:opacity-60 ${
                        tone === "error" ? "bg-error text-error-foreground" : "bg-primary text-primary-foreground"
                    }`}
                >
                    {submitLabel}
                </button>
            </div>
        </form>
    );
}

function sessionStatus(session) {
    if (session.ended_at) return `Ended (${session.end_reason})`;
    if (session.consumed_at) return "In progress";
    return "Link not used";
}

export default function TenantShow({ tenant, users, impersonations, can }) {
    const { openModal, closeModal } = useModal();
    const suspended = Boolean(tenant.suspended_at);
    const userName = Object.fromEntries(users.map((u) => [u.id, u.name]));

    const suspend = () =>
        openModal(
            <ReasonForm
                title={`Suspend ${tenant.name}`}
                intro="All of its users are signed out and cannot sign in until the suspension is lifted. The tenant sees this reason in its activity log."
                submitLabel="Suspend tenant"
                tone="error"
                url={`/tenants/${tenant.id}/suspend`}
                onDone={closeModal}
            />,
            { size: "md" },
        );

    const unsuspend = () =>
        openModal(
            <div className="p-6">
                <h3 className="text-lg font-bold text-surface-foreground">Lift the suspension?</h3>
                <p className="mt-2 text-sm text-surface-muted-foreground">
                    {tenant.name}'s users will be able to sign in again.
                </p>
                <div className="flex justify-end gap-3 mt-6">
                    <button onClick={closeModal} className="px-4 py-2 rounded-lg font-semibold bg-surface-muted text-surface-muted-foreground">
                        Cancel
                    </button>
                    <button
                        onClick={() => router.post(`/tenants/${tenant.id}/unsuspend`, {}, { onFinish: closeModal })}
                        className="px-4 py-2 rounded-lg font-semibold bg-primary text-primary-foreground"
                    >
                        Lift suspension
                    </button>
                </div>
            </div>,
            { size: "sm" },
        );

    // On success the server sends the browser to the tenant app (a full page visit).
    const actAs = (user) =>
        openModal(
            <ReasonForm
                title={`Act as ${user.name}`}
                intro="You will be signed in to the tenant app as this user for up to 60 minutes. Every change you make is recorded, and the tenant sees that a support session took place, with this reason."
                submitLabel="Start support session"
                minLength={10}
                url={`/tenants/${tenant.id}/impersonate`}
                data={{ user_id: user.id }}
                onDone={closeModal}
            />,
            { size: "md" },
        );

    return (
        <PlatformLayout>
            <Head title={tenant.name} />

            <Link href="/tenants" className="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline mb-4">
                <ArrowLeft size={16} />
                All tenants
            </Link>

            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
                <div>
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold text-surface-foreground">{tenant.name}</h1>
                        <StatusBadge suspendedAt={tenant.suspended_at} />
                    </div>
                    <p className="text-sm text-surface-muted-foreground mt-1">
                        Created {new Date(tenant.created_at).toLocaleDateString()}
                    </p>
                </div>

                {can.suspend &&
                    (suspended ? (
                        <button onClick={unsuspend} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold bg-primary text-primary-foreground">
                            <CheckCircle2 size={16} />
                            Lift suspension
                        </button>
                    ) : (
                        <button onClick={suspend} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold bg-error text-error-foreground">
                            <Ban size={16} />
                            Suspend tenant
                        </button>
                    ))}
            </div>

            {suspended && (
                <div className="flex items-start gap-3 mb-6 p-4 rounded-xl border border-error/30 bg-error/5 text-sm">
                    <AlertTriangle size={18} className="text-error shrink-0 mt-0.5" />
                    <div>
                        <p className="font-semibold text-error">
                            Suspended on {new Date(tenant.suspended_at).toLocaleString()}
                        </p>
                        <p className="text-surface-foreground mt-1">{tenant.suspended_reason}</p>
                    </div>
                </div>
            )}

            <section className="bg-surface border border-border rounded-2xl overflow-hidden mb-6">
                <h2 className="px-5 py-4 font-bold text-surface-foreground border-b border-border">Users</h2>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-surface-muted text-left text-xs uppercase tracking-wider text-surface-muted-foreground">
                            <tr>
                                <th className="px-5 py-3 font-semibold">Name</th>
                                <th className="px-5 py-3 font-semibold">Email</th>
                                <th className="px-5 py-3 font-semibold">Role</th>
                                <th className="px-5 py-3 font-semibold">Account</th>
                                {can.impersonate && <th className="px-5 py-3" />}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {users.map((user) => (
                                <tr key={user.id}>
                                    <td className="px-5 py-3.5 font-semibold text-surface-foreground">{user.name}</td>
                                    <td className="px-5 py-3.5 text-surface-muted-foreground">{user.email}</td>
                                    <td className="px-5 py-3.5 capitalize">{user.role}</td>
                                    <td className="px-5 py-3.5">{user.is_active ? "Active" : "Deactivated"}</td>
                                    {can.impersonate && (
                                        <td className="px-5 py-3.5 text-right">
                                            {user.is_active && !suspended && (
                                                <button
                                                    onClick={() => actAs(user)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-border hover:border-primary hover:text-primary"
                                                >
                                                    <UserCog size={14} />
                                                    Act as
                                                </button>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="bg-surface border border-border rounded-2xl overflow-hidden">
                <h2 className="px-5 py-4 font-bold text-surface-foreground border-b border-border">Recent support sessions</h2>
                {impersonations.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-surface-muted-foreground">No support sessions yet.</p>
                ) : (
                    <ul className="divide-y divide-border text-sm">
                        {impersonations.map((session) => (
                            <li key={session.id} className="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                                <div className="min-w-0">
                                    <p className="font-semibold text-surface-foreground">
                                        {session.platform_admin?.name ?? "Unknown admin"} as {userName[session.target_user_id] ?? `user #${session.target_user_id}`}
                                    </p>
                                    <p className="text-surface-muted-foreground truncate">{session.reason}</p>
                                </div>
                                <div className="sm:text-right shrink-0">
                                    <p className="font-medium">{sessionStatus(session)}</p>
                                    <p className="text-xs text-surface-muted-foreground">{new Date(session.created_at).toLocaleString()}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </PlatformLayout>
    );
}
