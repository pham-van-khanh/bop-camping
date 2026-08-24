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
