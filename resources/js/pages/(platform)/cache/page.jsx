import React from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, router } from "@inertiajs/react";
import { useModal } from "@/Contexts/ModalContext";
import { Zap, AlertTriangle, RefreshCw, LayoutTemplate, Settings, Link as LinkIcon, Database } from "lucide-react";

export default function CacheManagementPage() {
    const { openModal, closeModal } = useModal();

    const confirmClear = (type, title, description) => {
        openModal(
            <div className="p-6">
                <div className="flex items-center gap-4 mb-4 text-amber-600 bg-amber-50 p-4 rounded-xl border border-amber-200">
                    <AlertTriangle size={32} />
                    <div>
                        <h3 className="text-lg font-bold">Confirm Action</h3>
                        <p className="text-sm opacity-80">You are about to flush application memory.</p>
                    </div>
                </div>
                <div className="mb-6 text-gray-700">
                    <h4 className="font-bold text-gray-900 mb-1">{title}</h4>
                    <p className="text-sm">{description}</p>
                </div>
                <div className="flex justify-end gap-3 mt-6">
                    <button
                        onClick={closeModal}
                        className="px-4 py-2 bg-gray-100 font-semibold text-gray-700 rounded-lg hover:bg-gray-200 transition"
                    >
                        Cancel
                    </button>
                    <button
                        onClick={() => {
                            router.post('/admin/cache/clear', { type: type });
                            closeModal();
                        }}
                        className="px-4 py-2 font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition flex items-center gap-2"
                    >
                        <RefreshCw size={16} />
                        Clear {title}
                    </button>
                </div>
            </div>,
            { size: "md" }
        );
    };

    return (
        <AdminLayout>
            <Head title="Cache Management" />
            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                <div>
                    <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                        Cache Management
                    </h1>
                    <p className="text-sm text-gray-500 mt-1">
                        Clear application caches when changes to configuration or code are not reflecting.
                    </p>
                </div>

                <div className="bg-amber-50 border border-amber-200 p-4 rounded-xl flex items-start gap-3">
                    <AlertTriangle className="text-amber-600 shrink-0 mt-0.5" size={20} />
                    <div>
                        <h4 className="text-sm font-bold text-amber-800">Use with Caution</h4>
                        <p className="text-xs text-amber-700 mt-1">
                            Clearing cache in a production environment may cause a temporary spike in loading times and database operations as memory stores are rebuilt.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {/* All / Optimize */}
                    <CacheCard 
                        title="Optimize All"
                        description="Runs optimize:clear which flushes all of the application's caching layers simultaneously."
                        icon={<Zap className="text-amber-500" size={24} />}
                        onClick={() => confirmClear('all', 'Optimization Cache', 'This will flush configuration, views, events, routes, and compiled services. It is the most comprehensive flush available.')}
                        primary
                    />

                    {/* View Cache */}
                    <CacheCard 
                        title="View Cache"
                        description="Clears all compiled Blade templates. Use this if UI changes to your *.blade.php files aren't showing up."
                        icon={<LayoutTemplate className="text-indigo-500" size={24} />}
                        onClick={() => confirmClear('view', 'View Cache', 'This will delete all compiled Blade templates in storage/framework/views/.')}
                    />

                    {/* Config Cache */}
                    <CacheCard 
                        title="Configuration Cache"
                        description="Clears the cached configuration files. Use this after changing .env variables or config array values."
                        icon={<Settings className="text-blue-500" size={24} />}
                        onClick={() => confirmClear('config', 'Configuration Cache', 'This removes the bootstrap/cache/config.php file.')}
                    />

                    {/* Route Cache */}
                    <CacheCard 
                        title="Route Cache"
                        description="Clears the cached routes. Use this if new API or web routes are throwing 404 Not Found errors."
                        icon={<LinkIcon className="text-emerald-500" size={24} />}
                        onClick={() => confirmClear('route', 'Route Cache', 'This removes the bootstrap/cache/routes-v7.php file.')}
                    />

                    {/* App Cache */}
                    <CacheCard 
                        title="Application Cache"
                        description="Clears the main application cache (Redis/File/Database memstore). Flushes all Cache::put values."
                        icon={<Database className="text-rose-500" size={24} />}
                        onClick={() => confirmClear('application', 'Application Cache', 'This truncates your primary cache datastore entirely.')}
                    />
                </div>
            </div>
        </AdminLayout>
    );
}

function CacheCard({ title, description, icon, onClick, primary = false }) {
    return (
        <div className={`border p-6 rounded-2xl flex flex-col justify-between ${primary ? 'bg-amber-600 border-amber-700 text-white shadow-lg' : 'bg-white border-gray-200 shadow-sm'}`}>
            <div>
                <div className={`w-12 h-12 rounded-xl flex items-center justify-center mb-4 ${primary ? 'bg-white/20' : 'bg-gray-50 border border-gray-100'}`}>
                    {primary ? <Zap className="text-white fill-white" size={24} /> : icon}
                </div>
                <h3 className={`font-bold text-lg mb-2 ${primary ? 'text-white' : 'text-gray-900'}`}>{title}</h3>
                <p className={`text-sm mb-6 ${primary ? 'text-amber-100' : 'text-gray-500'}`}>{description}</p>
            </div>
            
            <button 
                onClick={onClick}
                className={`w-full py-2.5 px-4 font-semibold rounded-xl transition flex items-center justify-center gap-2 ${
                    primary 
                        ? 'bg-white text-amber-700 hover:bg-amber-50' 
                        : 'bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200 hover:border-gray-300'
                }`}
            >
                <RefreshCw size={16} />
                Clear {primary ? 'All Caches' : 'Cache'}
            </button>
        </div>
    );
}
