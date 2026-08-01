import ProductCard from '@/Components/site/ProductCard';
import type { ProductResource } from '@/types/product';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-kvcc — badge tồn kho trên thẻ sản phẩm phải nói rõ con số ở kho NÀO.
 *
 * Khi khách chưa chọn kho, con số là max qua các kho đang mở. Không nói kho nào thì khách
 * thấy "Còn 3 bộ", thêm 3 vào giỏ, rồi tới checkout mới biết KHÔNG kho nào đủ 3 — vì cả giỏ
 * phải nằm trong MỘT kho. Server chỉ gửi `available_at` khi các kho lệch nhau.
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
        article: ({ children }: { children: React.ReactNode }) => (
            <article>{children}</article>
        ),
    },
}));

const base = {
    id: 1,
    name: 'Lều Cloud-Up 2',
    slug: 'leu-cloud-up-2',
    price_per_day: 120000,
    deposit: 300000,
    quantity: 4,
    thumbnail: null,
    category: { name: 'Lều', slug: 'leu' },
    locations: [
        { name: 'Vinh', slug: 'vinh' },
        { name: 'Hà Nội', slug: 'ha-noi' },
    ],
    all_locations: true,
} as unknown as ProductResource;

const ve = (p: Partial<ProductResource>) =>
    render(<ProductCard p={{ ...base, ...p } as ProductResource} />);

describe('badge tồn kho trên thẻ sản phẩm (bopcamping-kvcc)', () => {
    it('các kho lệch nhau -> nói rõ con số ở kho nào', () => {
        ve({ available: 3, in_range: true, available_at: 'Vinh' });

        expect(screen.getByText('Còn 3 bộ tại Vinh')).toBeInTheDocument();
    });

    it('các kho bằng nhau -> chỉ hiện con số, không kèm tên kho', () => {
        ve({ available: 3, in_range: true, available_at: null });

        expect(screen.getByText('Còn 3 bộ')).toBeInTheDocument();
    });

    it('sắp hết mà lệch kho -> giữ được cả cảnh báo và tên kho', () => {
        ve({ available: 1, in_range: true, available_at: 'Hà Nội' });

        expect(
            screen.getByText('Sắp hết · 1 bộ tại Hà Nội'),
        ).toBeInTheDocument();
    });

    it('hết hàng -> không kèm tên kho (không có gì để chỉ)', () => {
        ve({ available: 0, in_range: false, available_at: 'Vinh' });

        expect(screen.getByText('Hết hàng')).toBeInTheDocument();
        expect(screen.queryByText(/tại Vinh/)).not.toBeInTheDocument();
    });

    /** Chưa chọn ngày: badge nói tổng tồn tĩnh, chưa lọc theo khoảng nào. */
    it('chưa chọn ngày -> không kèm tên kho', () => {
        ve({ available: null, in_range: null, available_at: null });

        expect(screen.getByText('Còn 4 bộ')).toBeInTheDocument();
        expect(screen.queryByText(/tại /)).not.toBeInTheDocument();
    });

    /**
     * PHÒNG THỦ: nếu server có lúc gửi sai hợp đồng — available_at kèm available=null (chưa
     * chọn ngày) — thì `stock` đang là tồn TĨNH cả kho. Ghép tên kho vào con số đó là tạo ra
     * một con số sai kiểu mới, đúng thứ bead này muốn diệt. Phải bỏ qua tên kho.
     */
    it('hợp đồng sai (available_at mà chưa chọn ngày) -> bỏ qua tên kho', () => {
        ve({ available: null, in_range: null, available_at: 'Vinh' });

        expect(screen.getByText('Còn 4 bộ')).toBeInTheDocument();
        expect(screen.queryByText(/tại Vinh/)).not.toBeInTheDocument();
    });

    /** Cẩn thận với dữ liệu cũ: prop mới thiếu hẳn thì vẫn phải vẽ được. */
    it('thiếu hẳn available_at -> vẫn vẽ bình thường', () => {
        ve({ available: 2, in_range: true });

        expect(screen.getByText('Sắp hết · 2 bộ')).toBeInTheDocument();
    });
});
