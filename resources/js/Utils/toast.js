/**
 * Toast Utility
 *
 * Helper for showing flash messages from Laravel
 */

/**
 * Flash message types
 */
export const TOAST_TYPES = {
    SUCCESS: "success",
    ERROR: "error",
    WARNING: "warning",
    INFO: "info",
};

/**
 * Format Laravel validation errors for display
 *
 * @param {Object} errors - Inertia errors object
 * @returns {string} Formatted error message
 */
export function formatValidationErrors(errors) {
    if (!errors || Object.keys(errors).length === 0) return "";

    const errorMessages = Object.values(errors).flat();

    if (errorMessages.length === 1) {
        return errorMessages[0];
    }

    return errorMessages.join(", ");
}

/**
 * Check if there are any validation errors
 *
 * @param {Object} errors - Inertia errors object
 * @param {string} field - Optional specific field to check
 * @returns {boolean}
 */
export function hasErrors(errors, field = null) {
    if (!errors) return false;

    if (field) {
        return errors.hasOwnProperty(field);
    }

    return Object.keys(errors).length > 0;
}

/**
 * Get error message for a specific field
 *
 * @param {Object} errors - Inertia errors object
 * @param {string} field - Field name
 * @returns {string|null}
 */
export function getError(errors, field) {
    if (!errors || !errors[field]) return null;

    return Array.isArray(errors[field]) ? errors[field][0] : errors[field];
}
