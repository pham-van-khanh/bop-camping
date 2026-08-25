import HeroSlideshow from '@/Components/site/HeroSlideshow';
import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-vq7t — trước đây cả 7 ảnh hero tải ngay khi mount dù chỉ 1 ảnh hiển thị
 * (PageSpeed mobile báo LCP 5.3s, ~1.3MB tải thừa). Slide đầu vẫn phải tải ngay để khớp
 * <link rel=preload> ở app.blade.php; các slide khác chỉ tải khi sắp/đang cần.
 */

const SLIDES = [
    { src: '/a.webp', title: 'Ảnh 1' },
    { src: '/b.webp', title: 'Ảnh 2' },
    { src: '/c.webp', title: 'Ảnh 3' },
];

function panes(container: HTMLElement) {
    return Array.from(
        container.querySelectorAll<HTMLElement>('.flex-none > div'),
    );
}

describe('HeroSlideshow — chỉ tải ảnh đang cần', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('lúc mount chỉ slide đầu có background-image, các slide khác chưa tải', () => {
        const { container } = render(<HeroSlideshow slides={SLIDES} />);
        const p = panes(container);

        expect(p[0].style.backgroundImage).toContain('a.webp');
        expect(p[1].style.backgroundImage).toBe('');
        expect(p[2].style.backgroundImage).toBe('');
    });

    it('bấm thumbnail sang slide chưa tải thì slide đó được tải ngay', () => {
        const { container } = render(<HeroSlideshow slides={SLIDES} />);

        act(() => {
            screen.getByRole('button', { name: 'Ảnh 3' }).click();
        });

        expect(panes(container)[2].style.backgroundImage).toContain('c.webp');
    });

    it('sau 1.5s tự tải trước slide kế tiếp để autoplay không bị chớp nền trống', () => {
        const { container } = render(<HeroSlideshow slides={SLIDES} />);

        act(() => {
            vi.advanceTimersByTime(1500);
        });

        const p = panes(container);
        expect(p[1].style.backgroundImage).toContain('b.webp');
        // Slide 3 chưa tới lượt (kế tiếp của kế tiếp) — vẫn chưa tải.
        expect(p[2].style.backgroundImage).toBe('');
    });

    it('nút thumbnail của slide chưa tải không tự gắn ảnh nền (tránh tải qua đường này)', () => {
        render(<HeroSlideshow slides={SLIDES} />);

        const thumb = screen.getByRole('button', { name: 'Ảnh 3' });
        // `background: '#25301a'` (shorthand) tự đặt background-image = none, không
        // phải chuỗi rỗng — đây là hành vi CSS đúng, không phải lỗi component.
        expect(thumb.style.backgroundImage).toBe('none');
    });

    it('không có prop slides thì rơi về 7 ảnh mặc định, slide đầu vẫn tải ngay', () => {
        const { container } = render(<HeroSlideshow />);

        expect(panes(container)[0].style.backgroundImage).toContain(
            'beach-night-tent.webp',
        );
    });
});
