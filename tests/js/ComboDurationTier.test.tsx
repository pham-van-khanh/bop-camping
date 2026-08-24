import ComboDetail from '@/Pages/ComboDetail';
import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-4j3h — trang combo phải áp bậc giảm dài ngày, không chỉ hiện bảng.
 *
 * LỖI GỐC: trang combo tính thẳng `combo_price × qty × days`, trong khi giỏ (`lineRent`)
 * và server (`OrderSplitter` + `tierPercentForDays`) đều đã trừ bậc. Đo thật trên trình
 * duyệt: combo 200.000đ thuê 5 ngày -> trang combo 1.000.000đ, giỏ 800.000đ.
 *
 * Khách thấy giá CAO HƠN thực tế đúng ở chỗ họ đang cân nhắc, và ưu đãi thuê dài ngày
 * thành vô hình — mất luôn lý do để thuê thêm ngày.
 */

const tiers = [
    { minDays: 3, percent: 5 },
    { minDays: 5, percent: 20 },
    { minDays: 10, percent: 30 },
];

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    // Trang combo giờ có form đánh giá (bopcamping-vxwx) nên ComboDetail dùng useForm
    // — thiếu mock này là cả file test nổ, không liên quan gì tới thứ đang kiểm.
    useForm: () => ({
        data: { reviewer_name: '', rating: 0, content: '', media: [] },
        setData: () => {},
        post: () => {},
        processing: false,
        errors: {},
        reset: () => {},
    }),

    Link: ({ children, ...p }: { children: React.ReactNode }) => (
        <a {...p}>{children}</a>
    ),
    usePage: () => ({
        props: {
            durationTiers: tiers,
            site: {
                pickup_hour: 8,
                return_hour: 21,
                zalo_1: { url: 'https://zalo.me/x' },
            },
        },
    }),
}));
vi.mock('@/Layouts/SiteLayout', () => ({
    default: ({ children }: { children: React.ReactNode }) => (
        <div>{children}</div>
    ),
}));
vi.mock('@/Components/site/DateRangeCalendar', () => ({
    default: () => <div />,
}));
vi.mock('@/lib/cart', () => ({
    addLine: vi.fn(),
    clearCart: vi.fn(),
    cartSuggestedRange: () => ({ start: '2026-09-01', end: '2026-09-05' }), // 5 ngày
    locationConflict: () => ({ conflict: false, cartLocations: [] }),
}));
vi.mock('@/lib/bus', () => ({
    emit: vi.fn(),
    EVENTS: { cartChange: 'c', toast: 't' },
}));

const combo = {
    id: 1,
    name: 'Combo QA',
    slug: 'combo-qa',
    description: '',
    combo_price: 200000,
    deposit: 400000,
    sum_individual: 240000,
    savings_amount: 40000,
    savings_percent: 17,
    suitable_for: 2,
    images: [],
    items: [],
    locations: [{ slug: 'vinh', name: 'Vinh' }],
    all_locations: false,
    early_return_pct: 10,
} as unknown as Parameters<typeof ComboDetail>[0]['combo'];

describe('bậc giảm dài ngày trên trang combo (bopcamping-4j3h)', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({ ok: false, json: () => Promise.resolve({}) }),
            ),
        );
    });

    /**
     * CA CHÍNH: 200.000đ × 5 ngày = 1.000.000đ, bậc 5 ngày −20% -> 800.000đ.
     * Con số này phải KHỚP với giỏ và với server, nếu không khách thấy hai giá khác nhau.
     */
    it('tạm tính đã trừ bậc giảm, khớp với giỏ và server', () => {
        render(<ComboDetail combo={combo} />);

        expect(screen.getByText('800.000đ')).toBeInTheDocument();
        expect(screen.queryByText('1.000.000đ')).not.toBeInTheDocument();
    });

    it('hiện bảng bậc giảm để khách biết thuê thêm ngày thì rẻ hơn', () => {
        render(<ComboDetail combo={combo} />);

        expect(
            screen.getByText('🏕️ Thuê dài ngày càng giảm'),
        ).toBeInTheDocument();
        expect(screen.getByText(/≥3 ngày −5%/)).toBeInTheDocument();
        expect(screen.getByText(/≥10 ngày −30%/)).toBeInTheDocument();
    });

    /** Bậc đang áp phải được đánh dấu, nếu không bảng chỉ là chữ trang trí. */
    it('đánh dấu đúng bậc đang được áp', () => {
        render(<ComboDetail combo={combo} />);

        expect(screen.getByText(/≥5 ngày −20% ✓/)).toBeInTheDocument();
        expect(screen.queryByText(/≥3 ngày −5% ✓/)).not.toBeInTheDocument();
        expect(screen.queryByText(/≥10 ngày −30% ✓/)).not.toBeInTheDocument();
    });

    /**
     * Khung giờ dùng CHUNG component với trang sản phẩm — hai trang không được nói hai giờ
     * khác nhau. Test chốt nó có mặt và đọc đúng giờ từ setting shop.
     */
    it('hiện khung giờ nhận/trả theo setting shop', () => {
        render(<ComboDetail combo={combo} />);

        expect(screen.getByText(/Nhận từ/)).toBeInTheDocument();
        expect(screen.getByText('8h')).toBeInTheDocument();
        expect(screen.getByText('21h')).toBeInTheDocument();
    });

    it('có đường dẫn Zalo để xin giờ khác', () => {
        render(<ComboDetail combo={combo} />);

        const a = screen.getByText('Liên hệ Zalo');
        expect(a).toHaveAttribute('href', 'https://zalo.me/x');
    });

    /**
     * Thuê NHIỀU ngày thì không có ô chọn buổi — buổi chỉ có nghĩa với đơn cùng ngày.
     * Mock ở file này trả khoảng 5 ngày nên khối buổi phải KHÔNG xuất hiện.
     */
    it('thuê nhiều ngày -> không hiện ô chọn buổi', () => {
        render(<ComboDetail combo={combo} />);

        expect(screen.queryByText('Chọn buổi')).not.toBeInTheDocument();
    });

    /** Cọc KHÔNG được giảm theo bậc — nó là tiền giữ chân, hoàn lại nguyên vẹn. */
    it('tiền cọc không bị trừ bậc giảm', () => {
        render(<ComboDetail combo={combo} />);

        expect(screen.getByText(/\+ cọc 400\.000đ/)).toBeInTheDocument();
    });
});
