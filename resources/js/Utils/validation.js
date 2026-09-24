/**
 * Validation utilities
 */

/**
 * Validates a phone number using regex
 * Supports international formats
 * @param {string} phone
 * @returns {boolean}
 */
export const validatePhone = (phone) => {
    const regex = /^01[3,4,5,6,7,8,9]\d{8}$/;
    return regex.test(phone.replace(/[\s\(\)\-]/g, ""));
};

/**
 * Basic email validation
 * @param {string} email
 * @returns {boolean}
 */
export const validateEmail = (email) => {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
};
/**
 * Validates password against strict rules:
 * - 8-15 characters
 * - Uppercase, Lowercase, Number, Symbol
 * @param {string} password
 * @returns {boolean}
 */
export const validatePassword = (password) => {
    const regex =
        /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,15}$/;
    return regex.test(password);
};

export const passwordRequirements = [
    { label: "8-15 Characters", regex: /^.{8,15}$/ },
    { label: "Uppercase Letter", regex: /[A-Z]/ },
    { label: "Lowercase Letter", regex: /[a-z]/ },
    { label: "Number", regex: /\d/ },
    { label: "Special Symbol (@$!%*?&#)", regex: /[@$!%*?&#]/ },
];
