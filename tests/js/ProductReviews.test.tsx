import ProductReviews from '@/Components/site/ProductReviews';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-saeb — component đánh giá dùng chung cho SẢN PHẨM và COMBO.
 *
 * Trước đây nó chỉ phục vụ trang sản phẩm và tự dựng URL gửi từ productSlug. Nay hai trang
 * dùng chung nên đích gửi do trang truyền vào — gửi nhầm route là combo lại đi tạo đánh giá
 * cho sản phẩm, mà nhìn UI thì không thấy sai. Đó là thứ test này giữ.
 */
const posted = vi.hoisted(() => ({ urls: [] as string[] }));

vi.mock('@inertiajs/react', () => ({
    useForm: () => ({
        data: { reviewer_name: 'Ai Đó', rating: 5, content: '', media: [] },
        setData: () => {},
        post: (url: string) => posted.urls.push(url),
        processing: false,
        errors: {},
        reset: () => {},
    }),
}));

const base = {
    submitUrl: '/combos/combo-cap-doi/danh-gia',
    targetName: 'Combo Cặp Đôi',
    reviews: [],
    summary: { count: 0, avg: 0 },
    isLoggedIn: true,
};

const withMedia = {
    id: 1,
    reviewer_name: 'Hà',
    rating: 5,
    content: 'Trọn bộ ổn',
    meta: 'Tháng 8, 2026',
    media: [
        { type: 'image' as const, url: '/media/a.jpg' },
        { type: 'image' as const, url: '/media/b.jpg' },
    ],
};

beforeEach(() => {
    posted.urls = [];
});

describe('ProductReviews — dùng chung cho sản phẩm và combo', () => {
    it('gửi đúng URL trang truyền vào, không tự dựng route sản phẩm', async () => {
        render(<ProductReviews {...base} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Gửi đánh giá' }),
        );

        expect(posted.urls).toEqual(['/combos/combo-cap-doi/danh-gia']);
    });

    it('ai cũng gửi được — không có nhánh nào giấu form đi', () => {
        render(<ProductReviews {...base} />);

        expect(
            screen.getByRole('button', { name: 'Gửi đánh giá' }),
        ).toBeInTheDocument();
    });

    it('khách vãng lai phải nhập tên; khách đã đăng nhập thì không hỏi lại', () => {
        const { unmount } = render(
            <ProductReviews {...base} isLoggedIn={false} />,
        );
        expect(screen.getByPlaceholderText('Tên của bạn')).toBeInTheDocument();
        unmount();

        render(<ProductReviews {...base} isLoggedIn />);
        expect(screen.queryByPlaceholderText('Tên của bạn')).toBeNull();
    });

    it('chưa có đánh giá nào thì nói rõ, không hiện carousel rỗng', () => {
        render(<ProductReviews {...base} />);

        expect(screen.getByText(/Chưa có đánh giá nào/)).toBeVisible();
    });

    it('bấm ảnh trong modal thì mở lightbox, chuyển được ảnh và Esc đóng lightbox trước', async () => {
        render(
            <ProductReviews
                {...base}
                reviews={[withMedia]}
                summary={{ count: 1, avg: 5 }}
            />,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Xem' }));

        // Ô ảnh trong modal phải là NÚT bấm được — trước đây là div trơn (bopcamping-ydls).
        await userEvent.click(
            screen.getByRole('button', {
                name: /Xem lớn ảnh 1 Hà gửi kèm đánh giá/,
            }),
        );

        const box = screen.getByRole('dialog', { name: 'Xem ảnh đính kèm' });
        expect(box).toBeVisible();
        expect(screen.getByText('1 / 2')).toBeVisible();
        // object-contain: ảnh khách gửi kèm là bằng chứng, không được cắt mép.
        expect(
            screen.getByAltText(/Ảnh Hà gửi kèm đánh giá \(1\/2\)/),
        ).toHaveClass('object-contain');

        await userEvent.click(screen.getByRole('button', { name: 'Ảnh sau' }));
        expect(screen.getByText('2 / 2')).toBeVisible();

        // Esc lần đầu chỉ đóng lightbox — modal đánh giá vẫn còn để khách đọc tiếp.
        await userEvent.keyboard('{Escape}');
        expect(
            screen.queryByRole('dialog', { name: 'Xem ảnh đính kèm' }),
        ).toBeNull();
        expect(screen.getByText('Combo Cặp Đôi')).toBeVisible();
    });

    it('bấm nền lightbox chỉ đóng lightbox, không đóng luôn modal đánh giá', async () => {
        render(
            <ProductReviews
                {...base}
                reviews={[withMedia]}
                summary={{ count: 1, avg: 5 }}
            />,
        );
        await userEvent.click(screen.getByRole('button', { name: 'Xem' }));
        await userEvent.click(
            screen.getByRole('button', {
                name: /Xem lớn ảnh 1 Hà gửi kèm đánh giá/,
            }),
        );

        await userEvent.click(
            screen.getByRole('dialog', { name: 'Xem ảnh đính kèm' }),
        );

        expect(
            screen.queryByRole('dialog', { name: 'Xem ảnh đính kèm' }),
        ).toBeNull();
        expect(screen.getByText('Combo Cặp Đôi')).toBeVisible();
    });

    it('modal xem ảnh mang tên thứ đang được đánh giá (combo chứ không phải sản phẩm)', async () => {
        render(
            <ProductReviews
                {...base}
                reviews={[
                    {
                        id: 1,
                        reviewer_name: 'Hà',
                        rating: 5,
                        content: 'Trọn bộ ổn',
                        meta: 'Tháng 8, 2026',
                        media: [],
                    },
                ]}
                summary={{ count: 1, avg: 5 }}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Xem' }));

        expect(screen.getByText('Combo Cặp Đôi')).toBeVisible();
    });
});
