import "../css/app.css";

import { createRoot } from "react-dom/client";
import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
    title: (title) => `${title} - ${appName}`,

    // Next.js (App Router) style page resolution
    // Controller: Inertia::render('(portals)/admin/dashboard/page')
    // Path:       resources/js/pages/(portals)/admin/dashboard/page.jsx
    resolve: async (name) => {
        const pages = import.meta.glob("./pages/**/*.jsx");
        const page = await pages[`./pages/${name}.jsx`]();

        // Automatic Layout injection (Next.js layout.jsx pattern)
        // If the page doesn't have a layout, wrap it in our persistent MainLayout
        if (page.default.layout === undefined) {
            const MainLayout = await import("./Components/Layout/MainLayout");
            page.default.layout = (p) => (
                <MainLayout.default>{p}</MainLayout.default>
            );
        }

        return page;
    },

    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },

    progress: {
        color: "#4B5563",
    },
});
