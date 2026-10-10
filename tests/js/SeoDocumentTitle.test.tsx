import { SeoTitle } from '@/Layouts/SiteLayout';
import { documentTitle } from '@/lib/seo';
import { render, waitFor } from '@testing-library/react';
import { useEffect } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-ft1j — title phía client ghi đè title server sau hydrate.
 *
 * Server in `<title inertia>` = seo.title (vd "Lều X — Thuê tại BỐP CAMPING"). Bản cũ: trang
 * tự đặt `<Head title={product.name}>` và callback app.tsx luôn nối " - BỐP CAMPING" → sau
 * hydrate thành "Lều X - BỐP CAMPING". Googlebot index DOM đã render nên title client thắng.
 *
 * Nay: SiteLayout đặt `<Head title={seo.title}>`, callback (documentTitle) không nối brand khi
 * title đã có → document.title trùng khít seo.title, cả lần đầu lẫn khi điều hướng SPA.
 */

const page = vi.hoisted(() => ({
    props: {} as { seo?: { title?: string; url?: string } },
}));

// Ranh giới ngoài: usePage (props trang) và Head (head manager của Inertia). Head giả lập
// đúng cách Inertia làm — đi qua title callback mà app.tsx đăng ký (documentTitle).
vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    Head: ({ title }: { title?: string }) => {
        useEffect(() => {
            if (title !== undefined) document.title = documentTitle(title);
        }, [title]);
        return null;
    },
}));

beforeEach(() => {
    document.title = 'server-title';
    page.props = {};
});

describe('SeoTitle trong SiteLayout', () => {
    it('document.title = ĐÚNG seo.title server, không nối thêm brand', async () => {
        page.props = {
            seo: {
                title: 'Lều X — Thuê tại BỐP CAMPING',
                url: '/thiet-bi/leu-x',
            },
        };
        render(<SeoTitle />);

        await waitFor(() =>
            expect(document.title).toBe('Lều X — Thuê tại BỐP CAMPING'),
        );
    });

    it('điều hướng SPA sang trang khác → title đổi theo seo.title mới', async () => {
        page.props = { seo: { title: 'Lều X — Thuê tại BỐP CAMPING' } };
        const { rerender } = render(<SeoTitle />);
        await waitFor(() =>
            expect(document.title).toBe('Lều X — Thuê tại BỐP CAMPING'),
        );

        page.props = {
            seo: { title: 'Combo Gia Đình — Thuê trọn bộ tại BỐP CAMPING' },
        };
        rerender(<SeoTitle />);

        await waitFor(() =>
            expect(document.title).toBe(
                'Combo Gia Đình — Thuê trọn bộ tại BỐP CAMPING',
            ),
        );
    });

    it('trang không có seo.title (chỉ có url mặc định) → không đụng title', () => {
        page.props = { seo: { url: '/gio-thue' } };
        render(<SeoTitle />);

        expect(document.title).toBe('server-title');
    });
});

describe('documentTitle (title callback của createInertiaApp)', () => {
    it('title đã có brand → giữ nguyên, không nhân đôi brand', () => {
        expect(documentTitle('Lều X — Thuê tại BỐP CAMPING')).toBe(
            'Lều X — Thuê tại BỐP CAMPING',
        );
        expect(documentTitle('Chính sách bảo mật | BỐP CAMPING')).toBe(
            'Chính sách bảo mật | BỐP CAMPING',
        );
        // Brand viết không dấu / khác hoa thường cũng tính là đã có (giống withBrand PHP).
        expect(documentTitle('Về Bop Camping')).toBe('Về Bop Camping');
    });

    it('title ngắn của admin/shipper/auth → nối brand đúng một lần', () => {
        expect(documentTitle('Admin · Sản phẩm')).toBe(
            'Admin · Sản phẩm - BỐP CAMPING',
        );
        expect(documentTitle('Lịch của tôi')).toBe(
            'Lịch của tôi - BỐP CAMPING',
        );
    });

    it('rỗng (mặc định của head manager) → chỉ brand, không còn " - BỐP CAMPING"', () => {
        expect(documentTitle('')).toBe('BỐP CAMPING');
        expect(documentTitle('   ')).toBe('BỐP CAMPING');
    });
});
