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

const partners = [
    {
        key: 'a',
        name: 'Admin A',
        capital: 40_000_000,
        capital_percent: 57.14,
        profit_percent: 25.71,
        total: 514_286,
    },
    {
        key: 'b',
        name: 'Admin B',
        capital: 30_000_000,
        capital_percent: 42.86,
        profit_percent: 19.29,
        total: 385_714,
    },
];

const sharing = {
    partners,
    reserve_percent: 55,
    reserve_total: 1_100_000,
    distributed_total: 900_000,
    deficit: 0,
    rows: [
        {
            month: '2026-08',
            label: 'T8/2026',
            profit: 5_000_000,
            offset: 3_000_000,
            distributable: 2_000_000,
            reserve: 1_100_000,
            shares: { a: 514_286, b: 385_714 },
        },
        {
            month: '2026-07',
            label: 'T7/2026',
            profit: 5_000_000,
            offset: 5_000_000,
            distributable: 0,
            reserve: 0,
            shares: { a: 0, b: 0 },
        },
    ],
};

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
        sharing,
        expenses: { rows: [], total_count: 0 },
        categories: [
            { value: 'equipment', label: 'Mua thiết bị', color: '#557A2B' },
            { value: 'shipping', label: 'Vận chuyển', color: '#4A7C9B' },
        ],
        capital: [
            {
                id: 1,
                user_id: 11,
                user_name: 'Admin A',
                amount: 40_000_000,
                contributed_on: '2026-06-01',
                contributed_on_label: '01/06/2026',
                note: 'Vốn ban đầu',
            },
        ],
        admins: [
            { id: 11, name: 'Admin A' },
            { id: 12, name: 'Admin B' },
        ],
        can_manage: true,
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
const tileValue = (label: string) => {
    // Lọc theo thẻ DIV: cùng một nhãn ("Admin A") vừa là ô KPI vừa là tiêu đề cột <th>
    // của bảng chia — tra chung chung thì khớp nhầm sang bảng.
    const el = screen
        .getAllByText(label)
        .find(
            (e) =>
                e.tagName === 'DIV' && e.parentElement?.children.length === 3,
        );

    return el?.parentElement?.children[1].textContent;
};

/** Thẻ "Chia lợi nhuận" — để tra bảng chia mà không đụng các bảng khác trên trang. */
const sharingCard = () =>
    screen.getByRole('heading', { name: 'Chia lợi nhuận' }).closest('div')!
        .parentElement!;

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

describe('khối chia lợi nhuận', () => {
    it('hiện quỹ dự phòng và phần của từng người kèm tỉ lệ góp vốn', () => {
        setProps();
        render(<AdminFinance />);

        expect(tileValue('Quỹ dự phòng (55%)')).toBe('1.100.000đ');
        expect(tileValue('Admin A')).toBe('514.286đ');
        expect(tileValue('Admin B')).toBe('385.714đ');
        // Tỉ lệ phải là 57,14 / 42,86 chứ không phải 57 / 43 làm tròn.
        expect(screen.getByText(/57,14%/)).toBeInTheDocument();
        expect(screen.getByText(/42,86%/)).toBeInTheDocument();
    });

    it('chỉ liệt kê tháng THỰC SỰ chia được, không liệt kê tháng bù lỗ hết', () => {
        setProps();
        render(<AdminFinance />);

        const rows = within(sharingCard())
            .getAllByRole('row')
            .map((r) => r.textContent ?? '');

        // T8 chia được 2tr nên phải có; T7 lãi 5tr nhưng bù lỗ sạch nên không lên bảng.
        expect(rows.some((t) => t.startsWith('T8/2026'))).toBe(true);
        expect(rows.some((t) => t.startsWith('T7/2026'))).toBe(false);
    });

    it('nói rõ số tiền đã bù lỗ, không lặng lẽ hiện lãi 5tr mà chia 2tr', () => {
        setProps();
        render(<AdminFinance />);

        // Không có cột "Bù lỗ" thì người xem tưởng phép tính sai.
        expect(screen.getByText('Bù lỗ')).toBeInTheDocument();
        expect(screen.getByText('−3.000.000đ')).toBeInTheDocument();
    });

    it('đang lỗ luỹ kế thì cảnh báo chưa chia được đồng nào', () => {
        setProps({
            sharing: {
                ...sharing,
                deficit: 25_000_000,
                reserve_total: 0,
                distributed_total: 0,
                partners: partners.map((p) => ({ ...p, total: 0 })),
                rows: [],
            },
        });
        render(<AdminFinance />);

        expect(
            screen.getByText(/Chưa chia được đồng nào/i),
        ).toBeInTheDocument();
        expect(screen.getByText('25.000.000đ')).toBeInTheDocument();
        expect(
            screen.getByText(/Chưa có tháng nào đủ lãi để chia/i),
        ).toBeInTheDocument();
    });

    it('không cảnh báo lỗ khi shop đã hết nợ luỹ kế', () => {
        setProps();
        render(<AdminFinance />);

        expect(
            screen.queryByText(/Chưa chia được đồng nào/i),
        ).not.toBeInTheDocument();
    });

    it('chưa khai vốn góp thì nói rõ phải nhập trước, không hiện bảng rỗng khó hiểu', () => {
        setProps({
            sharing: {
                ...sharing,
                partners: [],
                rows: [],
                reserve_total: 0,
                distributed_total: 0,
            },
            capital: [],
        });
        render(<AdminFinance />);

        expect(screen.getByText(/Chưa khai vốn góp\./i)).toBeInTheDocument();
    });
});

