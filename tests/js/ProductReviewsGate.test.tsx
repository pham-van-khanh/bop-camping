import ProductReviews from '@/Components/site/ProductReviews';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-saeb — component đánh giá dùng chung cho sản phẩm VÀ combo.
 *
 * Hai bên khác nhau đúng một luật: sản phẩm cho ai cũng gửi được, combo thì phải đã thuê.
 * Luật đó nằm ở cờ `requireRented`, nên đây là chỗ dễ sai nhất khi component được dùng
 * lại — test giữ cả hai chiều.
 */
vi.mock('@inertiajs/react', () => ({
    useForm: () => ({
        data: { reviewer_name: '', rating: 0, content: '', media: [] },
        setData: () => {},
        post: () => {},
        processing: false,
        errors: {},
        reset: () => {},
    }),
}));

const base = {
    submitUrl: '/danh-gia',
    targetName: 'Combo Cặp Đôi',
    reviews: [],
    summary: { count: 0, avg: 0 },
    isLoggedIn: true,
};

const form = () => screen.queryByRole('button', { name: 'Gửi đánh giá' });

describe('ProductReviews — cổng "phải đã thuê"', () => {
    it('sản phẩm (requireRented tắt): vẫn mở form dù chưa thuê', () => {
        render(<ProductReviews {...base} canReview={false} />);

        expect(form()).toBeInTheDocument();
    });

    it('combo (requireRented bật) + chưa thuê: thay form bằng lời nhắc', () => {
        render(
            <ProductReviews
                {...base}
                canReview={false}
                requireRented
                gateHint="Chỉ khách đã thuê trọn bộ combo này mới đánh giá được."
            />,
        );

        expect(form()).toBeNull();
        expect(
            screen.getByText(/Chỉ khách đã thuê trọn bộ combo này/),
        ).toBeVisible();
    });

    it('combo + đã thuê: mở form bình thường', () => {
        render(<ProductReviews {...base} canReview requireRented />);

        expect(form()).toBeInTheDocument();
    });

    it('không có đánh giá nào thì nói rõ, không hiện carousel rỗng', () => {
        render(<ProductReviews {...base} canReview requireRented />);

        expect(screen.getByText(/Chưa có đánh giá nào/)).toBeVisible();
    });
});
