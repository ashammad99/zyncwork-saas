/**
 * Clipboard Utilities
 *
 * Copy text to clipboard with fallback support
 */

/**
 * Copy text to clipboard
 *
 * @param {string} text - Text to copy
 * @returns {Promise<boolean>} Success status
 *
 * @example
 * const success = await copyToClipboard('https://example.com');
 * if (success) {
 *   // Show success toast
 * }
 */
export async function copyToClipboard(text) {
    // Modern clipboard API (preferred)
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            console.error("Failed to copy using Clipboard API:", err);
            return fallbackCopyToClipboard(text);
        }
    } else {
        // Fallback for older browsers or non-HTTPS
        return fallbackCopyToClipboard(text);
    }
}

/**
 * Fallback copy method using document.execCommand
 *
 * @param {string} text - Text to copy
 * @returns {boolean} Success status
 */
function fallbackCopyToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        const successful = document.execCommand("copy");
        document.body.removeChild(textArea);
        return successful;
    } catch (err) {
        console.error("Fallback copy failed:", err);
        document.body.removeChild(textArea);
        return false;
    }
}

/**
 * Copy to clipboard and show a toast notification
 * Requires the toast utility to be available
 *
 * @param {string} text - Text to copy
 * @param {Function} showToast - Toast function (optional)
 * @returns {Promise<boolean>} Success status
 *
 * @example
 * import { copyWithToast } from '@/Utils/clipboard';
 *
 * copyWithToast('Copied text', (message) => {
 *   // Your toast implementation
 * });
 */
export async function copyWithToast(text, showToast = null) {
    const success = await copyToClipboard(text);

    if (showToast) {
        if (success) {
            showToast("Copied to clipboard!");
        } else {
            showToast("Failed to copy to clipboard");
        }
    }

    return success;
}

/**
 * Read text from clipboard
 *
 * @returns {Promise<string|null>} Clipboard text or null
 */
export async function readFromClipboard() {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            const text = await navigator.clipboard.readText();
            return text;
        } catch (err) {
            console.error("Failed to read from clipboard:", err);
            return null;
        }
    }
    return null;
}