describe('phân quyền super admin', () => {
    it('super admin thấy form nhập khoản chi và vốn góp', () => {
        setProps();
        render(<AdminFinance />);

        expect(screen.getByLabelText('Số tiền')).toBeInTheDocument();
        expect(screen.getByLabelText('Người góp vốn')).toBeInTheDocument();
        expect(screen.getByText('Quản lý khoản chi')).toBeInTheDocument();
        expect(screen.getByText('Quản lý vốn góp')).toBeInTheDocument();
    });

    it('admin thường KHÔNG thấy form nhập, chỉ thấy số liệu', () => {
        setProps({
            can_manage: false,
            expenses: {
                rows: [
                    {
                        id: 1,
                        spent_on: '2026-08-01',
                        spent_on_label: '01/08/2026',
                        amount: 2_000_000,
                        category: 'equipment',
                        category_label: 'Mua thiết bị',
                        note: null,
                    },
                ],
                total_count: 1,
            },
        });
        render(<AdminFinance />);

        // Form biến mất hoàn toàn — không render chứ không phải ẩn bằng CSS, vì class
        // `grid` sẽ đè mất thuộc tính hidden.
        expect(screen.queryByLabelText('Số tiền')).not.toBeInTheDocument();
        expect(
            screen.queryByLabelText('Người góp vốn'),
        ).not.toBeInTheDocument();
        expect(screen.queryByText('Thêm khoản chi')).not.toBeInTheDocument();
        expect(screen.queryByText('Thêm vốn góp')).not.toBeInTheDocument();

        // Nút Sửa/Xoá trên từng dòng cũng phải biến mất.
        expect(screen.queryByText('Sửa')).not.toBeInTheDocument();
        expect(screen.queryByText('Xoá')).not.toBeInTheDocument();

        // Nhưng SỐ LIỆU vẫn xem được đầy đủ — hai người góp vốn đều là chủ.
        expect(tileValue('Vốn ban đầu')).toBe('70.000.000đ');
        const expenseCard = screen
            .getByRole('heading', { name: 'Khoản chi' })
            .closest('div')!.parentElement!;
        expect(within(expenseCard).getByText('2.000.000đ')).toBeInTheDocument();
        expect(
            screen.getAllByText(/Chỉ super admin/i).length,
        ).toBeGreaterThanOrEqual(2);
    });

    it('sổ vốn góp hiện đủ ai góp bao nhiêu, ngày nào', () => {
        setProps();
        render(<AdminFinance />);

        const card = screen
            .getByRole('heading', { name: 'Quản lý vốn góp' })
            .closest('div')!.parentElement!;

        // Mỗi giá trị xuất hiện ở nhiều chỗ trong thẻ (ô "Tổng" ở đầu, <option> của ô
        // chọn người góp), nên chỉ soi các ô TD của dòng trong sổ.
        const cell = (pattern: RegExp) =>
            within(card)
                .getAllByText(pattern)
                .find((e) => e.tagName === 'TD');

        expect(cell(/01\/06\/2026/)).toBeTruthy();
        expect(cell(/Admin A/)).toBeTruthy();
        expect(cell(/^40\.000\.000đ$/)).toBeTruthy();
    });
});
