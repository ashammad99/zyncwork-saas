import React from "react";
import { Head, Link } from "@inertiajs/react";
import { Building2, ChevronRight } from "lucide-react";
import PlatformLayout from "../layout";

export function StatusBadge({ suspendedAt }) {
    return suspendedAt ? (
        <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-error/10 text-error">Suspended</span>
    ) : (
        <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-success/10 text-success">Active</span>
    );
}

export default function TenantsIndex({ tenants }) {
    return (
        <PlatformLayout>
            <Head title="Tenants" />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-surface-foreground">Tenants</h1>
                <p className="text-sm text-surface-muted-foreground mt-1">
                    Every organisation on the platform. Opening one is recorded in the platform audit log.
                </p>
            </div>

            <div className="bg-surface border border-border rounded-2xl overflow-hidden">
                {tenants.length === 0 ? (
                    <div className="px-6 py-12 text-center text-surface-muted-foreground">
                        <Building2 size={28} className="mx-auto mb-2 opacity-40" />
                        No tenants yet.
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-surface-muted text-left text-xs uppercase tracking-wider text-surface-muted-foreground">
                                <tr>
                                    <th className="px-5 py-3 font-semibold">Name</th>
                                    <th className="px-5 py-3 font-semibold">Users</th>
                                    <th className="px-5 py-3 font-semibold">Status</th>
                                    <th className="px-5 py-3 font-semibold">Created</th>
                                    <th className="px-5 py-3" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {tenants.map((tenant) => (
                                    <tr key={tenant.id} className="hover:bg-surface-muted/50">
                                        <td className="px-5 py-3.5 font-semibold text-surface-foreground">
                                            <Link href={`/tenants/${tenant.id}`} className="hover:text-primary">
                                                {tenant.name}
                                            </Link>
                                        </td>
                                        <td className="px-5 py-3.5">{tenant.users_count}</td>
                                        <td className="px-5 py-3.5">
                                            <StatusBadge suspendedAt={tenant.suspended_at} />
                                        </td>
                                        <td className="px-5 py-3.5 text-surface-muted-foreground">
                                            {new Date(tenant.created_at).toLocaleDateString()}
                                        </td>
                                        <td className="px-5 py-3.5 text-right">
                                            <Link href={`/tenants/${tenant.id}`} className="inline-flex text-surface-muted-foreground hover:text-primary" aria-label={`Open ${tenant.name}`}>
                                                <ChevronRight size={18} />
                                            </Link>
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
