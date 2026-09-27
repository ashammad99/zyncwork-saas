import React from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head } from "@inertiajs/react";
import { Activity, Server, Database, Zap, Cpu, Globe, Key, Clock, Settings, HardDrive } from "lucide-react";

export default function SystemHealthPage({ metrics }) {
    return (
        <AdminLayout>
            <Head title="System Health" />
            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                        System Health
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Monitor application environment, software versions, and infrastructure vitals.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {/* Status & Environment */}
                    <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                                <Globe size={20} />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">Application Environment</h3>
                                <p className="text-xs text-gray-500">Core configuration</p>
                            </div>
                        </div>
                        <div className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Environment</span>
                                <span className="font-semibold text-gray-900 capitalize">{metrics.environment}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Debug Mode</span>
                                <span className={`font-semibold ${metrics.debug_mode === 'Enabled' ? 'text-amber-600' : 'text-green-600'}`}>{metrics.debug_mode}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Timezone</span>
                                <span className="font-semibold text-gray-900">{metrics.timezone}</span>
                            </div>
                        </div>
                    </div>

                    {/* Software Versions */}
                    <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center text-purple-600">
                                <Server size={20} />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">Software Versions</h3>
                                <p className="text-xs text-gray-500">Framework and language stacks</p>
                            </div>
                        </div>
                        <div className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Laravel Version</span>
                                <span className="font-semibold text-gray-900">{metrics.laravel_version}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">PHP Version</span>
                                <span className="font-semibold text-gray-900">{metrics.php_version}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">App Version</span>
                                <span className="font-semibold text-gray-900">v{metrics.app_version}</span>
                            </div>
                        </div>
                    </div>

                    {/* Infrastructure */}
                    <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6 border-t-4 border-t-primary">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary">
                                <HardDrive size={20} />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">Infrastructure</h3>
                                <p className="text-xs text-gray-500">Server hardware</p>
                            </div>
                        </div>
                        <div className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Server OS</span>
                                <span className="font-semibold text-gray-900 truncate pl-4" title={metrics.server_os}>{metrics.server_os}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Memory Limit</span>
                                <span className="font-semibold text-gray-900">{metrics.memory_limit}</span>
                            </div>
                        </div>
                    </div>

                    {/* Database Connect */}
                    <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                <Database size={20} />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">Database Connection</h3>
                                <p className="text-xs text-gray-500">Primary datastore</p>
                            </div>
                        </div>
                        <div className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Driver</span>
                                <span className="font-semibold text-gray-900 capitalize">{metrics.database.connection}</span>
                            </div>
                            <div className="flex justify-between text-sm items-center">
                                <span className="text-gray-500">Connection Status</span>
                                <span className={`px-2 py-1 rounded text-xs font-bold ${metrics.database.status === 'Connected' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                    {metrics.database.status}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Cache Connect */}
                    <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
                                <Zap size={20} />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">Cache Connection</h3>
                                <p className="text-xs text-gray-500">Memory store</p>
                            </div>
                        </div>
                        <div className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Driver</span>
                                <span className="font-semibold text-gray-900 capitalize">{metrics.cache.driver}</span>
                            </div>
                            <div className="flex justify-between text-sm items-center">
                                <span className="text-gray-500">Connection Status</span>
                                <span className={`px-2 py-1 rounded text-xs font-bold ${metrics.cache.status === 'Connected' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                    {metrics.cache.status}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    {/* Queue */}
                    <div className="bg-white border border-gray-200 shadow-sm rounded-2xl p-6">
                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-600">
                                <Clock size={20} />
                            </div>
                            <div>
                                <h3 className="font-bold text-gray-900">Queue & Background</h3>
                                <p className="text-xs text-gray-500">Async processing workers</p>
                            </div>
                        </div>
                        <div className="space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Connection</span>
                                <span className="font-semibold text-gray-900 capitalize">{metrics.queue}</span>
                            </div>
                            <div className="flex mt-3 bg-surface p-3 rounded-lg border border-surface-foreground/5 text-xs text-gray-500">
                                <Settings size={14} className="mr-2 shrink-0 mt-0.5" />
                                <span>Note: Verify that `php artisan queue:work` is actively running via Supervisor in production.</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </AdminLayout>
    );
}
