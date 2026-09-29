import React from "react";
import { Head, Link } from "@inertiajs/react";
import { CreditCard } from "lucide-react";
import PlatformLayout from "../layout";
import { StatusBadge } from "../tenants/Index";

/** Billing status per tenant. Plans arrive with Phase 1D (Cashier + Stripe); until then `plan` is always null. */
export default function BillingIndex({ tenants }) {
    return (
        <PlatformLayout>
            <Head title="Billing" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-surface-foreground">Billing</h1>
                <p className="text-sm text-surface-muted-foreground mt-1">
                    Plans and subscriptions are not connected yet, so every tenant shows as having no plan.
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
                                    <th className="px-5 py-3 font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {tenants.map((tenant) => (
                                    <tr key={tenant.id}>
                                        <td className="px-5 py-3.5 font-semibold text-surface-foreground">
                                            <Link href={`/tenants/${tenant.id}`} className="hover:text-primary">
                                                {tenant.name}
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3.5 text-surface-muted-foreground">{tenant.plan ?? "No plan"}</td>
                                        <td className="px-5 py-3.5">
                                            <StatusBadge suspendedAt={tenant.suspended_at} />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </PlatformLayout>
    );
}
