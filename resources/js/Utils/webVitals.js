/**
 * Web Vitals Monitoring
 *
 * Track Core Web Vitals (LCP, FID, CLS, FCP, TTFB) for performance monitoring.
 * These metrics are crucial for SEO and user experience.
 */

import { onLCP, onFID, onCLS, onFCP, onTTFB } from "web-vitals";

/**
 * Send vitals to analytics endpoint
 *
 * @param {Object} metric - Web vital metric
 */
function sendToAnalytics(metric) {
    const body = JSON.stringify({
        name: metric.name,
        value: metric.value,
        rating: metric.rating,
        delta: metric.delta,
        id: metric.id,
        url: window.location.href,
        user_agent: navigator.userAgent,
    });

    // Send to your analytics endpoint
    if (navigator.sendBeacon) {
        navigator.sendBeacon("/api/analytics/vitals", body);
    } else {
        fetch("/api/analytics/vitals", {
            body,
            method: "POST",
            headers: { "Content-Type": "application/json" },
            keepalive: true,
        });
    }
}

/**
 * Log vitals to console (development only)
 *
 * @param {Object} metric - Web vital metric
 */
function logVitals(metric) {
    const emoji = {
        good: "✅",
        "needs-improvement": "⚠️",
        poor: "❌",
    };

    console.log(
        `${emoji[metric.rating] || "📊"} ${metric.name}:`,
        metric.value,
        `(${metric.rating})`,
        metric,
    );
}

/**
 * Initialize Web Vitals monitoring
 *
 * @param {Object} options - Configuration options
 * @param {boolean} options.sendToServer - Send metrics to analytics endpoint
 * @param {boolean} options.logToConsole - Log metrics to console
 * @param {Function} options.onMetric - Custom callback for each metric
 *
 * @example
 * // In app.jsx or main entry point
 * import { initWebVitals } from '@/Utils/webVitals';
 *
 * if (import.meta.env.PROD) {
 *     initWebVitals({ sendToServer: true });
 * } else {
 *     initWebVitals({ logToConsole: true });
 * }
 */
export function initWebVitals(options = {}) {
    const {
        sendToServer = false,
        logToConsole = import.meta.env.DEV,
        onMetric = null,
    } = options;

    const handleMetric = (metric) => {
        if (logToConsole) logVitals(metric);
        if (sendToServer) sendToAnalytics(metric);
        if (onMetric) onMetric(metric);
    };

    // Track all Core Web Vitals
    onLCP(handleMetric); // Largest Contentful Paint
    onFID(handleMetric); // First Input Delay
    onCLS(handleMetric); // Cumulative Layout Shift
    onFCP(handleMetric); // First Contentful Paint
    onTTFB(handleMetric); // Time to First Byte
}

/**
 * Get current Web Vitals snapshot
 *
 * @returns {Promise<Object>} Object with all current vitals
 */
export function getWebVitals() {
    return new Promise((resolve) => {
        const vitals = {};
        let count = 0;
        const total = 5;

        const checkComplete = () => {
            count++;
            if (count === total) resolve(vitals);
        };

        onLCP((metric) => {
            vitals.lcp = metric;
            checkComplete();
        });
        onFID((metric) => {
            vitals.fid = metric;
            checkComplete();
        });
        onCLS((metric) => {
            vitals.cls = metric;
            checkComplete();
        });
        onFCP((metric) => {
            vitals.fcp = metric;
            checkComplete();
        });
        onTTFB((metric) => {
            vitals.ttfb = metric;
            checkComplete();
        });

        // Timeout after 3 seconds
        setTimeout(() => resolve(vitals), 3000);
    });
}
