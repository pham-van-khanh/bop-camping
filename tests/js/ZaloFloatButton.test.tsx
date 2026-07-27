import ZaloFloatButton from '@/Components/site/ZaloFloatButton';
import type { SiteZalo } from '@/types';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-uen0 — ZaloFloatButton: nút Zalo nổi trên nút Góp ý.
 *
 * Nhánh hiển thị phụ thuộc số tài khoản CÓ `url`:
 *   0 → không render · 1 → link mở thẳng Zalo · 2 → nút mở panel chọn số.
 */

// vi.mock được hoist lên đầu file nên state phải khai báo bằng vi.hoisted.
const state = vi.hoisted(() => ({
    site: null as { zalo_1: SiteZalo; zalo_2: SiteZalo } | null,
}));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { site: state.site } }),
}));

// Thay framer-motion bằng thẻ thường: test kiểm logic hiển thị, không kiểm
// animation. Giữ animation thật sẽ khiến phần tử nán lại trong DOM lúc exit và
// làm test chớp tắt (flaky).
vi.mock('framer-motion', () => {
    const MOTION_PROPS = [
        'initial',
        'animate',
        'exit',
        'transition',
        'whileHover',
        'whileTap',
        'whileInView',
        'viewport',
    ];
    return {
        AnimatePresence: ({ children }: { children: React.ReactNode }) => (
            <>{children}</>
        ),
        motion: new Proxy({} as Record<string, React.ElementType>, {
            get: (_target, tag: string) =>
                function MotionStub({
                    children,
                    ...props
                }: Record<string, unknown> & { children?: React.ReactNode }) {
                    const clean = Object.fromEntries(
                        Object.entries(props).filter(
                            ([k]) => !MOTION_PROPS.includes(k),
                        ),
                    );
                    return React.createElement(tag, clean, children);
                },
        }),
    };
});

const zalo = (over: Partial<SiteZalo> = {}): SiteZalo => ({
    label: 'Tư vấn & đặt đồ',
    phone: '0976544370',
    url: 'https://zalo.me/0976544370',
    ...over,
});

const empty: SiteZalo = { label: null, phone: null, url: null };

const setSite = (zalo_1: SiteZalo, zalo_2: SiteZalo) => {
    state.site = { zalo_1, zalo_2 };
};

beforeEach(() => {
    state.site = null;
});

describe('không có tài khoản Zalo nào', () => {
    it('không render gì cả', () => {
        setSite(empty, empty);
        const { container } = render(<ZaloFloatButton />);
        expect(container).toBeEmptyDOMElement();
    });

    it('coi tài khoản chỉ có SĐT mà thiếu url là không dùng được', () => {
        // url do server resolve (zaloUrl()); nếu null thì không có cách nào mở Zalo.
        setSite(zalo({ url: null }), empty);
        const { container } = render(<ZaloFloatButton />);
        expect(container).toBeEmptyDOMElement();
    });
});

describe('chỉ một tài khoản Zalo', () => {
    it('render link mở thẳng Zalo, không có panel', async () => {
        setSite(zalo(), empty);
        render(<ZaloFloatButton />);

        const link = screen.getByRole('link', {
            name: /Liên hệ Zalo — Tư vấn & đặt đồ/,
        });
        expect(link).toHaveAttribute('href', 'https://zalo.me/0976544370');
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noreferrer');
        expect(link).not.toHaveAttribute('aria-haspopup');
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('vẫn hoạt động khi chủ shop chỉ điền ô Zalo thứ hai', () => {
        setSite(
            empty,
            zalo({
                label: 'Hỗ trợ thêm',
                phone: '0373655008',
                url: 'https://zalo.me/0373655008',
            }),
        );
        render(<ZaloFloatButton />);

        expect(
            screen.getByRole('link', { name: /Hỗ trợ thêm/ }),
        ).toHaveAttribute('href', 'https://zalo.me/0373655008');
    });

    it('dùng nhãn mặc định khi chưa đặt label', () => {
        setSite(zalo({ label: null }), empty);
        render(<ZaloFloatButton />);

        expect(
            screen.getByRole('link', { name: 'Liên hệ Zalo' }),
        ).toBeInTheDocument();
    });
});

describe('hai tài khoản Zalo', () => {
    const bothAccounts = () =>
        setSite(
            zalo(),
            zalo({
                label: 'Hỗ trợ thêm',
                phone: '0373655008',
                url: 'https://zalo.me/0373655008',
            }),
        );

    it('render nút đóng sẵn, chưa hiện panel', () => {
        bothAccounts();
        render(<ZaloFloatButton />);

        const btn = screen.getByRole('button', { name: 'Liên hệ Zalo' });
        expect(btn).toHaveAttribute('aria-haspopup', 'menu');
        expect(btn).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('bấm nút thì hiện đủ cả hai số, mỗi số là một link Zalo', async () => {
        const user = userEvent.setup();
        bothAccounts();
        render(<ZaloFloatButton />);

        await user.click(screen.getByRole('button', { name: 'Liên hệ Zalo' }));

        expect(
            screen.getByRole('button', { name: 'Liên hệ Zalo' }),
        ).toHaveAttribute('aria-expanded', 'true');
        const items = screen.getAllByRole('menuitem');
        expect(items).toHaveLength(2);
        expect(items[0]).toHaveAttribute('href', 'https://zalo.me/0976544370');
        expect(items[1]).toHaveAttribute('href', 'https://zalo.me/0373655008');
        expect(screen.getByText('Tư vấn & đặt đồ')).toBeInTheDocument();
        expect(screen.getByText('0976544370')).toBeInTheDocument();
        expect(screen.getByText('Hỗ trợ thêm')).toBeInTheDocument();
        expect(screen.getByText('0373655008')).toBeInTheDocument();
    });

    it('bấm lại nút thì đóng panel', async () => {
        const user = userEvent.setup();
        bothAccounts();
        render(<ZaloFloatButton />);
        const btn = screen.getByRole('button', { name: 'Liên hệ Zalo' });

        await user.click(btn);
        await user.click(btn);

        expect(btn).toHaveAttribute('aria-expanded', 'false');
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('nhấn Esc thì đóng panel', async () => {
        const user = userEvent.setup();
        bothAccounts();
        render(<ZaloFloatButton />);

        await user.click(screen.getByRole('button', { name: 'Liên hệ Zalo' }));
        await user.keyboard('{Escape}');

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('bấm ra ngoài thì đóng panel', async () => {
        const user = userEvent.setup();
        bothAccounts();
        render(
            <div>
                <button type="button">chỗ khác</button>
                <ZaloFloatButton />
            </div>,
        );

        await user.click(screen.getByRole('button', { name: 'Liên hệ Zalo' }));
        await user.click(screen.getByRole('button', { name: 'chỗ khác' }));

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('chọn một số thì đóng panel', async () => {
        const user = userEvent.setup();
        bothAccounts();
        render(<ZaloFloatButton />);

        await user.click(screen.getByRole('button', { name: 'Liên hệ Zalo' }));
        await user.click(screen.getAllByRole('menuitem')[0]);

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });
});
