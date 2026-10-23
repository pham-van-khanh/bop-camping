import ZaloFloatButton from '@/Components/site/ZaloFloatButton';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Nút Zalo nổi mở thẳng Zalo cá nhân số 0373655008 (bopcamping-ygsu, trước là OA) —
 * một đích duy nhất, không có panel chọn tài khoản. OA nằm ở footer, không phải ở đây.
 */

// vi.mock được hoist lên đầu file nên state phải khai báo bằng vi.hoisted.
const state = vi.hoisted(() => ({
    site: null as { zalo_oa: string; zalo_float: string } | null,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { site: state.site } }),
}));

const OA = 'https://zalo.me/791036380751013489';
const FLOAT = 'https://zalo.me/0373655008';

beforeEach(() => {
    state.site = null;
});

describe('nút Zalo nổi', () => {
    it('không render khi chưa có link', () => {
        const { container } = render(<ZaloFloatButton />);
        expect(container).toBeEmptyDOMElement();
    });

    it('mở thẳng Zalo theo số trong tab mới, không bày panel chọn', () => {
        state.site = { zalo_oa: OA, zalo_float: FLOAT };
        render(<ZaloFloatButton />);

        const link = screen.getByRole('link', { name: 'Liên hệ Zalo' });
        expect(link).toHaveAttribute('href', FLOAT);
        // OA đã chuyển xuống footer — nút nổi không được trỏ về OA nữa.
        expect(link).not.toHaveAttribute('href', OA);
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noreferrer');
        // Không còn nút bung panel như bản cũ.
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('nằm trên nút Góp ý và không bị nút đó che', () => {
        state.site = { zalo_oa: OA, zalo_float: FLOAT };
        render(<ZaloFloatButton />);

        // Góp ý chiếm 20→68px; nút này phải ở 80px trở lên.
        const link = screen.getByRole('link', { name: 'Liên hệ Zalo' });
        expect(link.className).toContain('bottom-[80px]');
        expect(link.className).toContain('fixed');
    });
});
