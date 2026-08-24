import SystemReviewCta from '@/Components/site/SystemReviewCta';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-saeb — CTA viết đánh giá tổng thể ở trang chủ.
 *
 * Ba trạng thái cổng, và cái đáng kiểm nhất là KHÔNG mở form cho người không đủ điều
 * kiện: trang chủ là chỗ dễ bị spam nhất. Chặn ở đây chỉ để khỏi cho khách gõ xong mới
 * bị từ chối — server vẫn chặn thật (xem HomeSystemReviewTest).
 */
const state = vi.hoisted(() => ({ posted: [] as string[] }));

vi.mock('@inertiajs/react', () => ({
    useForm: () => ({
        data: { rating: 0, content: '' },
        setData: () => {},
        post: (url: string) => state.posted.push(url),
        processing: false,
        errors: {},
        reset: () => {},
    }),
}));

const openLogin = vi.hoisted(() => ({ calls: 0 }));
vi.mock('@/lib/bus', () => ({
    emit: () => {
        openLogin.calls++;
    },
    EVENTS: { openLogin: 'bop:open-login' },
}));

beforeEach(() => {
    state.posted = [];
    openLogin.calls = 0;
    // route() là global do Ziggy cung cấp ở runtime; test chỉ cần một hàm trả chuỗi.
    (globalThis as { route?: unknown }).route = (name: string) => `/${name}`;
});

describe('SystemReviewCta — cổng viết đánh giá', () => {
    it('chưa đăng nhập: bấm CTA mở modal đăng nhập, KHÔNG mở form', async () => {
        render(<SystemReviewCta isLoggedIn={false} canReview={false} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Viết đánh giá của bạn' }),
        );

        expect(openLogin.calls).toBe(1);
        expect(screen.queryByPlaceholderText(/Đồ thuê, giao nhận/)).toBeNull();
    });

    it('đã đăng nhập nhưng chưa thuê: nhắc ngay ở CTA và không mở form khi bấm', async () => {
        render(<SystemReviewCta isLoggedIn canReview={false} />);

        // Lời nhắc hiện sẵn, khách biết trước khi bấm.
        expect(screen.getByText(/hẹn bạn sau chuyến đi đầu tiên/)).toBeVisible();

        await userEvent.click(
            screen.getByRole('button', { name: 'Viết đánh giá của bạn' }),
        );

        expect(screen.queryByPlaceholderText(/Đồ thuê, giao nhận/)).toBeNull();
        expect(openLogin.calls).toBe(0);
    });

    it('đủ điều kiện: bấm CTA mở form chấm sao + nội dung', async () => {
        render(<SystemReviewCta isLoggedIn canReview />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Viết đánh giá của bạn' }),
        );

        expect(
            screen.getByPlaceholderText(/Đồ thuê, giao nhận/),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: '5 sao' })).toBeVisible();
    });

    it('nút gửi bị chặn khi chưa chấm sao — không cho gửi đánh giá trống', async () => {
        render(<SystemReviewCta isLoggedIn canReview />);
        await userEvent.click(
            screen.getByRole('button', { name: 'Viết đánh giá của bạn' }),
        );

        const submit = screen.getByRole('button', { name: 'Gửi đánh giá' });
        expect(submit).toBeDisabled();

        await userEvent.click(submit);
        expect(state.posted).toEqual([]);
    });
});
