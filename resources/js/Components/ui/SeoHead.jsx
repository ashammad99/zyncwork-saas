import React from "react";
import { Head } from "@inertiajs/react";

export default function SeoHead({
    title,
    description,
    keywords,
    image,
    type = "website",
}) {
    // Props passed directly from the page component (which receives them from controller)

    // We can still have some "smart" defaults if props are missing,
    // but we won't rely on global app.seo anymore.
    // Instead, we assume the controller passed the resolved final values
    // OR we just use what is passed here.

    return (
        <Head>
            {title && <title>{title}</title>}
            {description && <meta name="description" content={description} />}
            {keywords && <meta name="keywords" content={keywords} />}

            {/* Open Graph */}
            {/* {title && <meta property="og:title" content={title} />}
            {description && (
                <meta property="og:description" content={description} />
            )}
            {image && <meta property="og:image" content={image} />}
            <meta property="og:type" content={type} /> */}

            {/* Twitter Card */}
            {/* <meta name="twitter:card" content="summary_large_image" />
            {title && <meta name="twitter:title" content={title} />}
            {description && (
                <meta name="twitter:description" content={description} />
            )}
            {image && <meta name="twitter:image" content={image} />} */}
        </Head>
    );
}
