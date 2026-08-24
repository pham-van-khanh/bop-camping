import ComboDetail from '@/Pages/ComboDetail';
import { render, screen, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-gmup — khối "Combo này có tại" trên trang combo.
 *
 * Test PHP đã chốt server trả đúng `served` cho từng cơ sở. Test này canh phần CÒN LẠI:
 * cơ sở `served=false` phải thật sự bị khoá trên giao diện. Đảo cờ đó trong JSX là lỗi
 * không backend test nào bắt được — khách sẽ tưởng combo có ở nơi nó không có.
 */

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
    // ComboDetail đọc durationTiers từ prop dùng chung (bopcamping-4j3h) — thiếu mock này
    // là cả file test nổ, không liên quan gì tới thứ đang kiểm.
    usePage: () => ({ props: { durationTiers: [], site: {} } }),
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
    cartSuggestedRange: () => null,
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
} as unknown as Parameters<typeof ComboDetail>[0]['combo'];

const VINH = { id: 1, name: 'Vinh', slug: 'vinh', served: true };
const HANOI = { id: 2, name: 'Hà Nội', slug: 'ha-noi', served: false };

/** Thẻ cơ sở theo tên — mỗi thẻ có aria-disabled nên tìm được không cần test-id. */
const the = (ten: string) =>
    screen
        .getAllByText(ten)
        .map((el) => el.closest('[aria-disabled]'))
        .find(Boolean) as HTMLElement;

describe('khối "Combo này có tại" (bopcamping-gmup)', () => {
    beforeEach(() => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve({ ok: false, json: () => Promise.resolve({}) }),
            ),
        );
    });

    it('cơ sở CÓ combo thì mở, cơ sở KHÔNG có thì khoá', () => {
        render(<ComboDetail combo={combo} stores={[VINH, HANOI]} />);

        expect(the('Vinh')).toHaveAttribute('aria-disabled', 'false');
        expect(the('Hà Nội')).toHaveAttribute('aria-disabled', 'true');
    });

    it('nói rõ bằng chữ chứ không chỉ bằng màu', () => {
        render(<ComboDetail combo={combo} stores={[VINH, HANOI]} />);

        expect(
            within(the('Vinh')).getByText('có combo này'),
        ).toBeInTheDocument();
        expect(within(the('Hà Nội')).getByText('không có')).toBeInTheDocument();
    });

    /**
     * Hai thẻ phải TRÔNG khác nhau, không chỉ khác ở chữ. Cố ý so "khác nhau" thay vì bám
     * vào tên class cụ thể: đổi màu/bo góc sau này không được làm đỏ test, nhưng làm hai
     * thẻ giống hệt nhau thì phải đỏ.
     */
    it('thẻ khoá và thẻ mở không được trông giống hệt nhau', () => {
        render(<ComboDetail combo={combo} stores={[VINH, HANOI]} />);

        expect(the('Vinh').className).not.toBe(the('Hà Nội').className);
    });

    it('combo ở cả hai nơi -> không khoá cái nào', () => {
        render(
            <ComboDetail
                combo={combo}
                stores={[VINH, { ...HANOI, served: true }]}
            />,
        );

        expect(the('Vinh')).toHaveAttribute('aria-disabled', 'false');
        expect(the('Hà Nội')).toHaveAttribute('aria-disabled', 'false');
    });

    it('combo chưa gắn nơi nào -> khoá hết', () => {
        render(
            <ComboDetail
                combo={combo}
                stores={[{ ...VINH, served: false }, HANOI]}
            />,
        );

        expect(the('Vinh')).toHaveAttribute('aria-disabled', 'true');
        expect(the('Hà Nội')).toHaveAttribute('aria-disabled', 'true');
    });

    /** Một cơ sở thì không có gì để so — bày ra chỉ tốn chỗ. */
    it('chỉ một cơ sở đang mở -> ẩn hẳn khối', () => {
        render(<ComboDetail combo={combo} stores={[VINH]} />);

        expect(screen.queryByText('Combo này có tại')).not.toBeInTheDocument();
    });

    /** Trang cũ chưa truyền prop này thì vẫn phải vẽ được, không nổ. */
    it('không truyền stores -> vẫn vẽ bình thường', () => {
        render(<ComboDetail combo={combo} />);

        expect(screen.queryByText('Combo này có tại')).not.toBeInTheDocument();
    });
});
