import React from "react";
import AdminLayout from "@/pages/(portals)/admin/layout";
import { Head, useForm, usePage } from "@inertiajs/react";
import { User, Mail, Phone, Camera, Save, Loader2 } from "lucide-react";

export default function Page() {
    const { props } = usePage();
    const user = props.auth.user;

    const { data, setData, post, processing, errors } = useForm({
        name: user.name || "",
        email: user.email || "",
        phone: user.phone || "",
        profile_image: null,
        _method: "PUT", // For spoofing PUT in multipart/form-data
    });

    const submit = (e) => {
        e.preventDefault();
        post("/admin/profile", {
            forceFormData: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Profile" />
            <div className="max-w-4xl mx-auto py-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold text-gray-900">
                        Admin Profile
                    </h1>
                    <p className="text-gray-500">
                        Manage your account information and profile details.
                    </p>
                </div>

                <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <form onSubmit={submit} className="p-8">
                        {/* Profile Image Section */}
                        <div className="flex flex-col md:flex-row items-center gap-8 mb-10 pb-10 border-b border-gray-50">
                            <div className="relative group">
                                <div className="h-32 w-32 rounded-2xl overflow-hidden bg-gray-100 border-4 border-white shadow-md ring-1 ring-gray-100">
                                    <img
                                        src={
                                            data.profile_image
                                                ? URL.createObjectURL(
                                                      data.profile_image,
                                                  )
                                                : user.profile_image ||
                                                  "/default/profile.png"
                                        }
                                        alt="Profile"
                                        className="h-full w-full object-cover transition-transform group-hover:scale-105"
                                    />
                                </div>
                                <label className="absolute -bottom-2 -right-2 p-2 bg-primary text-white rounded-xl shadow-lg cursor-pointer hover:bg-primary-hover transition-colors">
                                    <Camera size={18} />
                                    <input
                                        type="file"
                                        className="hidden"
                                        onChange={(e) =>
                                            setData(
                                                "profile_image",
                                                e.target.files[0],
                                            )
                                        }
                                        accept="image/*"
                                    />
                                </label>
                            </div>
                            <div className="flex-1 text-center md:text-left">
                                <h3 className="text-lg font-semibold text-gray-900">
                                    Profile Photo
                                </h3>
                                <p className="text-sm text-gray-500 mt-1 mb-4">
                                    Update your profile picture. Recommended
                                    size: 400x400px.
                                </p>
                                {errors.profile_image && (
                                    <p className="text-red-500 text-xs mt-1">
                                        {errors.profile_image}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Form Fields */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div className="space-y-2">
                                <label className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                    <User size={16} className="text-primary" />{" "}
                                    Full Name
                                </label>
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData("name", e.target.value)
                                    }
                                    className={`w-full px-4 py-3 rounded-xl border ${errors.name ? "border-red-500" : "border-gray-200"} focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all`}
                                    placeholder="Enter your name"
                                />
                                {errors.name && (
                                    <p className="text-red-500 text-xs mt-1">
                                        {errors.name}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                    <Mail size={16} className="text-primary" />{" "}
                                    Email Address
                                </label>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData("email", e.target.value)
                                    }
                                    className={`w-full px-4 py-3 rounded-xl border ${errors.email ? "border-red-500" : "border-gray-200"} focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all`}
                                    placeholder="admin@example.com"
                                />
                                {errors.email && (
                                    <p className="text-red-500 text-xs mt-1">
                                        {errors.email}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <label className="text-sm font-semibold text-gray-700 flex items-center gap-2">
                                    <Phone size={16} className="text-primary" />{" "}
                                    Phone Number
                                </label>
                                <input
                                    type="text"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData("phone", e.target.value)
                                    }
                                    className={`w-full px-4 py-3 rounded-xl border ${errors.phone ? "border-red-500" : "border-gray-200"} focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all`}
                                    placeholder="+1 (555) 000-0000"
                                />
                                {errors.phone && (
                                    <p className="text-red-500 text-xs mt-1">
                                        {errors.phone}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Submit Button */}
                        <div className="mt-10 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="flex items-center gap-2 px-8 py-3 bg-primary text-white rounded-xl font-semibold shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {processing ? (
                                    <Loader2
                                        className="animate-spin"
                                        size={20}
                                    />
                                ) : (
                                    <Save size={20} />
                                )}
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
