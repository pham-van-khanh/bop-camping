import Header from '@/Components/site/Header';
import { EVENTS, on } from '@/lib/bus';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Bấm vào TÊN ở header phải mở menu, KHÔNG được đăng xuất thẳng.
 *
 * Bản cũ: `onClick` của nút tên gọi luôn post(guest.logout). Cái tên trông chẳng giống nút
 * đăng xuất, mà bấm nhầm là mất phiên — với tài khoản chỉ-có-SĐT thì cookie vừa mất là thứ
 * duy nhất giữ tài khoản, phải nhắn Zalo mới vào lại được.
 *
 * Nay bấm tên mở menu hai mục: "Thông tin tài khoản" và "Đăng xuất".
 */

const posted = vi.hoisted(() => ({ urls: [] as string[] }));

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ url: '/' }),
    useForm: () => ({
        post: (url: string) => posted.urls.push(url),
        processing: false,
    }),
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

beforeEach(() => {
    posted.urls = [];
});

describe('menu tài khoản ở header', () => {
    it('bấm vào tên KHÔNG đăng xuất, mà mở menu', () => {
        render(<Header userName="Chị Ngọc" />);

        const btn = screen.getByRole('button', { name: 'Tài khoản (Chị Ngọc)' });
        expect(btn).toHaveAttribute('aria-expanded', 'false');
        // Chưa mở thì chưa có mục nào.
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();

        fireEvent.click(btn);

        expect(btn).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('menu')).toBeInTheDocument();
        // Và tuyệt đối KHÔNG được đăng xuất chỉ vì bấm vào tên.
        expect(posted.urls).toEqual([]);
    });

    it('menu có đúng hai mục: thông tin tài khoản và đăng xuất', () => {
        render(<Header userName="Chị Ngọc" />);
        fireEvent.click(screen.getByRole('button', { name: 'Tài khoản (Chị Ngọc)' }));

        const items = screen.getAllByRole('menuitem');
        expect(items.map((e) => e.textContent)).toEqual([
            'Thông tin tài khoản',
            'Đăng xuất',
        ]);
        expect(items[0]).toHaveAttribute('href', '/tai-khoan');
    });

    it('bấm "Đăng xuất" trong menu mới thật sự đăng xuất', () => {
        render(<Header userName="Chị Ngọc" />);
        fireEvent.click(screen.getByRole('button', { name: 'Tài khoản (Chị Ngọc)' }));
        fireEvent.click(screen.getByRole('menuitem', { name: 'Đăng xuất' }));

        expect(posted.urls).toEqual(['/guest.logout']);
    });

    it('bấm ra ngoài thì đóng menu', () => {
        render(<Header userName="Chị Ngọc" />);
        fireEvent.click(screen.getByRole('button', { name: 'Tài khoản (Chị Ngọc)' }));
        expect(screen.getByRole('menu')).toBeInTheDocument();

        fireEvent.mouseDown(document.body);

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('nhấn Esc thì đóng menu', () => {
        render(<Header userName="Chị Ngọc" />);
        fireEvent.click(screen.getByRole('button', { name: 'Tài khoản (Chị Ngọc)' }));

        act(() => {
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        });

        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
    });

    it('chưa đăng nhập thì nút mở modal đăng nhập, không có menu', () => {
        let mở = false;
        const off = on(EVENTS.openLogin, () => {
            mở = true;
        });
        render(<Header />);

        const btn = screen.getByRole('button', { name: 'Đăng nhập' });
        expect(btn).not.toHaveAttribute('aria-haspopup');
        fireEvent.click(btn);

        expect(mở).toBe(true);
        expect(screen.queryByRole('menu')).not.toBeInTheDocument();
        off();
    });
});
