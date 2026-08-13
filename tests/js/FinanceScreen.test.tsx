import AdminFinance from '@/Pages/Admin/Finance';
import { render, screen, within } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-n4qy — màn Tài chính.
 *
 * Trọng tâm là những chỗ số liệu dễ sai mà nhìn mắt thường không thấy: cọc bị lẫn vào
 * lãi, thanh tiến độ tràn khi tiêu quá vốn, và trạng thái rỗng khi shop chưa có dữ liệu.
 */

const state = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: vi.fn() },
    useForm: () => ({
        data: { spent_on: '', amount: '', category: 'equipment', note: '' },
        setData: vi.fn(),
        reset: vi.fn(),
        clearErrors: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        errors: {},
        processing: false,
    }),
    usePage: () => ({ props: state.props }),
}));

vi.mock('@/Layouts/AdminLayout', () => ({
    default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// route() là helper toàn cục do Ziggy cắm vào window ở app thật.
vi.stubGlobal('route', (name: string) => `/${name}`);

const overview = {
    capital: 70_000_000,
    spent: 30_000_000,
    capital_left: 40_000_000,
    revenue: 15_000_000,
    profit: -15_000_000,
    payback_percent: 50,
    held_deposit: 5_000_000,
    pipeline_revenue: 2_000_000,
    returned_count: 3,
};

const monthly = [
    {
        month: '2026-07',
        label: 'T7/2026',
        revenue: 5_000_000,
        expense: 30_000_000,
        profit: -25_000_000,
        cum_revenue: 5_000_000,
        cum_expense: 30_000_000,
    },
    {
        month: '2026-08',
        label: 'T8/2026',
        revenue: 10_000_000,
        expense: 0,
        profit: 10_000_000,
        cum_revenue: 15_000_000,
        cum_expense: 30_000_000,
    },
];

const setProps = (over: Record<string, unknown> = {}) => {
    state.props = {
        period: 'all',
        overview,
        period_summary: {
            revenue: 15_000_000,
            expense: 30_000_000,
            profit: -15_000_000,
            returned_count: 3,
        },
        monthly,
        by_category: [
            {
                category: 'equipment',
                label: 'Mua thiết bị',
                total: 20_000_000,
                count: 1,
                percent: 66.7,
            },
            {
                category: 'shipping',
                label: 'Vận chuyển',
                total: 10_000_000,
                count: 1,
                percent: 33.3,
            },
        ],
        expenses: { rows: [], total_count: 0 },
        categories: [
            { value: 'equipment', label: 'Mua thiết bị', color: '#557A2B' },
            { value: 'shipping', label: 'Vận chuyển', color: '#4A7C9B' },
        ],
        ...over,
    };
};

/**
 * Giá trị của ô KPI mang nhãn cho trước.
 *
 * Không tra thẳng theo số: cùng một con số xuất hiện ở nhiều ô (vd 30tr vừa là "Đã chi"
 * toàn thời gian vừa là "Chi trong kỳ"), tra theo số thì test đậu kể cả khi hai ô bị
 * gán nhầm giá trị cho nhau.
 */
const tileValue = (label: string) =>
    screen.getByText(label).parentElement!.children[1].textContent;

describe('màn Tài chính', () => {
    it('hiện vốn, đã chi, vốn còn lại và tiến độ hoàn vốn đúng từng ô', () => {
        setProps();
        render(<AdminFinance />);

        expect(tileValue('Vốn ban đầu')).toBe('70.000.000đ');
        expect(tileValue('Đã chi')).toBe('30.000.000đ');
        expect(tileValue('Vốn còn lại')).toBe('40.000.000đ');
        expect(tileValue('Đã hoàn vốn')).toBe('50%');
    });

    it('tách bạch doanh thu, lợi nhuận, cọc và tiền sắp thu', () => {
        setProps();
        render(<AdminFinance />);

        expect(tileValue('Doanh thu')).toBe('15.000.000đ');
        // Đang lỗ 15tr: nhãn phải đổi chứ không in "Lợi nhuận -15tr" khó hiểu.
        expect(tileValue('Đang lỗ')).toBe('-15.000.000đ');
        expect(tileValue('Cọc đang giữ')).toBe('5.000.000đ');
        expect(tileValue('Sắp thu')).toBe('2.000.000đ');
    });

    it('cảnh báo cọc đang giữ KHÔNG phải doanh thu', () => {
        setProps();
        render(<AdminFinance />);

        // Đây là cái bẫy chính của mô hình cho thuê — cảnh báo phải hiện thành chữ,
        // không chỉ là một ô số vô hồn.
        expect(screen.getByText(/không phải/i)).toBeInTheDocument();
        expect(screen.getAllByText(/5\.000\.000đ/).length).toBeGreaterThan(0);
    });

    it('không cảnh báo khi shop không cầm đồng cọc nào', () => {
        setProps({ overview: { ...overview, held_deposit: 0 } });
        render(<AdminFinance />);

        expect(screen.queryByText(/không phải/i)).not.toBeInTheDocument();
    });

    it('thanh tiến độ không tràn quá 100% khi tiêu quá vốn', () => {
        setProps({
            overview: {
                ...overview,
                spent: 90_000_000,
                capital_left: -20_000_000,
                payback_percent: 250,
            },
        });
        render(<AdminFinance />);

        const bars = screen.getAllByRole('progressbar');
        bars.forEach((b) =>
            expect(Number(b.getAttribute('aria-valuenow'))).toBeLessThanOrEqual(
                100,
            ),
        );
        // Vẫn phải NÓI RÕ là đã vượt vốn, chứ không lặng lẽ kẹp về 100%.
        expect(screen.getByText(/Vượt vốn/i)).toBeInTheDocument();
    });

    it('báo đã hoà vốn khi thu luỹ kế đuổi kịp chi luỹ kế', () => {
        setProps({
            monthly: [
                { ...monthly[0] },
                {
                    ...monthly[1],
                    cum_revenue: 30_000_000,
                    cum_expense: 30_000_000,
                },
            ],
        });
        render(<AdminFinance />);

        expect(screen.getByText(/Hoà vốn từ/i)).toBeInTheDocument();
    });

    it('báo chưa hoà vốn kèm số còn thiếu', () => {
        setProps();
        render(<AdminFinance />);

        expect(screen.getByText(/Chưa hoà vốn/i)).toBeInTheDocument();
        // 30tr chi − 15tr thu = còn thiếu 15tr.
        const box = screen.getByText(/Chưa hoà vốn/i).closest('div')!;
        expect(within(box).getByText('15.000.000đ')).toBeInTheDocument();
    });

    it('chưa chi đồng nào thì KHÔNG báo "còn thiếu 0đ nữa là hoà vốn"', () => {
        setProps({
            overview: {
                ...overview,
                spent: 0,
                capital_left: 70_000_000,
                payback_percent: 0,
            },
        });
        render(<AdminFinance />);

        // Câu cũ đọc thành "sắp hoà vốn tới nơi" trong khi shop chưa bỏ ra đồng nào.
        expect(screen.queryByText(/Còn thiếu 0đ/)).not.toBeInTheDocument();
        expect(
            screen.getByText(/Chưa có khoản chi nào để tính hoàn vốn/i),
        ).toBeInTheDocument();
        // 0% cũng sai nghĩa — chưa chi thì không có tỉ lệ nào để nói.
        expect(tileValue('Đã hoàn vốn')).toBe('—');
    });

    it('chỉ báo đã hoà vốn khi thu THẬT SỰ bằng hoặc vượt chi', () => {
        // 99,96% làm tròn 1 chữ số thành 100,0 — nếu nhìn phần trăm thì báo nhầm.
        setProps({
            overview: {
                ...overview,
                spent: 30_000_000,
                revenue: 29_990_000,
                payback_percent: 100,
            },
        });
        render(<AdminFinance />);

        expect(screen.getByText(/Còn thiếu/)).toBeInTheDocument();
        expect(screen.queryByText(/Đã thu về đủ/)).not.toBeInTheDocument();
    });

    it('shop chưa có dữ liệu thì hiện hướng dẫn thay vì chart rỗng', () => {
        setProps({ monthly: [], by_category: [] });
        render(<AdminFinance />);

        expect(screen.getAllByText(/Chưa có dữ liệu/i).length).toBeGreaterThan(
            0,
        );
    });
});
