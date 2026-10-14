import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import ProductCard from '@/Components/site/ProductCard';
import type { ProductResource } from '@/types/product';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-5nrb — hàng giá dưới thẻ phải xuống dòng được.
 *
 * Lưới /thiet-bi để `grid-cols-2` trên mobile nên thẻ chỉ rộng ~140px, hẹp hơn tổng
 * "giá/ngày" + "cọc ..." (~195px). Hàng giá vốn là flex một dòng => phần tiền cọc tràn
 * ra ngoài và bị `overflow-hidden` của thẻ cắt mất.
 *
 * jsdom KHÔNG đo được layout thật (xem adr_frontend_component_testing) nên test này chốt
 * phần hợp đồng kiểm được: hàng giá có `flex-wrap` (được phép rơi xuống dòng) và mỗi cụm
 * số tiền `whitespace-nowrap` (rơi cả cụm, không gãy giữa con số). Chồng lấn thật vẫn phải
 * mắt thường/trình duyệt.
 */

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, ...p }: { children: React.ReactNode }) => (
        <a {...p}>{children}</a>
    ),
}));
vi.mock('framer-motion', () => ({
    motion: {
        div: ({ children }: { children: React.ReactNode }) => (
            <div>{children}</div>
        ),
    },
}));

const product = {
    id: 1,
    name: 'Bàn cắm trại gấp gọn Naturehike',
    slug: 'ban-cam-trai',
    price_per_day: 80000,
    deposit: 400000,
    quantity: 4,
    thumbnail: null,
    description: 'Bàn nhỏ gọn, siêu nhẹ.',
    category: { name: 'Bàn ghế', slug: 'ban-ghe-da-ngoai' },
    locations: [],
    all_locations: false,
} as unknown as ProductResource;

const combo: ComboCardData = {
    id: 1,
    name: 'Combo 2 người',
    slug: 'combo-2-nguoi',
    combo_price: 250000,
    sum_individual: 320000,
    savings_amount: 70000,
    savings_percent: 22,
    suitable_for: 2,
};

describe('hàng giá dưới thẻ được phép xuống dòng (bopcamping-5nrb)', () => {
    it('thẻ sản phẩm: tiền cọc nằm cùng hàng flex-wrap với giá/ngày', () => {
        render(<ProductCard p={product} />);

        const deposit = screen.getByText(/^cọc/);
        expect(deposit.className).toContain('whitespace-nowrap');

        const row = deposit.parentElement as HTMLElement;
        expect(row.className).toContain('flex-wrap');
        expect(row).toHaveTextContent('80.000đ');
    });

    it('thẻ combo: nút "Xem bộ" nằm cùng hàng flex-wrap với giá combo', () => {
        render(<ComboCard c={combo} />);

        const cta = screen.getByText(/Xem bộ/);
        expect(cta.className).toContain('whitespace-nowrap');

        const row = cta.parentElement as HTMLElement;
        expect(row.className).toContain('flex-wrap');
        expect(row).toHaveTextContent('250.000đ');
    });
});
