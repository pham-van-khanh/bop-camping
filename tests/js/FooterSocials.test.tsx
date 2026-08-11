import Footer from '@/Components/site/Footer';
import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-h0hh — khối icon mạng xã hội từng bị xoá trắng ở commit e2c14f2 mà
 * không test nào kêu, nên chủ shop điền Facebook/TikTok trong admin mà ngoài trang
 * chẳng thấy gì. File này khoá lại hai thứ:
 *   1. Điền link nào thì hiện icon đó (và KHÔNG hiện icon chưa điền).
 *   2. OA và liên hệ theo SỐ là hai đường tách bạch — cột "Liên hệ Zalo" phải
 *      trỏ vào từng số, không phải OA.
 */

const state = vi.hoisted(() => ({
    site: null as Record<string, unknown> | null,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { site: state.site } }),
    Link: ({
        href,
        children,
        ...rest
    }: {
        href: string;
        children: React.ReactNode;
    }) => (
        <a href={href} {...rest}>
            {children}
        </a>
    ),
}));

vi.mock('@/Components/Logo', () => ({
    default: () => <img alt="Bốp Camping" />,
}));

const OA = 'https://zalo.me/791036380751013489';

const site = (over: Record<string, unknown> = {}) => ({
    hotline_primary: '0976544370',
    hotline_secondary: null,
    zalo_oa: OA,
    zalo_1: {
        label: 'Tư vấn',
        phone: '0976544370',
        url: 'https://zalo.me/0976544370',
    },
    zalo_2: { label: null, phone: null, url: null },
    facebook_url: null,
    tiktok_url: null,
    working_hours: null,
    addresses: [],
    ...over,
});

beforeEach(() => {
    state.site = null;
});

describe('icon mạng xã hội ở footer', () => {
    it('hiện Facebook và TikTok khi chủ shop đã điền link', () => {
        state.site = site({
            facebook_url: 'https://facebook.com/bopcamping',
            tiktok_url: 'https://tiktok.com/@bopcamping',
        });
        render(<Footer />);

        expect(screen.getByRole('link', { name: 'Facebook' })).toHaveAttribute(
            'href',
            'https://facebook.com/bopcamping',
        );
        expect(screen.getByRole('link', { name: 'TikTok' })).toHaveAttribute(
            'href',
            'https://tiktok.com/@bopcamping',
        );
    });

    it('không hiện icon của kênh chưa điền link', () => {
        state.site = site({ facebook_url: 'https://facebook.com/bopcamping' });
        render(<Footer />);

        expect(
            screen.getByRole('link', { name: 'Facebook' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('link', { name: 'TikTok' }),
        ).not.toBeInTheDocument();
    });

    it('icon Zalo trỏ vào OA', () => {
        state.site = site();
        render(<Footer />);

        expect(screen.getByRole('link', { name: 'Zalo OA' })).toHaveAttribute(
            'href',
            OA,
        );
    });
});

describe('cột "Liên hệ Zalo" — theo SỐ, không phải OA', () => {
    it('hiện đủ hai số khi admin điền cả hai, mỗi số trỏ đúng zalo.me của nó', () => {
        state.site = site({
            zalo_2: {
                label: 'Hỗ trợ thêm',
                phone: '0373655008',
                url: 'https://zalo.me/0373655008',
            },
        });
        render(<Footer />);

        const first = screen.getByRole('link', { name: /Tư vấn · 0976544370/ });
        const second = screen.getByRole('link', {
            name: /Hỗ trợ thêm · 0373655008/,
        });

        expect(first).toHaveAttribute('href', 'https://zalo.me/0976544370');
        expect(second).toHaveAttribute('href', 'https://zalo.me/0373655008');
        // Chính là lỗi của bopcamping-yki5: hai số cùng trỏ về OA.
        expect(first).not.toHaveAttribute('href', OA);
        expect(second).not.toHaveAttribute('href', OA);
    });

    it('chỉ hiện một dòng khi admin mới điền một số', () => {
        state.site = site();
        const { container } = render(<Footer />);

        const col = within(container).getByText('Liên hệ Zalo').parentElement!;
        expect(within(col).getAllByRole('link')).toHaveLength(1);
    });
});
