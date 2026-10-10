import React from "react";
import { Head, Link } from "@inertiajs/react";
import { CreditCard } from "lucide-react";
import PlatformLayout from "../layout";
import { StatusBadge } from "../tenants/Index";

const STATUS_LABELS = {
    trialing: "Trial",
    active: "Active",
    past_due: "Past due",
    canceled: "Canceled",
    none: "No plan",
};

function formatDate(value) {
    return value ? new Date(value).toLocaleDateString() : null;
}

/** Can this tenant add users? Mirrors TenantSubscription::canAddSeats(). */
function isInGoodStanding(tenant) {
    return tenant.status === "active" || (tenant.status === "trialing" && !tenant.trial_expired);
}

function BillingStatusBadge({ tenant }) {
    if (!tenant.status) {
        return <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-error/10 text-error">No subscription</span>;
    }

    const label = tenant.trial_expired ? "Trial expired" : STATUS_LABELS[tenant.status] ?? tenant.status;
    const tone = isInGoodStanding(tenant) ? "bg-success/10 text-success" : "bg-error/10 text-error";

    return <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${tone}`}>{label}</span>;
}

function PeriodEnd({ tenant }) {
    if (tenant.status === "trialing" && tenant.trial_ends_at) {
        return <>Trial {tenant.trial_expired ? "ended" : "ends"} {formatDate(tenant.trial_ends_at)}</>;
    }
    if (tenant.status === "past_due" && tenant.grace_ends_at) {
        return <>Grace ends {formatDate(tenant.grace_ends_at)}</>;
    }
    if (tenant.current_period_ends_at) {
        return <>Renews {formatDate(tenant.current_period_ends_at)}</>;
    }
    return <>—</>;
}

/** Billing status per tenant, from Zyncwork's own subscription records (no payment provider is connected yet). */
export default function BillingIndex({ tenants }) {
    return (
        <PlatformLayout>
            <Head title="Billing" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-surface-foreground">Billing</h1>
                <p className="text-sm text-surface-muted-foreground mt-1">
                    Plan, status and seats per tenant. Seats used count active users plus pending invitations.
                </p>
            </div>

            <div className="bg-surface border border-border rounded-2xl overflow-hidden">
                {tenants.length === 0 ? (
                    <div className="px-6 py-12 text-center text-surface-muted-foreground">
                        <CreditCard size={28} className="mx-auto mb-2 opacity-40" />
                        No tenants yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-surface-muted text-left text-xs uppercase tracking-wider text-surface-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">Tenant</th>
                                    <th className="px-5 py-3 font-semibold">Plan</th>
                                    <th className="px-5 py-3 font-semibold">Billing</th>
                                    <th className="px-5 py-3 font-semibold">Seats</th>
                                    <th className="px-5 py-3 font-semibold">Period</th>
                                    <th className="px-5 py-3 font-semibold">Billing email</th>
                                    <th className="px-5 py-3 font-semibold">Account</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {tenants.map((tenant) => {
                                    const overLimit = tenant.seats !== null && tenant.seats_used > tenant.seats;

                                    return (
                                        <tr key={tenant.id}>
                                            <td className="px-5 py-3.5 font-semibold text-surface-foreground">
                                                <Link href={`/tenants/${tenant.id}`} className="hover:text-primary">
                                                    {tenant.name}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3.5 text-surface-muted-foreground">{tenant.plan ?? "No plan"}</td>
                                            <td className="px-5 py-3.5">
                                                <BillingStatusBadge tenant={tenant} />
                                            </td>
                                            <td className={`px-5 py-3.5 tabular-nums ${overLimit ? "text-error font-semibold" : "text-surface-muted-foreground"}`}>
                                                {tenant.seats_used} / {tenant.seats ?? "—"}
                                                {overLimit && <span className="ml-1 text-xs">(over)</span>}
                                            </td>
                                            <td className="px-5 py-3.5 text-surface-muted-foreground whitespace-nowrap">
                                                <PeriodEnd tenant={tenant} />
                                            </td>
                                            <td className="px-5 py-3.5 text-surface-muted-foreground break-all">{tenant.billing_email ?? "—"}</td>
                                            <td className="px-5 py-3.5">
                                                <StatusBadge suspendedAt={tenant.suspended_at} />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </PlatformLayout>
    );
}
