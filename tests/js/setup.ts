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

/**
 * `route()` là hàm TOÀN CỤC do Ziggy chèn vào lúc chạy thật (@routes trong app.blade.php),
 * không phải thứ component import — nên trong jsdom nó không tồn tại và mọi component gọi
 * tới nó sẽ nổ ReferenceError ngay khi render.
 *
 * Stub ở đây thay vì rải trong từng file test: đây là chuyện của MÔI TRƯỜNG (giống
 * matchMedia/ResizeObserver ở trên), không phải chuyện riêng của test nào. Trả lại chính
 * tên route + tham số để test nào muốn kiểm "gửi đi đúng đích" vẫn khẳng định được.
 * Test cần dạng khác cứ gán đè globalThis.route trong file của mình.
 */
if (!('route' in globalThis)) {
    (globalThis as { route?: unknown }).route = (
        name: string,
        params?: unknown,
    ) =>
        params === undefined
            ? `/${name}`
            : `/${name}/${String(
                  typeof params === 'object'
                      ? Object.values(params as object).join('/')
                      : params,
              )}`;
}
