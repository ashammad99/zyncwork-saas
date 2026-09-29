import React, { useState, useEffect } from "react";
import { Link, usePage, router } from "@inertiajs/react";
import { Building2, CreditCard, Activity, Zap, LogOut, Menu, X, ShieldCheck } from "lucide-react";

/**
 * Platform console shell (platform host only).
 *
 * The signed-in platform admin comes from the `platform` shared prop, never
 * `auth` (which only ever holds a tenant user). Nav items are shown per
 * capability; the server enforces the same list.
 */
export default function PlatformLayout({ children }) {
    const { url, props } = usePage();
    const admin = props.platform;
    const capabilities = admin?.capabilities ?? [];
    const [isMenuOpen, setIsMenuOpen] = useState(false);

    useEffect(() => setIsMenuOpen(false), [url]);

    const navigation = [
        { name: "Tenants", href: "/tenants", icon: Building2 },
        { name: "Billing", href: "/billing", icon: CreditCard, capability: "view_billing" },
        { name: "System Health", href: "/health", icon: Activity, capability: "view_system_health" },
        { name: "Cache", href: "/cache", icon: Zap, capability: "view_system_health" },
    ].filter((item) => !item.capability || capabilities.includes(item.capability));

    const handleLogout = () => router.post("/logout");

    return (
        <div className="min-h-screen bg-surface-muted text-foreground font-sans">
            <header className="bg-secondary text-secondary-foreground">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
                    <div className="flex items-center gap-8 min-w-0">
                        <Link href="/tenants" className="flex items-center gap-2 font-bold text-lg shrink-0">
                            <ShieldCheck size={22} />
                            <span>{props.app?.name ?? "Zyncwork"} Platform</span>
                        </Link>

                        <nav className="hidden md:flex items-center gap-1">
                            {navigation.map((item) => {
                                const isActive = url.startsWith(item.href);
                                return (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-colors ${
                                            isActive ? "bg-white/15 font-semibold" : "text-white/70 hover:text-white hover:bg-white/10"
                                        }`}
                                    >
                                        <item.icon size={16} />
                                        {item.name}
                                    </Link>
                                );
                            })}
                        </nav>
                    </div>

                    <div className="hidden md:flex items-center gap-4">
                        <div className="text-right leading-tight">
                            <p className="text-sm font-semibold">{admin?.name}</p>
                            <p className="text-xs text-white/60">{admin?.email}</p>
                        </div>
                        <button
                            onClick={handleLogout}
                            className="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-white/80 hover:text-white hover:bg-white/10 transition-colors"
                        >
                            <LogOut size={16} />
                            Sign out
                        </button>
                    </div>

                    <button
                        onClick={() => setIsMenuOpen(!isMenuOpen)}
                        className="md:hidden p-2 -mr-2 rounded-lg hover:bg-white/10"
                        aria-label="Menu"
                    >
                        {isMenuOpen ? <X size={22} /> : <Menu size={22} />}
                    </button>
                </div>

                {isMenuOpen && (
                    <nav className="md:hidden border-t border-white/10 px-4 py-3 space-y-1">
                        {navigation.map((item) => (
                            <Link
                                key={item.name}
                                href={item.href}
                                className={`flex items-center gap-2 px-3 py-2 rounded-lg text-sm ${
                                    url.startsWith(item.href) ? "bg-white/15 font-semibold" : "text-white/80"
                                }`}
                            >
                                <item.icon size={16} />
                                {item.name}
                            </Link>
                        ))}
                        <div className="pt-3 mt-2 border-t border-white/10 flex items-center justify-between">
                            <span className="text-sm text-white/70 truncate">{admin?.email}</span>
                            <button onClick={handleLogout} className="flex items-center gap-2 text-sm text-white/80">
                                <LogOut size={16} />
                                Sign out
                            </button>
                        </div>
                    </nav>
                )}
            </header>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 md:py-8">{children}</main>
        </div>
    );
}
