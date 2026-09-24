import React from "react";

/**
 * Loading Spinner Component
 *
 * Displays a centered loading spinner overlay.
 * Used for global page transitions and async operations.
 */
export default function LoadingSpinner() {
    return (
        <div className="fixed inset-0 flex items-center justify-center bg-white bg-opacity-80 z-50">
            <div className="relative">
                <div className="w-16 h-16 border-t-4 border-primary border-solid rounded-full animate-spin"></div>
            </div>
        </div>
    );
}
