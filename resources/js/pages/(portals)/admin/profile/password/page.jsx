import React, { useState } from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, useForm } from "@inertiajs/react";
import {
    Lock,
    ShieldCheck,
    Key,
    Save,
    Loader2,
    Eye,
    EyeOff,
} from "lucide-react";

export default function Page() {
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
        put("/admin/profile/password", {
            onSuccess: () => reset(),
        });
    };

    return (
        <AdminLayout>
            <Head title="Change Password" />
            <div className="max-w-4xl mx-auto py-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-900 font-sans">
                        Change Password
                    </h1>
                    <p className="text-gray-500 font-sans">
                        Update your account security by changing your password.
                    </p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <form onSubmit={submit} className="p-8">
                        <div className="max-w-xl space-y-8">
                            {/* Current Password */}
                            <div className="space-y-2">
                                <label className="text-sm font-semibold text-gray-700 flex items-center gap-2 font-sans">
                                    <Key size={16} className="text-primary" />{" "}
                                    Current Password
                                </label>
                                <div className="relative">
                                    <input
                                        type={showCurrent ? "text" : "password"}
                                        value={data.current_password}
                                        onChange={(e) =>
                                            setData(
                                                "current_password",
                                                e.target.value,
                                            )
                                        }
                                        className={`w-full px-4 py-3 rounded-xl border ${errors.current_password ? "border-red-500" : "border-gray-200"} focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-sans pr-12`}
                                        placeholder="••••••••"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowCurrent(!showCurrent)
                                        }
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors focus:outline-none"
                                    >
                                        {showCurrent ? (
                                            <EyeOff size={18} />
                                        ) : (
                                            <Eye size={18} />
                                        )}
                                    </button>
                                </div>
                                {errors.current_password && (
                                    <p className="text-red-500 text-xs mt-1 font-sans">
                                        {errors.current_password}
                                    </p>
                                )}
                            </div>

                            {/* New Password */}
                            <div className="space-y-2">
                                <label className="text-sm font-semibold text-gray-700 flex items-center gap-2 font-sans">
                                    <Lock size={16} className="text-primary" />{" "}
                                    New Password
                                </label>
                                <div className="relative">
                                    <input
                                        type={showNew ? "text" : "password"}
                                        value={data.password}
                                        onChange={(e) =>
                                            setData("password", e.target.value)
                                        }
                                        className={`w-full px-4 py-3 rounded-xl border ${errors.password ? "border-red-500" : "border-gray-200"} focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-sans pr-12`}
                                        placeholder="Min. 8 characters"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowNew(!showNew)}
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors focus:outline-none"
                                    >
                                        {showNew ? (
                                            <EyeOff size={18} />
                                        ) : (
                                            <Eye size={18} />
                                        )}
                                    </button>
                                </div>
                                {errors.password && (
                                    <p className="text-red-500 text-xs mt-1 font-sans">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            {/* Confirm New Password */}
                            <div className="space-y-2">
                                <label className="text-sm font-semibold text-gray-700 flex items-center gap-2 font-sans">
                                    <ShieldCheck
                                        size={16}
                                        className="text-primary"
                                    />{" "}
                                    Confirm New Password
                                </label>
                                <div className="relative">
                                    <input
                                        type={showConfirm ? "text" : "password"}
                                        value={data.password_confirmation}
                                        onChange={(e) =>
                                            setData(
                                                "password_confirmation",
                                                e.target.value,
                                            )
                                        }
                                        className={`w-full px-4 py-3 rounded-xl border ${errors.password_confirmation ? "border-red-500" : "border-gray-200"} focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all font-sans pr-12`}
                                        placeholder="••••••••"
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowConfirm(!showConfirm)
                                        }
                                        className="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary transition-colors focus:outline-none"
                                    >
                                        {showConfirm ? (
                                            <EyeOff size={18} />
                                        ) : (
                                            <Eye size={18} />
                                        )}
                                    </button>
                                </div>
                                {errors.password_confirmation && (
                                    <p className="text-red-500 text-xs mt-1 font-sans">
                                        {errors.password_confirmation}
                                    </p>
                                )}
                            </div>

                            {/* Submit Button */}
                            <div className="pt-4 flex justify-start">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex items-center gap-2 px-8 py-3 bg-primary text-white rounded-xl font-semibold shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed font-sans"
                                >
                                    {processing ? (
                                        <Loader2
                                            className="animate-spin"
                                            size={20}
                                        />
                                    ) : (
                                        <Save size={20} />
                                    )}
                                    Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
