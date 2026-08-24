import ComboDetail from '@/Pages/ComboDetail';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-w7gi — chọn buổi (nửa ngày) cho combo.
 *
 * Chủ shop chốt phương án CỘT RIÊNG `combos.early_return_discount_pct` để linh động: combo
 * nào muốn giảm thì nhập, không thì để 0.
 *
 * Ba nơi phải nói CÙNG một giá: trang combo, giỏ (`lineRent`), server (`priceLine`). Lệch
 * một nơi là khách thấy giá khác nhau ở từng bước.
 */

const tiers = [{ minDays: 5, percent: 20 }];

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
                morning_end_hour: 12,
                afternoon_start_hour: 13,
                return_hour: 20,
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
    cartSuggestedRange: () => ({ start: '2026-09-01', end: '2026-09-01' }), // ĐÚNG 1 NGÀY
    locationConflict: () => ({ conflict: false, cartLocations: [] }),
}));
vi.mock('@/lib/bus', () => ({
    emit: vi.fn(),
    EVENTS: { cartChange: 'c', toast: 't' },
}));

const base = {
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
} as unknown as Parameters<typeof ComboDetail>[0]['combo'];

const ve = (earlyPct: number) =>
    render(
        <ComboDetail
            combo={{ ...base, early_return_pct: earlyPct } as typeof base}
        />,
    );

describe('chọn buổi cho combo (bopcamping-w7gi)', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({ ok: false, json: () => Promise.resolve({}) }),
            ),
        );
    });

    it('thuê 1 ngày -> hiện đủ ba lựa chọn buổi kèm khung giờ', () => {
        ve(10);

        expect(screen.getByText('Chọn buổi')).toBeInTheDocument();
        expect(screen.getByText('Buổi sáng')).toBeInTheDocument();
        expect(screen.getByText('8h–12h')).toBeInTheDocument();
        expect(screen.getByText('13h–20h')).toBeInTheDocument();
        expect(screen.getByText('8h–20h')).toBeInTheDocument();
    });

    it('mặc định là Cả ngày, giá chưa giảm', () => {
        ve(10);

        expect(screen.getByRole('button', { name: /Cả ngày/ })).toHaveAttribute(
            'aria-pressed',
            'true',
        );
        expect(screen.getByText('200.000đ')).toBeInTheDocument();
    });

    /** CA TIỀN BẠC: 200.000đ × 1 ngày, giảm 10% -> 180.000đ. */
    it('chọn buổi sáng -> giá giảm đúng phần trăm của combo', async () => {
        const user = userEvent.setup();
        ve(10);

        await user.click(screen.getByRole('button', { name: /Buổi sáng/ }));

        expect(screen.getByText('180.000đ')).toBeInTheDocument();
    });

    it('chọn buổi chiều cũng giảm y như buổi sáng', async () => {
        const user = userEvent.setup();
        ve(10);

        await user.click(screen.getByRole('button', { name: /Buổi chiều/ }));

        expect(screen.getByText('180.000đ')).toBeInTheDocument();
    });

    /** Combo để 0% -> vẫn chọn được buổi nhưng KHÔNG giảm. Đây là điều chủ shop muốn. */
    it('combo để 0% -> chọn buổi sáng vẫn giữ nguyên giá và không hiện nhãn giảm', async () => {
        const user = userEvent.setup();
        ve(0);

        await user.click(screen.getByRole('button', { name: /Buổi sáng/ }));

        expect(screen.getByText('200.000đ')).toBeInTheDocument();
        expect(screen.queryByText(/^−\d+%$/)).not.toBeInTheDocument();
    });

    it('có % giảm thì nhãn hiện trên hai buổi nửa ngày, không hiện trên Cả ngày', () => {
        ve(10);

        expect(screen.getAllByText('−10%')).toHaveLength(2);
    });

    /** Cọc không bao giờ giảm — tiền giữ chân, hoàn lại nguyên vẹn. */
    it('cọc không giảm khi chọn nửa ngày', async () => {
        const user = userEvent.setup();
        ve(10);

        await user.click(screen.getByRole('button', { name: /Buổi sáng/ }));

        expect(screen.getByText(/\+ cọc 400\.000đ/)).toBeInTheDocument();
    });
});
