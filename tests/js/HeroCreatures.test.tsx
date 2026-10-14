import HeroSlideshow from '@/Components/site/HeroSlideshow';
import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-hus0 — lớp chim/bướm/đom đóm trên hero. Ở đây chỉ kiểm phần gắn vào
 * trang (bật/tắt, ngày/đêm theo slide, không chặn chuột); đường bay đã test ở
 * heroCreatures.test.ts. jsdom không vẽ canvas thật nên getContext được giả.
 */

const SLIDES = [
    { src: '/ngay.webp', title: 'Ảnh ngày', mood: 'day' as const },
    { src: '/dem.webp', title: 'Ảnh đêm', mood: 'night' as const },
];

const realMatchMedia = window.matchMedia;

function stubIntersectionObserver() {
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            observe() {}
            unobserve() {}
            disconnect() {}
        },
    );
}

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

const canvas = (container: HTMLElement) =>
    container.querySelector<HTMLCanvasElement>('canvas');

describe('HeroCreatures trên hero slideshow', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        stubIntersectionObserver();
        setReducedMotion(false);
        // Mọi lệnh vẽ đều là no-op: test không kiểm hình, chỉ kiểm hành vi.
        const noop = new Proxy({}, { get: () => () => {} });
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(
            noop as unknown as CanvasRenderingContext2D,
        );
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
        window.matchMedia = realMatchMedia;
    });

    it('là lớp trang trí: ẩn với trình đọc màn hình và không chặn cú bấm', () => {
        const { container } = render(<HeroSlideshow slides={SLIDES} />);
        const c = canvas(container);

        expect(c).not.toBeNull();
        expect(c).toHaveAttribute('aria-hidden', 'true');
        expect(c).toHaveClass('pointer-events-none');
    });

    it('đổi sang ảnh đêm thì chuyển sang đom đóm, quay lại ảnh ngày thì chim/bướm', () => {
        const { container } = render(<HeroSlideshow slides={SLIDES} />);
        expect(canvas(container)).toHaveAttribute('data-mood', 'day');

        act(() => {
            screen.getByRole('button', { name: 'Ảnh đêm' }).click();
        });
        expect(canvas(container)).toHaveAttribute('data-mood', 'night');

        act(() => {
            screen.getByRole('button', { name: 'Ảnh ngày' }).click();
        });
        expect(canvas(container)).toHaveAttribute('data-mood', 'day');
    });

    it('khách bật "giảm chuyển động" thì không có animation nào', () => {
        setReducedMotion(true);
        const { container } = render(<HeroSlideshow slides={SLIDES} />);
        expect(canvas(container)).toBeNull();
    });

    it('trình duyệt thiếu IntersectionObserver (không biết lúc nào hero khuất) thì không chạy', () => {
        vi.unstubAllGlobals();
        vi.stubGlobal('IntersectionObserver', undefined);
        const { container } = render(<HeroSlideshow slides={SLIDES} />);
        expect(canvas(container)).toBeNull();
    });
});
