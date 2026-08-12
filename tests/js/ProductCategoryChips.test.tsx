import Products from '@/Pages/Products';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-10x2 — chip danh mục phải là LINK, không phải nút.
 *
 * Googlebot có chạy JS nhưng không bấm <button>. Chip là nút nghĩa là
 * /thiet-bi?cat=... không nhận được một internal link nào trên cả site — trang chỉ
 * tồn tại trong sitemap, không hưởng chút liên kết nội bộ nào. Đổi sang <a href> thì
 * href phải trỏ đúng bản CANONICAL (sạch query), khớp với ProductController::canonicalFor
 * và với thứ sitemap khai; trỏ vào bản đầy bộ lọc là lại tự dẫn Google tới URL bị gộp.
 */

const routerGet = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: routerGet },
    usePage: () => ({ props: { site: null, auth: { user: null } } }),
    Link: ({ href, children }: { href: string; children: React.ReactNode }) => (
        <a href={href}>{children}</a>
    ),
}));

vi.mock('@/Layouts/SiteLayout', () => ({
    default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

const baseProps = {
    products: [],
    categories: [
        { id: 1, name: 'Lều cắm trại', slug: 'leu-cam-trai' },
        { id: 2, name: 'Bếp & Nấu ăn', slug: 'bep-nau-an' },
    ],
    service_locations: [],
    filters: { cat: '', q: '', sort: 'pop', vi_tri: '', start: '', end: '' },
    range_summary: null,
};

const chip = (name: string) => screen.getByRole('link', { name });

describe('chip danh mục ở /thiet-bi', () => {
    beforeEach(() => routerGet.mockClear());

    it('render thành link trỏ đúng URL canonical của danh mục', () => {
        render(<Products {...baseProps} />);

        expect(chip('Lều cắm trại')).toHaveAttribute(
            'href',
            '/thiet-bi?cat=leu-cam-trai',
        );
        expect(chip('Bếp & Nấu ăn')).toHaveAttribute(
            'href',
            '/thiet-bi?cat=bep-nau-an',
        );
        // "Tất cả" gỡ bộ lọc nên trỏ về trang danh sách trần.
        expect(chip('Tất cả')).toHaveAttribute('href', '/thiet-bi');
    });

    it('href KHÔNG kèm bộ lọc đang chọn — Google phải được dẫn tới bản canonical', () => {
        render(
            <Products
                {...baseProps}
                filters={{
                    cat: 'bep-nau-an',
                    q: 'lều',
                    sort: 'low',
                    vi_tri: 'vinh',
                    start: '2026-08-20',
                    end: '2026-08-22',
                }}
            />,
        );

        expect(chip('Lều cắm trại')).toHaveAttribute(
            'href',
            '/thiet-bi?cat=leu-cam-trai',
        );
    });

    it('click trái vẫn đi đường Inertia và GIỮ khoảng ngày đang chọn', () => {
        render(
            <Products
                {...baseProps}
                filters={{
                    ...baseProps.filters,
                    start: '2026-08-20',
                    end: '2026-08-22',
                }}
            />,
        );

        fireEvent.click(chip('Lều cắm trại'), { button: 0 });

        expect(routerGet).toHaveBeenCalledTimes(1);
        const [path, query] = routerGet.mock.calls[0];
        expect(path).toBe('/thiet-bi');
        expect(query).toMatchObject({
            cat: 'leu-cam-trai',
            start: '2026-08-20',
            end: '2026-08-22',
        });
    });

    it('ctrl/cmd-click thả cho trình duyệt mở tab mới, không nuốt sự kiện', () => {
        render(<Products {...baseProps} />);

        fireEvent.click(chip('Lều cắm trại'), { button: 0, ctrlKey: true });
        fireEvent.click(chip('Bếp & Nấu ăn'), { button: 0, metaKey: true });

        expect(routerGet).not.toHaveBeenCalled();
    });
});
