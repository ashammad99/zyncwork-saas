import React, { useState, useEffect } from "react";
import Toast from "../ui/Toast";
import LoadingSpinner from "../ui/LoadingSpinner";
import Modal from "../ui/Modal";
import { ModalProvider } from "../../Contexts/ModalContext";
import { router } from "@inertiajs/react";

/**
 * Main App Layout
 *
 * Wraps all pages with common components like Toast, Modal, and loading indicator.
 * Use this as a persistent layout for your pages.
 *
 * @example
 * // In your page component
 * import MainLayout from '@/Layouts/MainLayout';
 *
 * YourPage.layout = page => <MainLayout>{page}</MainLayout>;
 */
export default function MainLayout({ children }) {
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const handleStart = () => setLoading(true);
        const handleFinish = () => setLoading(false);

        const stopStart = router.on("start", handleStart);
        const stopFinish = router.on("finish", handleFinish);

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    return (
        <ModalProvider>
            {loading && <LoadingSpinner />}
            {children}
            <Toast />
            <Modal />
        </ModalProvider>
    );
}
