import React, { useState, useEffect, createContext, useContext } from "react";
import Toast from "../ui/Toast";
import ImpersonationBanner from "./ImpersonationBanner";
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
// Portal layouts wrap MainLayout again inside the one app.jsx adds; the banner renders once.
const InsideMainLayout = createContext(false);

export default function MainLayout({ children }) {
    const nested = useContext(InsideMainLayout);
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
        <InsideMainLayout.Provider value={true}>
            <ModalProvider>
                {!nested && <ImpersonationBanner />}
                {loading && <LoadingSpinner />}
                {children}
                <Toast />
                <Modal />
            </ModalProvider>
        </InsideMainLayout.Provider>
    );
}
