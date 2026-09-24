/**
 * Performance Utilities
 *
 * Debounce and throttle functions for optimizing event handlers
 */

/**
 * Debounce function - delays execution until after wait time has elapsed since last call
 * Perfect for: search inputs, resize handlers, validation
 *
 * @param {Function} func - Function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function} Debounced function
 *
 * @example
 * const handleSearch = debounce((query) => {
 *   // API call here
 * }, 300);
 */
export function debounce(func, wait = 300) {
    let timeout;

    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };

        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function - ensures function only executes once per specified time period
 * Perfect for: scroll handlers, mouse move, window resize
 *
 * @param {Function} func - Function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function} Throttled function
 *
 * @example
 * const handleScroll = throttle(() => {
 *   // Scroll logic here
 * }, 100);
 */
export function throttle(func, limit = 100) {
    let inThrottle;

    return function executedFunction(...args) {
        if (!inThrottle) {
            func(...args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}
