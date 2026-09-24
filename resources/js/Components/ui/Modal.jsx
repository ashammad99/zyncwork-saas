import React, { useEffect } from "react";
import { useModal } from "../../Contexts/ModalContext";

/**
 * Modal Component (The actual Pop-up Window)
 *
 * This is the physical "box" that pops up on the screen.
 * It gets its content from the ModalContext (The Brain).
 */
export default function Modal() {
    const { isOpen, content, options, closeModal } = useModal();

    // Close on ESC key
    useEffect(() => {
        const handleEsc = (e) => {
            if (e.key === "Escape" && isOpen) {
                closeModal();
            }
        };
        window.addEventListener("keydown", handleEsc);
        return () => window.removeEventListener("keydown", handleEsc);
    }, [isOpen, closeModal]);

    // Prevent body scroll when modal is open
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = "hidden";
        } else {
            document.body.style.overflow = "unset";
        }
        return () => {
            document.body.style.overflow = "unset";
        };
    }, [isOpen]);

    if (!isOpen) return null;

    const sizeClasses = {
        sm: "max-w-md",
        md: "max-w-lg",
        lg: "max-w-2xl",
        xl: "max-w-4xl",
        full: "max-w-full mx-4",
    };

    const handleOverlayClick = (e) => {
        if (options.closeOnOverlay && e.target === e.currentTarget) {
            closeModal();
        }
    };

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center backdrop-blur-sm bg-opacity-50 p-4 animate-fadeIn"
            onClick={handleOverlayClick}
        >
            <div
                className={`relative bg-white rounded-lg shadow-2xl ${sizeClasses[options.size]} w-full max-h-[90vh] overflow-auto animate-slideUp`}
            >
                {/* Close button */}
                {options.showCloseButton && (
                    <button
                        onClick={closeModal}
                        className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 transition-colors z-10"
                        aria-label="Close modal"
                    >
                        <svg
                            className="w-6 h-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                )}

                {/* Modal content */}
                <div className="p-6">{content}</div>
            </div>
        </div>
    );
}
