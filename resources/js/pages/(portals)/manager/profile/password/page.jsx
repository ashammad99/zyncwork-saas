import React, { useState } from "react";
import ManagerLayout from "../../layout";
import { Head, useForm } from "@inertiajs/react";
import { Lock, ShieldCheck, Key, Save, Loader2, Eye, EyeOff } from "lucide-react";

export default function PasswordPage() {
    const [showCurrent, setShowCurrent] = useState(false);
    const [showNew, setShowNew] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });

    const submit = (e) => {
        e.preventDefault();
        put("/user/profile/password", { onSuccess: () => reset() });
    };

    return (
        <ManagerLayout>
            <Head title="Change Password" />
            <div className="max-w-4xl mx-auto py-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-900">Change Password</h1>
                    <p className="text-gray-500">Update your account security by changing your password.</p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <form onSubmit={submit} className="p-8">
                        <div className="max-w-xl space-y-8">
                            <PasswordField
                                label="Current Password"
                                icon={<Key size={16} className="text-primary" />}
                                value={data.current_password}
                                onChange={(e) => setData("current_password", e.target.value)}
                                show={showCurrent}
                                onToggle={() => setShowCurrent(!showCurrent)}
                                error={errors.current_password}
                            />
                            <PasswordField
                                label="New Password"
                                icon={<Lock size={16} className="text-primary" />}
                                value={data.password}
                                onChange={(e) => setData("password", e.target.value)}
                                show={showNew}
                                onToggle={() => setShowNew(!showNew)}
                                placeholder="Min. 8 characters"
                                error={errors.password}
                            />
                            <PasswordField
                                label="Confirm New Password"
                                icon={<ShieldCheck size={16} className="text-primary" />}
                                value={data.password_confirmation}
                                onChange={(e) => setData("password_confirmation", e.target.value)}
                                show={showConfirm}
                                onToggle={() => setShowConfirm(!showConfirm)}
                                error={errors.password_confirmation}
                            />

                            <div className="pt-4">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex items-center gap-2 px-8 py-3 bg-primary text-white rounded-xl font-semibold shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {processing ? <Loader2 className="animate-spin" size={20} /> : <Save size={20} />}
                                    Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </ManagerLayout>
    );
}

function PasswordField({ label, icon, value, onChange, show, onToggle, placeholder = "••••••••", error }) {
    return (
        <div className="space-y-2">
            <label className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                {icon} {label}
            </label>
            <div className="relative">
                <input
                    type={show ? "text" : "password"}
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                    className={`w-full px-4 py-3 rounded-xl border ${error ? "border-red-500" : "border-gray-200"} focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all pr-12`}
                />
                <button
                    type="button"
                    onClick={onToggle}
                    className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors focus:outline-none"
                >
                    {show ? <EyeOff size={18} /> : <Eye size={18} />}
                </button>
            </div>
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}
