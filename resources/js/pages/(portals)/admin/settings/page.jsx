import React, { useEffect, useState } from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, useForm, usePage } from "@inertiajs/react";
import { Settings, Save, RotateCcw, CheckCircle2 } from "lucide-react";

export default function SettingsPage({ groups = {}, values = {} }) {
    const { flash } = usePage().props;
    const [showSuccess, setShowSuccess] = useState(false);

    const { data, setData, patch, processing, errors, isDirty, reset } = useForm(values);

    useEffect(() => {
        if (flash?.success) {
            setShowSuccess(true);
            const t = setTimeout(() => setShowSuccess(false), 3000);
            return () => clearTimeout(t);
        }
    }, [flash]);

    const submit = (e) => {
        e.preventDefault();
        patch("/admin/settings", { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="System Settings" />

            <div className="max-w-7xl mx-auto space-y-6 md:pb-12">
                {/* Header */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-gray-900 to-gray-600">
                            System Configuration
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Manage global platform settings and integrations.
                        </p>
                    </div>

                    <div className="hidden md:flex items-center gap-2 text-xs text-gray-400">
                        <Settings size={16} />
                        <span>{Object.keys(data).length} settings</span>
                    </div>
                </div>

                {/* Flash success */}
                {showSuccess && (
                    <div className="flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        <CheckCircle2 size={18} />
                        Settings updated successfully.
                    </div>
                )}

                {/* Form */}
                <form onSubmit={submit} className="space-y-6">
                    {Object.entries(groups).map(([groupKey, group]) => (
                        <SettingsGroup
                            key={groupKey}
                            group={group}
                            data={data}
                            setData={setData}
                            errors={errors}
                        />
                    ))}

                    {/* Sticky action bar */}
                    <div className="sticky bottom-0 z-10 flex items-center justify-between border-t border-gray-200 bg-white/95 px-4 py-4 backdrop-blur rounded-b-2xl">
                        <p className="text-xs text-gray-500">
                            {isDirty ? (
                                <span className="text-amber-600">● You have unsaved changes</span>
                            ) : (
                                <span>All changes saved</span>
                            )}
                        </p>

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => reset()}
                                disabled={!isDirty || processing}
                                className="inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
                            >
                                <RotateCcw size={16} />
                                Reset
                            </button>
                            <button
                                type="submit"
                                disabled={!isDirty || processing}
                                className="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50 transition-colors"
                            >
                                <Save size={16} />
                                {processing ? "Saving…" : "Save Changes"}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

/* ───────────────────────── group section ───────────────────────── */

function SettingsGroup({ group, data, setData, errors }) {
    return (
        <section className="bg-white border border-gray-200 shadow-sm rounded-2xl overflow-hidden">
            <header className="border-b border-gray-100 px-6 py-4">
                <h2 className="text-base font-semibold text-gray-900">{group.label}</h2>
                <p className="mt-0.5 text-sm text-gray-500">{group.description}</p>
            </header>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-5 px-6 py-5">
                {group.fields.map((field) => (
                    <Field
                        key={field.key}
                        field={field}
                        value={data[field.key]}
                        onChange={(v) => setData(field.key, v)}
                        error={errors[field.key]}
                    />
                ))}
            </div>
        </section>
    );
}

/* ───────────────────────── single field ───────────────────────── */

function Field({ field, value, onChange, error }) {
    const baseInput =
        "block w-full rounded-md border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary focus:ring-1 focus:ring-primary/30 focus:outline-none transition-colors";
    const errorRing = error ? "border-red-300 focus:border-red-500 focus:ring-red-200" : "";

    /* boolean → checkbox spans both columns */
    if (field.type === "boolean") {
        const checked = value === true || value === "1" || value === 1;
        return (
            <label className="md:col-span-2 flex items-start gap-3 rounded-lg border border-gray-100 bg-gray-50/50 p-3 hover:bg-gray-50 cursor-pointer transition-colors">
                <input
                    type="checkbox"
                    checked={checked}
                    onChange={(e) => onChange(e.target.checked)}
                    className="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary/40"
                />
                <div className="flex-1">
                    <div className="text-sm font-medium text-gray-900">{field.label}</div>
                    <div className="font-mono text-xs text-gray-400 mt-0.5">{field.key}</div>
                    {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
                </div>
            </label>
        );
    }

    return (
        <div>
            <label className="mb-1.5 flex items-center justify-between">
                <span className="text-sm font-medium text-gray-900">{field.label}</span>
                <span className="font-mono text-xs text-gray-400">{field.key}</span>
            </label>

            {field.type === "select" ? (
                <select
                    value={value ?? ""}
                    onChange={(e) => onChange(e.target.value)}
                    className={`${baseInput} ${errorRing}`}
                >
                    {field.options.map((opt) => (
                        <option key={opt} value={opt}>
                            {opt}
                        </option>
                    ))}
                </select>
            ) : (
                <input
                    type={
                        field.type === "number"
                            ? "number"
                            : field.type === "email"
                                ? "email"
                                : "text"
                    }
                    value={value ?? ""}
                    min={field.min}
                    max={field.max}
                    onChange={(e) => onChange(e.target.value)}
                    className={`${baseInput} ${errorRing}`}
                />
            )}

            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
