import SiteCreatures from '@/Components/site/SiteCreatures';
import { render } from '@testing-library/react';
import { renderToString } from 'react-dom/server';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-ff1y — lớp chim, bướm, đom đóm bay khắp trang khách. Ở đây chỉ kiểm phần
 * gắn vào trang (bật/tắt, không chặn chuột, nằm dưới header, an toàn với SSR); đường
 * bay đã test ở butterflies.test.ts và skyCreatures.test.ts.
 * jsdom không vẽ canvas thật nên getContext được giả.
 */

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

describe('SiteCreatures', () => {
    beforeEach(() => {
        setReducedMotion(false);
        const noop = new Proxy({}, { get: () => () => {} });
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(
            noop as unknown as CanvasRenderingContext2D,
        );
        vi.spyOn(window, 'requestAnimationFrame').mockReturnValue(0);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        window.matchMedia = realMatchMedia;
    });

    it('là lớp trang trí phủ màn hình: ẩn với trình đọc màn hình, không chặn cú bấm', () => {
        const { container } = render(<SiteCreatures />);
        const c = container.querySelector('canvas');

        expect(c).not.toBeNull();
        expect(c).toHaveAttribute('aria-hidden', 'true');
        expect(c).toHaveClass('pointer-events-none', 'fixed', 'inset-0');
    });

    it('nằm dưới header (z-50) và menu điện thoại (z-40)', () => {
        const { container } = render(<SiteCreatures />);
        expect(container.querySelector('canvas')).toHaveClass('z-30');
    });

    it('khách bật "giảm chuyển động" thì không vẽ gì', () => {
        setReducedMotion(true);
        const { container } = render(<SiteCreatures />);
        expect(container.querySelector('canvas')).toBeNull();
    });

    it('SSR: server không in canvas (bật sau khi mount) — HTML hai bên khớp nhau', () => {
        expect(renderToString(<SiteCreatures />)).toBe('');
    });
});
