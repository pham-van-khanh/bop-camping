import '@testing-library/jest-dom/vitest';

/**
 * jsdom không có IntersectionObserver / matchMedia — framer-motion và một số
 * component dùng tới. Stub tối thiểu để render không nổ.
 */
if (!('matchMedia' in window)) {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: (query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addEventListener: () => {},
            removeEventListener: () => {},
            dispatchEvent: () => false,
        }),
    });
}
