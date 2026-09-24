import React from "react";
import { useModal } from "../../Contexts/ModalContext";

/**
 * Pre-built promotional modal templates
 * Ready to use for common marketing scenarios
 */

/**
 * Image Promo - Full width promotional image with CTA
 */
export function ImagePromo({
    imageUrl,
    title,
    description,
    ctaText,
    ctaLink,
    onClose,
}) {
    const { closeModal } = useModal();

    const handleCTA = () => {
        if (ctaLink) {
            window.location.href = ctaLink;
        }
        if (onClose) onClose();
        closeModal();
    };

    return (
        <div className="text-center">
            <img
                src={imageUrl}
                alt={title}
                className="w-full rounded-lg mb-4"
            />
            {title && <h2 className="text-2xl font-bold mb-3">{title}</h2>}
            {description && <p className="text-gray-600 mb-6">{description}</p>}
            {ctaText && (
                <button
                    onClick={handleCTA}
                    className="px-8 py-3 bg-primary text-on-primary rounded-lg font-semibold hover:bg-primary-hover transition-colors"
                >
                    {ctaText}
                </button>
            )}
        </div>
    );
}

/**
 * Banner Promo - Marketing banner with image and text side by side
 */
export function BannerPromo({
    imageUrl,
    title,
    subtitle,
    features,
    ctaText,
    ctaLink,
}) {
    const { closeModal } = useModal();

    const handleCTA = () => {
        if (ctaLink) window.location.href = ctaLink;
        closeModal();
    };

    return (
        <div className="grid md:grid-cols-2 gap-6 items-center">
            <div>
                <img src={imageUrl} alt={title} className="w-full rounded-lg" />
            </div>
            <div>
                <h2 className="text-3xl font-bold mb-2">{title}</h2>
                {subtitle && (
                    <p className="text-lg text-gray-600 mb-4">{subtitle}</p>
                )}

                {features && features.length > 0 && (
                    <ul className="space-y-2 mb-6">
                        {features.map((feature, index) => (
                            <li key={index} className="flex items-center gap-2">
                                <svg
                                    className="w-5 h-5 text-green-500"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                                <span>{feature}</span>
                            </li>
                        ))}
                    </ul>
                )}

                {ctaText && (
                    <button
                        onClick={handleCTA}
                        className="w-full px-6 py-3 bg-primary text-on-primary rounded-lg font-semibold hover:bg-primary-hover transition-colors"
                    >
                        {ctaText}
                    </button>
                )}
            </div>
        </div>
    );
}

/**
 * Countdown Promo - Time-limited offer with countdown timer
 */
export function CountdownPromo({ imageUrl, title, endTime, ctaText, ctaLink }) {
    const { closeModal } = useModal();
    const [timeLeft, setTimeLeft] = React.useState("");

    React.useEffect(() => {
        const calculateTimeLeft = () => {
            const difference = new Date(endTime) - new Date();
            if (difference > 0) {
                const hours = Math.floor(difference / (1000 * 60 * 60));
                const minutes = Math.floor((difference / 1000 / 60) % 60);
                const seconds = Math.floor((difference / 1000) % 60);
                setTimeLeft(`${hours}h ${minutes}m ${seconds}s`);
            } else {
                setTimeLeft("Expired");
            }
        };

        calculateTimeLeft();
        const timer = setInterval(calculateTimeLeft, 1000);
        return () => clearInterval(timer);
    }, [endTime]);

    return (
        <div className="text-center">
            <img
                src={imageUrl}
                alt={title}
                className="w-full rounded-lg mb-4"
            />
            <h2 className="text-2xl font-bold mb-3">{title}</h2>
            <div className="bg-red-100 text-red-600 px-4 py-2 rounded-lg inline-block mb-4">
                <span className="font-mono text-xl font-bold">
                    ⏰ {timeLeft}
                </span>
            </div>
            {timeLeft !== "Expired" && ctaText && (
                <button
                    onClick={() => {
                        if (ctaLink) window.location.href = ctaLink;
                        closeModal();
                    }}
                    className="px-8 py-3 bg-primary text-on-primary rounded-lg font-semibold hover:bg-primary-hover transition-colors"
                >
                    {ctaText}
                </button>
            )}
        </div>
    );
}

/**
 * Email Capture - Lead generation form with image
 */
export function EmailCapturePromo({
    imageUrl,
    title,
    subtitle,
    placeholder,
    buttonText,
    onSubmit,
}) {
    const { closeModal } = useModal();
    const [email, setEmail] = React.useState("");
    const [loading, setLoading] = React.useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        if (onSubmit) {
            await onSubmit(email);
        }

        setLoading(false);
        closeModal();
    };

    return (
        <div className="text-center">
            {imageUrl && (
                <img
                    src={imageUrl}
                    alt={title}
                    className="w-full rounded-lg mb-4"
                />
            )}
            <h2 className="text-2xl font-bold mb-2">{title}</h2>
            {subtitle && <p className="text-gray-600 mb-6">{subtitle}</p>}

            <form onSubmit={handleSubmit} className="space-y-4">
                <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder={placeholder || "Enter your email"}
                    required
                    className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                />
                <button
                    type="submit"
                    disabled={loading}
                    className="w-full px-6 py-3 bg-primary text-on-primary rounded-lg font-semibold hover:bg-primary-hover transition-colors disabled:opacity-50"
                >
                    {loading ? "Submitting..." : buttonText || "Subscribe"}
                </button>
            </form>
        </div>
    );
}

/**
 * Gallery Promo - Multiple images in a carousel
 */
export function GalleryPromo({ images, title, ctaText, ctaLink }) {
    const { closeModal } = useModal();
    const [currentIndex, setCurrentIndex] = React.useState(0);

    const nextImage = () => {
        setCurrentIndex((prev) => (prev + 1) % images.length);
    };

    const prevImage = () => {
        setCurrentIndex((prev) => (prev - 1 + images.length) % images.length);
    };

    return (
        <div className="text-center">
            <h2 className="text-2xl font-bold mb-4">{title}</h2>

            <div className="relative mb-4">
                <img
                    src={images[currentIndex]}
                    alt={`Gallery ${currentIndex + 1}`}
                    className="w-full rounded-lg"
                />

                {images.length > 1 && (
                    <>
                        <button
                            onClick={prevImage}
                            className="absolute left-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-75"
                        >
                            ←
                        </button>
                        <button
                            onClick={nextImage}
                            className="absolute right-2 top-1/2 -translate-y-1/2 bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-75"
                        >
                            →
                        </button>
                        <div className="absolute bottom-2 left-1/2 -translate-x-1/2 text-white text-sm bg-black bg-opacity-50 px-3 py-1 rounded">
                            {currentIndex + 1} / {images.length}
                        </div>
                    </>
                )}
            </div>

            {ctaText && (
                <button
                    onClick={() => {
                        if (ctaLink) window.location.href = ctaLink;
                        closeModal();
                    }}
                    className="px-8 py-3 bg-primary text-on-primary rounded-lg font-semibold hover:bg-primary-hover transition-colors"
                >
                    {ctaText}
                </button>
            )}
        </div>
    );
}
