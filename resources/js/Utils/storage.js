/**
 * LocalStorage Utilities
 *
 * Type-safe localStorage wrapper with JSON support and expiration
 */

/**
 * Set item in localStorage (auto-stringifies objects)
 *
 * @param {string} key - Storage key
 * @param {any} value - Value to store
 * @returns {boolean} Success status
 *
 * @example
 * storage.set('user', { name: 'John', role: 'admin' });
 * storage.set('theme', 'dark');
 */
export function set(key, value) {
    try {
        const stringValue =
            typeof value === "string" ? value : JSON.stringify(value);
        localStorage.setItem(key, stringValue);
        return true;
    } catch (err) {
        console.error("LocalStorage set error:", err);
        return false;
    }
}

/**
 * Get item from localStorage (auto-parses JSON)
 *
 * @param {string} key - Storage key
 * @param {any} defaultValue - Default value if key doesn't exist
 * @returns {any} Stored value or default
 *
 * @example
 * const user = storage.get('user', {});
 * const theme = storage.get('theme', 'light');
 */
export function get(key, defaultValue = null) {
    try {
        const item = localStorage.getItem(key);
        if (item === null) return defaultValue;

        // Try to parse as JSON, fallback to string
        try {
            return JSON.parse(item);
        } catch {
            return item;
        }
    } catch (err) {
        console.error("LocalStorage get error:", err);
        return defaultValue;
    }
}

/**
 * Remove item from localStorage
 *
 * @param {string} key - Storage key
 * @returns {boolean} Success status
 */
export function remove(key) {
    try {
        localStorage.removeItem(key);
        return true;
    } catch (err) {
        console.error("LocalStorage remove error:", err);
        return false;
    }
}

/**
 * Clear all localStorage
 *
 * @returns {boolean} Success status
 */
export function clear() {
    try {
        localStorage.clear();
        return true;
    } catch (err) {
        console.error("LocalStorage clear error:", err);
        return false;
    }
}

/**
 * Set item with expiration time
 *
 * @param {string} key - Storage key
 * @param {any} value - Value to store
 * @param {number} ttl - Time to live in seconds
 * @returns {boolean} Success status
 *
 * @example
 * // Expire in 1 hour
 * storage.setWithExpiry('token', 'abc123', 3600);
 */
export function setWithExpiry(key, value, ttl) {
    try {
        const now = new Date();
        const item = {
            value: value,
            expiry: now.getTime() + ttl * 1000,
        };
        localStorage.setItem(key, JSON.stringify(item));
        return true;
    } catch (err) {
        console.error("LocalStorage setWithExpiry error:", err);
        return false;
    }
}

/**
 * Get item with expiration check
 *
 * @param {string} key - Storage key
 * @param {any} defaultValue - Default value if expired or not found
 * @returns {any} Stored value or default
 */
export function getWithExpiry(key, defaultValue = null) {
    try {
        const itemStr = localStorage.getItem(key);
        if (!itemStr) return defaultValue;

        const item = JSON.parse(itemStr);
        const now = new Date();

        // Check if expired
        if (now.getTime() > item.expiry) {
            localStorage.removeItem(key);
            return defaultValue;
        }

        return item.value;
    } catch (err) {
        console.error("LocalStorage getWithExpiry error:", err);
        return defaultValue;
    }
}

/**
 * Check if key exists
 *
 * @param {string} key - Storage key
 * @returns {boolean}
 */
export function has(key) {
    return localStorage.getItem(key) !== null;
}

/**
 * Get all keys
 *
 * @returns {string[]} Array of all storage keys
 */
export function keys() {
    return Object.keys(localStorage);
}

// Default export as object for import convenience
export default {
    set,
    get,
    remove,
    clear,
    setWithExpiry,
    getWithExpiry,
    has,
    keys,
};
