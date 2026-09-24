/**
 * Number and Currency Utilities
 *
 * Format numbers, currency, and percentages with proper localization
 */

/**
 * Format number with locale-specific separators
 *
 * @param {number} value - Number to format
 * @param {string} locale - Locale (default: 'en-US')
 * @param {number} decimals - Number of decimal places (default: 0)
 * @returns {string} Formatted number
 *
 * @example
 * formatNumber(1234567)        // "1,234,567"
 * formatNumber(1234.567, 'en-US', 2)  // "1,234.57"
 */
export function formatNumber(value, locale = "en-US", decimals = 0) {
    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(value);
}

/**
 * Currency name to ISO code mapping
 */
const CURRENCY_MAP = {
    // Common names
    dollar: "USD",
    euro: "EUR",
    pound: "GBP",
    yen: "JPY",
    yuan: "CNY",
    rupee: "INR",
    taka: "BDT",
    real: "BRL",
    ruble: "RUB",
    won: "KRW",
    peso: "MXN",
    franc: "CHF",
    krona: "SEK",
    riyal: "SAR",
    dirham: "AED",
};

/**
 * Format currency with symbol
 *
 * @param {number} value - Amount to format
 * @param {string} currency - Currency name or ISO code (default: 'dollar')
 * @param {string} locale - Locale (default: 'en-US')
 * @returns {string} Formatted currency
 *
 * @example
 * formatCurrency(1234.56)                    // "$1,234.56"
 * formatCurrency(1234.56, 'euro', 'de-DE')  // "1.234,56 €"
 * formatCurrency(1234.56, 'EUR', 'de-DE')   // "1.234,56 €"
 * formatCurrency(1234.56, 'taka')           // "৳1,234.56"
 */
export function formatCurrency(value, currency = "dollar", locale = "en-US") {
    // Convert currency name to ISO code if needed
    const currencyCode =
        CURRENCY_MAP[currency.toLowerCase()] || currency.toUpperCase();

    return new Intl.NumberFormat(locale, {
        style: "currency",
        currency: currencyCode,
    }).format(value);
}

/**
 * Format percentage
 *
 * @param {number} value - Decimal value (0.15 = 15%)
 * @param {number} decimals - Decimal places (default: 0)
 * @returns {string} Formatted percentage
 *
 * @example
 * formatPercent(0.15)      // "15%"
 * formatPercent(0.1567, 2) // "15.67%"
 */
export function formatPercent(value, decimals = 0) {
    return (value * 100).toFixed(decimals) + "%";
}

/**
 * Format file size to human-readable format
 *
 * @param {number} bytes - Size in bytes
 * @param {number} decimals - Decimal places (default: 2)
 * @returns {string} Formatted size
 *
 * @example
 * formatBytes(1024)      // "1 KB"
 * formatBytes(1536, 2)   // "1.50 KB"
 */
export function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return "0 Bytes";

    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ["Bytes", "KB", "MB", "GB", "TB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i];
}

/**
 * Compact large numbers (1.2K, 1.5M)
 *
 * @param {number} value - Number to compact
 * @returns {string} Compacted number
 *
 * @example
 * compactNumber(1234)      // "1.2K"
 * compactNumber(1234567)   // "1.2M"
 */
export function compactNumber(value) {
    const formatter = new Intl.NumberFormat("en-US", {
        notation: "compact",
        compactDisplay: "short",
    });

    return formatter.format(value);
}
