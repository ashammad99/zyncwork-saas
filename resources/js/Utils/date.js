/**
 * Date/Time Utilities
 *
 * Common date formatting and manipulation helpers
 */

/**
 * Format date to human readable format
 *
 * @param {Date|string} date - Date to format
 * @param {string} locale - Locale (default: 'en-US')
 * @returns {string} Formatted date
 */
export function formatDate(date, locale = "en-US") {
    const d = new Date(date);
    return d.toLocaleDateString(locale, {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
}

/**
 * Format date with time
 *
 * @param {Date|string} date - Date to format
 * @param {string} locale - Locale (default: 'en-US')
 * @returns {string} Formatted date and time
 */
export function formatDateTime(date, locale = "en-US") {
    const d = new Date(date);
    return d.toLocaleString(locale, {
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });
}

/**
 * Get relative time (e.g., "2 hours ago")
 *
 * @param {Date|string} date - Date to compare
 * @returns {string} Relative time string
 */
export function timeAgo(date) {
    const seconds = Math.floor((new Date() - new Date(date)) / 1000);

    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60,
    };

    for (const [key, value] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / value);
        if (interval >= 1) {
            return interval === 1 ? `1 ${key} ago` : `${interval} ${key}s ago`;
        }
    }

    return "just now";
}
