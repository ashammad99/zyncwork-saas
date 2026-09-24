import React, { createContext, useContext, useState } from "react";

const ModalContext = createContext();

/**
 * Modal Provider
 *
 * This context is "The Brain" for handling Pop-ups (Modals) globally.
 * It allows you to open a popup window from ANY component without passing props down.
 *
 * Mainly used for:
 * - Confirmation dialogs (Are you sure?)
 * - Forms in popups
 * - Alert messages
 */
export function ModalProvider({ children }) {
    const [isOpen, setIsOpen] = useState(false);
    const [content, setContent] = useState(null);
    const [options, setOptions] = useState({
        size: "md",
        closeOnOverlay: true,
        showCloseButton: true,
    });

    const openModal = (modalContent, modalOptions = {}) => {
        setContent(modalContent);
        setOptions({ ...options, ...modalOptions });
        setIsOpen(true);
    };

    const closeModal = () => {
        setIsOpen(false);
        setTimeout(() => setContent(null), 200); // Clear after animation
    };

    return (
        <ModalContext.Provider
            value={{ isOpen, content, options, openModal, closeModal }}
        >
            {children}
        </ModalContext.Provider>
    );
}

/**
 * Hook to access modal controls
 *
 * @example
 * const { openModal, closeModal } = useModal();
 *
 * openModal(
 *   <div>My content</div>,
 *   { size: 'lg', closeOnOverlay: false }
 * );
 */
export function useModal() {
    const context = useContext(ModalContext);
    if (!context) {
        throw new Error("useModal must be used within ModalProvider");
    }
    return context;
}
