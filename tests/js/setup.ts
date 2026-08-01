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

/**
 * jsdom cũng không có ResizeObserver — Headless UI v2 (Combobox chọn tỉnh/xã) gọi tới
 * nó khi đóng/mở panel. Stub rỗng là đủ: nó chỉ dùng để canh vị trí panel, thứ jsdom
 * vốn đã không đo được. Layout thật vẫn phải kiểm trên trình duyệt.
 */
if (!('ResizeObserver' in globalThis)) {
    globalThis.ResizeObserver = class {
        observe() {}
        unobserve() {}
        disconnect() {}
    };
}
