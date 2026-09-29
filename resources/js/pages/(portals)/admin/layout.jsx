import React, { useState, useEffect } from "react";
import { Link, usePage, router } from "@inertiajs/react";
import {
    LayoutDashboard,
    Users,
    Settings,
    User,
    Menu,
    X,
    Home,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
    Bell,
    Key,
    Globe,
    History,
    Building2,
    FolderKanban,
    CheckSquare,
    Clock,
    TrendingDown
} from "lucide-react";

import MainLayout from "../../../Components/Layout/MainLayout";

/**
 * Admin Layout
 *
 * Matches the premium UI/UX of User portal.
 * Enhanced sidebar, interactive dropdowns, and standardized typography.
 */
export default function AdminLayout({ children }) {
    const { url, props } = usePage();
    const user = props.auth?.user;
    const notifications = props.notifications ?? [];

    // Desktop: false = collapsed (icons only), true = expanded
    const [isSidebarExpanded, setIsSidebarExpanded] = useState(false);

    // Mobile: false = hidden, true = open
    const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState(false);
    const [isProfileOpen, setIsProfileOpen] = useState(false);
    const [isNotificationsOpen, setIsNotificationsOpen] = useState(false);

    // Close mobile sidebar on route change
    useEffect(() => {
        setIsProfileOpen(false);
        setIsNotificationsOpen(false);
        setIsMobileSidebarOpen(false);
    }, [url]);
    useEffect(() => {
        const id = setInterval(() => {
            if (document.visibilityState === "visible") {
                router.reload({ only: ["notifications", "auth"] });
            }
        }, 60000);

        return () => clearInterval(id);
    }, []);
    const navigation = [
        {
            name: "Dashboard",
            href: "/dashboard",
            icon: LayoutDashboard,
        },
        { name: "Users", href: "/admin/users", icon: Users },
        { name: "Departments", href: "/admin/departments", icon: Building2 },
        { name: "Projects", href: "/admin/projects", icon:  FolderKanban },
        { name: "Tasks", href: "/admin/tasks",icon: CheckSquare },
        { name: "Time Logs", href: "/admin/timesheets", icon: Clock },
        { name: "Settings", href: "/admin/settings", icon: Settings,},
        { name: "Burndown", href: "/reports/burndown", icon: TrendingDown },
        { name: "Activity Logs", href: "/admin/activity-logs", icon: History, },
    ];

    const handleLogout = (e) => {
        e.preventDefault();
        router.post("/auth/logout");
    };

    const toggleMobileSidebar = () =>
        setIsMobileSidebarOpen(!isMobileSidebarOpen);

    return (
        <MainLayout>
            <div className="min-h-screen bg-[#F9FAFB] flex font-sans text-gray-700">
                {/* Mobile Sidebar Overlay */}
                {isMobileSidebarOpen && (
                    <div
                        className="fixed inset-0 bg-black/50 z-40 md:hidden"
                        onClick={() => setIsMobileSidebarOpen(false)}
                    />
                )}

                {/* Sidebar */}
                <aside
                    className={`
                        fixed md:static inset-y-0 left-0 z-50
                        bg-gradient-to-b from-primary to-[#1e293b] border-r border-white/5 transition-all duration-300 ease-in-out shrink-0
                        flex flex-col shadow-2xl shadow-black/20
                        ${isMobileSidebarOpen ? "translate-x-0 w-64" : "-translate-x-full md:translate-x-0"}
                        ${isSidebarExpanded ? "md:w-64" : "md:w-20"}
                    `}
                >
                    {/* Brand Section & Toggle */}
                    <div
                        className={`flex items-center ${isSidebarExpanded ? "justify-between px-6 py-6" : "justify-center py-6"} transition-all duration-300`}
                    >
                        {/* Logo Area */}
                        <div
                            className={`flex items-center gap-3 overflow-hidden transition-all duration-300 ${!isSidebarExpanded && "w-10"}`}
                        >
                            <div className="relative w-10 h-10 flex-shrink-0">
                                <img
                                    src="/logo.png"
                                    alt="Logo"
                                    className="w-full h-full object-contain rounded-xl"
                                    onError={(e) => {
                                        e.target.style.display = "none";
                                        e.target.nextSibling.style.display =
                                            "flex";
                                    }}
                                />
                                <div className="hidden absolute inset-0 bg-primary rounded-xl items-center justify-center text-white font-bold text-xl shadow-sm shadow-primary/30">
                                    {props.app?.name ? props.app.name[0] : "A"}
                                </div>
                            </div>

                            <div
                                className={`overflow-hidden transition-opacity duration-300 ${isSidebarExpanded ? "opacity-100" : "opacity-0 w-0"}`}
                            >
                                <h1 className="font-bold text-lg text-white leading-tight truncate">
                                    {props.app?.name || "Admin Panel"}
                                </h1>
                            </div>
                        </div>

                        {/* Toggle Button (Desktop Only) */}
                        <button
                            onClick={() =>
                                setIsSidebarExpanded(!isSidebarExpanded)
                            }
                            className={`hidden md:flex text-white/50 hover:text-white transition-colors ${!isSidebarExpanded && "absolute top-6 left-1/2 -translate-x-1/2 mt-12"}`}
                        >
                            {isSidebarExpanded ? (
                                <PanelLeftClose size={20} />
                            ) : (
                                <PanelLeftOpen size={20} />
                            )}
                        </button>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 px-3 py-4 space-y-1">
                        {navigation.map((item) => {
                            const isActive = url.startsWith(item.href);
                            return (
                                <Link
                                    key={item.name}
                                    href={item.href}
                                    className={`
                                        flex items-center px-3 py-3 rounded-xl transition-all duration-200 group relative
                                        ${
                                            isActive
                                                ? "text-white font-semibold bg-white/10"
                                                : "text-white/60 hover:bg-white/5 hover:text-white font-medium"
                                        }
                                        ${!isSidebarExpanded ? "md:justify-center" : ""}
                                    `}
                                    title={!isSidebarExpanded ? item.name : ""}
                                >
                                    <item.icon
                                        size={22}
                                        className={`shrink-0 transition-colors ${isActive ? "text-white" : "text-white/40 group-hover:text-white"} ${isSidebarExpanded ? "mr-3" : "mr-3 md:mr-0"}`}
                                        strokeWidth={isActive ? 2.5 : 2}
                                    />

                                    <span
                                        className={`whitespace-nowrap overflow-hidden transition-all duration-300 ${isSidebarExpanded ? "w-auto opacity-100" : "w-auto opacity-100 md:w-0 md:opacity-0 md:hidden"}`}
                                    >
                                        {item.name}
                                    </span>

                                    {/* Hover Tooltip for Collapsed State */}
                                    {!isSidebarExpanded && (
                                        <div className="hidden md:group-hover:block absolute left-full ml-4 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap pointer-events-none">
                                            {item.name}
                                        </div>
                                    )}
                                </Link>
                            );
                        })}
                    </nav>

                    {/* Admin Profile Section (Mobile Only) */}
                    <div className="md:hidden mt-auto pt-4 border-t border-white/10">
                        <div className="flex items-center gap-3 px-3 py-3">
                            <div className="h-10 w-10 rounded-full overflow-hidden border-2 border-white/20 shrink-0">
                                <img
                                    src={
                                        user?.profile_image ||
                                        "/default/profile.png"
                                    }
                                    alt="Profile"
                                    className="h-full w-full object-cover"
                                    onError={(e) => {
                                        e.target.src = "/default/profile.png";
                                    }}
                                />
                            </div>
                            <div className="flex-1 min-w-0">
                                <p className="text-sm font-semibold text-white truncate">
                                    {user?.name}
                                </p>
                                <p className="text-xs text-white/60 truncate">
                                    {user?.email}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Mobile Close Button (Absolute) */}
                    <button
                        onClick={toggleMobileSidebar}
                        className="absolute top-4 right-4 p-2 text-white/50 md:hidden"
                    >
                        <X size={20} />
                    </button>
                </aside>

                {/* Main Content Area */}
                <div className="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
                    {/* Top Bar */}
                    <header className="shrink-0 h-16 md:h-20 flex items-center justify-between px-6 bg-primary shadow-sm transition-colors duration-300">
                        <div className="flex items-center">
                            <button
                                onClick={toggleMobileSidebar}
                                className="mr-4 text-white/80 hover:text-white md:hidden p-2 -ml-2 hover:bg-white/10 rounded-lg transition-colors"
                            >
                                <Menu size={24} />
                            </button>

                            {/* Back to Website Link */}
                            <Link
                                onClick={(e) => {
                                    e.preventDefault();
                                    router.visit("/");
                                }}
                                className="hidden md:flex items-center text-sm font-medium text-white/80 hover:text-white transition-colors"
                            >
                                <Home size={16} className="mr-2" />
                                Back to Website
                            </Link>
                        </div>

                        {/* Right Side: Notifications & Profile */}
                        <div className="flex items-center space-x-4">
                            {/* Notification Dropdown */}
                            <div className="relative">
                                <button
                                    onClick={() => {
                                        setIsNotificationsOpen(
                                            !isNotificationsOpen,
                                        );
                                        setIsProfileOpen(false);
                                    }}
                                    className={`relative p-2 transition-colors rounded-full ${isNotificationsOpen ? "bg-white/20 text-white" : "text-white/80 hover:text-white hover:bg-white/10"}`}
                                >
                                    <Bell size={25} />
                                    {user?.unread_notifications_count > 0 && (
                                        <span className="absolute top-2 right-0 md:right-2 h-2.5 w-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                                    )}
                                </button>

                                {isNotificationsOpen && (
                                    <>
                                        <div
                                            className="fixed inset-0 z-30 cursor-default"
                                            onClick={() =>
                                                setIsNotificationsOpen(false)
                                            }
                                        />
                                        <div className="fixed left-1/2 top-16 -translate-x-1/2 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:translate-x-0 mt-2 w-[calc(100vw-2rem)] sm:w-96 max-w-md bg-white rounded-xl shadow-lg border border-gray-100 py-2 z-40 animate-fadeIn overflow-hidden">
                                            <div className="px-4 py-2 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                                                <h3 className="font-semibold text-sm text-gray-900">
                                                    Notifications
                                                </h3>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-[10px] sm:text-xs text-gray-500">
                                                        {user?.unread_notifications_count ||
                                                            0}{" "}
                                                        New
                                                    </span>
                                                    {user?.unread_notifications_count >
                                                        0 && (
                                                        <>
                                                            <span className="text-gray-300">
                                                                |
                                                            </span>
                                                            <button
                                                                onClick={() => {
                                                                    router.post(
                                                                        "/admin/notification/read-all",
                                                                    );
                                                                }}
                                                                className="text-[10px] font-bold text-primary hover:underline uppercase tracking-tight"
                                                            >
                                                                Mark all read
                                                            </button>
                                                        </>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="max-h-[300px] overflow-y-auto">
                                                {notifications &&
                                                notifications.length > 0 ? (
                                                    notifications.map(
                                                        (notification) => (
                                                            <Link
                                                                href={`/admin/notification/${notification.id}`}
                                                                key={
                                                                    notification.id
                                                                }
                                                                className={`block px-4 py-3 border-b border-gray-50 last:border-0 hover:bg-gray-50 transition-colors cursor-pointer ${notification.read_at ? "opacity-60" : "bg-blue-50/30"}`}
                                                            >
                                                                <p
                                                                    className={`text-xs sm:text-sm flex justify-between items-center ${notification.read_at ? "text-gray-500 font-normal" : "text-gray-900 font-semibold"}`}
                                                                >
                                                                    {
                                                                        notification.title
                                                                    }
                                                                    <span className="text-[9px] sm:text-xs shrink-0">
                                                                        {new Date(
                                                                            notification.created_at,
                                                                        ).toLocaleDateString()}
                                                                    </span>
                                                                </p>
                                                            </Link>
                                                        ),
                                                    )
                                                ) : (
                                                    <div className="px-4 py-8 text-center text-gray-500 text-sm">
                                                        <Bell
                                                            size={24}
                                                            className="mx-auto mb-2 opacity-20"
                                                        />
                                                        No notifications yet
                                                    </div>
                                                )}
                                            </div>

                                            <div className="p-2 border-t border-gray-50 text-center">
                                                <Link
                                                    href="/admin/notification"
                                                    className="text-xs font-semibold text-primary hover:underline"
                                                >
                                                    View All
                                                </Link>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>

                            {/* Profile Dropdown */}
                            <div className="relative">
                                <button
                                    onClick={() =>
                                        setIsProfileOpen(!isProfileOpen)
                                    }
                                    className="h-10 w-10 rounded-full overflow-hidden border-2 border-white/20 shadow-sm focus:outline-none focus:ring-2 focus:ring-white/20 transition-all"
                                >
                                    <img
                                        src={
                                            user?.profile_image ||
                                            "/default/profile.png"
                                        }
                                        alt="Profile"
                                        className="h-full w-full object-cover"
                                        onError={(e) => {
                                            e.target.src =
                                                "/default/profile.png";
                                        }}
                                    />
                                </button>

                                {/* Dropdown Menu */}
                                {isProfileOpen && (
                                    <>
                                        <div
                                            className="fixed inset-0 z-30 cursor-default"
                                            onClick={() =>
                                                setIsProfileOpen(false)
                                            }
                                        />
                                        <div className="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-40 animate-fadeIn">
                                            <div className="px-4 py-2 border-b border-gray-50 mb-1">
                                                <h3 className="text-sm sm:text-base font-bold text-gray-900 truncate">
                                                    {user?.name}
                                                </h3>
                                                <p className="text-xs text-gray-500 truncate">
                                                    {user?.email}
                                                </p>
                                            </div>

                                            <Link
                                                href="/admin/profile"
                                                className={`flex items-center px-4 py-2 text-sm transition-colors ${url === "/admin/profile" ? "text-primary bg-primary/5 font-semibold" : "text-gray-700 hover:bg-gray-50 hover:text-primary"}`}
                                            >
                                                <User
                                                    size={16}
                                                    className="mr-2"
                                                />
                                                Admin Profile
                                            </Link>
                                            <Link
                                                href="/admin/profile/password"
                                                className={`flex items-center px-4 py-2 text-sm transition-colors ${url === "/admin/profile/password" ? "text-primary bg-primary/5 font-semibold" : "text-gray-700 hover:bg-gray-50 hover:text-primary"}`}
                                            >
                                                <Key
                                                    size={16}
                                                    className="mr-2"
                                                />
                                                Change Password
                                            </Link>

                                            <div className="border-t border-gray-50 mt-1 pt-1">
                                                <button
                                                    onClick={handleLogout}
                                                    className="flex w-full items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors text-left"
                                                >
                                                    <LogOut
                                                        size={16}
                                                        className="mr-2"
                                                    />
                                                    Logout
                                                </button>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </div>
                        </div>
                    </header>

                    {/* Page Scroll Area */}
                    <main className="flex-1 overflow-auto px-4 sm:px-6 lg:px-8 pt-4 md:pt-6 pb-8">
                        {children}
                    </main>
                </div>
            </div>
        </MainLayout>
    );
}

