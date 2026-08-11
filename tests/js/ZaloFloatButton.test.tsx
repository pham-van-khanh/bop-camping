import ZaloFloatButton from '@/Components/site/ZaloFloatButton';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Nút Zalo nổi chỉ mở Zalo OA (bopcamping-h0hh) — một đích duy nhất, không còn
 * panel chọn tài khoản. Liên hệ theo SỐ nằm ở footer, không phải ở đây.
 */

// vi.mock được hoist lên đầu file nên state phải khai báo bằng vi.hoisted.
const state = vi.hoisted(() => ({
    site: null as { zalo_oa: string } | null,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { site: state.site } }),
}));

const OA = 'https://zalo.me/791036380751013489';

beforeEach(() => {
    state.site = null;
});

describe('nút Zalo nổi', () => {
    it('không render khi chưa có OA', () => {
        const { container } = render(<ZaloFloatButton />);
        expect(container).toBeEmptyDOMElement();
    });

    it('mở thẳng OA trong tab mới, không bày panel chọn', () => {
        state.site = { zalo_oa: OA };
        render(<ZaloFloatButton />);

        const link = screen.getByRole('link', { name: 'Liên hệ Zalo' });
        expect(link).toHaveAttribute('href', OA);
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noreferrer');
        // Không còn nút bung panel như bản cũ.
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('nằm trên nút Góp ý và không bị nút đó che', () => {
        state.site = { zalo_oa: OA };
        render(<ZaloFloatButton />);

        // Góp ý chiếm 20→68px; nút này phải ở 80px trở lên.
        const link = screen.getByRole('link', { name: 'Liên hệ Zalo' });
        expect(link.className).toContain('bottom-[80px]');
        expect(link.className).toContain('fixed');
    });
});
