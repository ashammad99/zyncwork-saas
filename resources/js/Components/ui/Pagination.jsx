import React from "react";
import { Link } from "@inertiajs/react";

export default function Pagination({ links }) {
    if (!links.links || links.links.length <= 3) return null;

    return (
        <div className="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div className="flex-1 flex justify-between sm:hidden">
                <Link
                    href={links.prev_page_url || "#"}
                    className={`relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ${
                        !links.prev_page_url && "opacity-50 pointer-events-none"
                    }`}
                >
                    Previous
                </Link>
                <Link
                    href={links.next_page_url || "#"}
                    className={`ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 ${
                        !links.next_page_url && "opacity-50 pointer-events-none"
                    }`}
                >
                    Next
                </Link>
            </div>
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm text-gray-700">
                        Showing{" "}
                        <span className="font-medium">{links.from}</span> to{" "}
                        <span className="font-medium">{links.to}</span> of{" "}
                        <span className="font-medium">{links.total}</span>{" "}
                        results
                    </p>
                </div>
                <div>
                    <nav
                        className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px"
                        aria-label="Pagination"
                    >
                        {links.links.map((link, i) => (
                            <Link
                                key={i}
                                href={link.url || "#"}
                                className={`
                                    relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                    ${
                                        link.active
                                            ? "z-10 bg-primary/10 border-primary text-primary"
                                            : "bg-white border-gray-300 text-gray-500 hover:bg-gray-50"
                                    }
                                    ${!link.url && "opacity-50 pointer-events-none"}
                                    ${i === 0 && "rounded-l-md"}
                                    ${i === links.links.length - 1 && "rounded-r-md"}
                                `}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </nav>
                </div>
            </div>
        </div>
    );
}
