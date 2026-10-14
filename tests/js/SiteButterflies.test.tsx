import SiteButterflies, {
    NO_BUTTERFLY_PAGES,
} from '@/Components/site/SiteButterflies';
import { render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-hus0 — lớp bướm bay khắp trang khách. Ở đây chỉ kiểm phần gắn vào trang
 * (bật/tắt, không chặn chuột, nằm dưới header); đường bay đã test ở butterflies.test.ts.
 * jsdom không vẽ canvas thật nên getContext được giả.
 */

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ component: 'Welcome', props: {} }),
}));

const realMatchMedia = window.matchMedia;

function setReducedMotion(on: boolean) {
    window.matchMedia = ((query: string) => ({
        matches: on && query.includes('reduce'),
        media: query,
        onchange: null,
        addEventListener: () => {},
        removeEventListener: () => {},
        dispatchEvent: () => false,
    })) as unknown as typeof window.matchMedia;
}

describe('SiteButterflies', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        setReducedMotion(false);
        const noop = new Proxy({}, { get: () => () => {} });
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(
            noop as unknown as CanvasRenderingContext2D,
        );
    });

    afterEach(() => {
        vi.useRealTimers();
        window.matchMedia = realMatchMedia;
    });

    it('là lớp trang trí phủ màn hình: ẩn với trình đọc màn hình, không chặn cú bấm', () => {
        const { container } = render(<SiteButterflies />);
        const c = container.querySelector('canvas');

        expect(c).not.toBeNull();
        expect(c).toHaveAttribute('aria-hidden', 'true');
        expect(c).toHaveClass('pointer-events-none', 'fixed', 'inset-0');
    });

    it('nằm dưới header (z-50) và menu điện thoại (z-40) để bướm bay qua dưới chúng', () => {
        const { container } = render(<SiteButterflies />);
        expect(container.querySelector('canvas')).toHaveClass('z-30');
    });

    it('khách bật "giảm chuyển động" thì không có bướm', () => {
        setReducedMotion(true);
        const { container } = render(<SiteButterflies />);
        expect(container.querySelector('canvas')).toBeNull();
    });

    it('trang giỏ hàng / thanh toán không có bướm', () => {
        expect(NO_BUTTERFLY_PAGES.has('Cart')).toBe(true);
        expect(NO_BUTTERFLY_PAGES.has('Welcome')).toBe(false);
        expect(NO_BUTTERFLY_PAGES.has('ProductDetail')).toBe(false);
    });
});
