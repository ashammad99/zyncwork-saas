/**
 * Image Compression Utility
 *
 * Compress and resize images before uploading to reduce file size and improve performance.
 */

/**
 * Compress and resize an image file
 *
 * @param {File} file - The image file to compress
 * @param {Object} options - Compression options
 * @param {number} options.maxWidth - Maximum width in pixels (default: 1920)
 * @param {number} options.maxHeight - Maximum height in pixels (default: 1080)
 * @param {number} options.quality - Image quality 0-1 (default: 0.8)
 * @param {string} options.type - Output mime type (default: 'image/jpeg')
 * @returns {Promise<File>} Compressed image file
 */
export async function compressImage(file, options = {}) {
    const {
        maxWidth = 1920,
        maxHeight = 1080,
        quality = 0.8,
        type = "image/jpeg",
    } = options;

    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (e) => {
            const img = new Image();

            img.onload = () => {
                const canvas = document.createElement("canvas");
                const ctx = canvas.getContext("2d");

                // Calculate new dimensions
                let width = img.width;
                let height = img.height;

                if (width > maxWidth) {
                    height = (height * maxWidth) / width;
                    width = maxWidth;
                }

                if (height > maxHeight) {
                    width = (width * maxHeight) / height;
                    height = maxHeight;
                }

                canvas.width = width;
                canvas.height = height;

                // Draw and compress
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(
                    (blob) => {
                        if (!blob) {
                            reject(
                                new Error("Canvas to Blob conversion failed"),
                            );
                            return;
                        }

                        // Create new File from blob
                        const compressedFile = new File([blob], file.name, {
                            type: type,
                            lastModified: Date.now(),
                        });

                        resolve(compressedFile);
                    },
                    type,
                    quality,
                );
            };

            img.onerror = () => reject(new Error("Image load failed"));
            img.src = e.target.result;
        };

        reader.onerror = () => reject(new Error("File read failed"));
        reader.readAsDataURL(file);
    });
}

/**
 * Compress multiple images
 *
 * @param {FileList|File[]} files - Array of image files
 * @param {Object} options - Compression options (same as compressImage)
 * @returns {Promise<File[]>} Array of compressed files
 */
export async function compressImages(files, options = {}) {
    const fileArray = Array.from(files);
    return Promise.all(fileArray.map((file) => compressImage(file, options)));
}

/**
 * Generate 3 responsive image versions (small, medium, large) for srcset
 * Perfect for performance optimization with responsive images
 *
 * @param {File} file - The original image file
 * @param {Object} options - Size options
 * @param {number} options.smallWidth - Small image max width (default: 640)
 * @param {number} options.mediumWidth - Medium image max width (default: 1280)
 * @param {number} options.largeWidth - Large image max width (default: 1920)
 * @param {number} options.quality - Image quality 0-1 (default: 0.8)
 * @returns {Promise<{small: File, medium: File, large: File}>} Object with 3 image versions
 *
 * @example
 * const versions = await generateResponsiveImages(originalFile);
 * // Returns: { small: File, medium: File, large: File }
 *
 * // Upload to backend
 * formData.append('image_small', versions.small);
 * formData.append('image_medium', versions.medium);
 * formData.append('image_large', versions.large);
 */
export async function generateResponsiveImages(file, options = {}) {
    const {
        smallWidth = 640,
        mediumWidth = 1280,
        largeWidth = 1920,
        quality = 0.8,
    } = options;

    const [small, medium, large] = await Promise.all([
        compressImage(file, { maxWidth: smallWidth, quality }),
        compressImage(file, { maxWidth: mediumWidth, quality }),
        compressImage(file, { maxWidth: largeWidth, quality }),
    ]);

    return { small, medium, large };
}

/**
 * Get image dimensions from a File
 *
 * @param {File} file - Image file
 * @returns {Promise<{width: number, height: number}>}
 */
export function getImageDimensions(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();

        reader.onload = (e) => {
            const img = new Image();
            img.onload = () =>
                resolve({ width: img.width, height: img.height });
            img.onerror = () => reject(new Error("Failed to load image"));
            img.src = e.target.result;
        };

        reader.onerror = () => reject(new Error("Failed to read file"));
        reader.readAsDataURL(file);
    });
}

/**
 * Convert file size to human readable format
 *
 * @param {number} bytes - File size in bytes
 * @returns {string} Formatted file size
 */
export function formatFileSize(bytes) {
    if (bytes === 0) return "0 Bytes";

    const k = 1024;
    const sizes = ["Bytes", "KB", "MB", "GB"];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}
