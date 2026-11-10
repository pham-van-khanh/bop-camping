import AdminOrderCreate from '@/Pages/Admin/Orders/Create';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-yv2w — admin tự tạo đơn. Kiểm phần FE tự lo: thêm món, ước tính tiền,
 * cảnh báo thiếu hàng, buổi chỉ hiện khi thuê 1 ngày, và payload gửi server tách
 * đúng items/combos. Giá/tồn thật do server quyết (đã có test PHP).
 */

const state = vi.hoisted(() => ({
    transform: null as null | ((d: Record<string, unknown>) => unknown),
    post: vi.fn(),
    stock: { products: { '1': 1 }, combos: { '7': 5 } } as Record<
        string,
        Record<string, number>
    >,
}));

vi.mock('@inertiajs/react', async () => {
    const React = await import('react');
    return {
        Head: () => null,
        Link: ({ children }: { children: React.ReactNode }) => (
            <a>{children}</a>
        ),
        usePage: () => ({ props: { durationTiers: [] } }),
        useForm: (initial: Record<string, unknown>) => {
            const [data, set] = React.useState(initial);
            return {
                data,
                setData: (k: unknown, v?: unknown) =>
                    typeof k === 'function'
                        ? set(k as (d: typeof data) => typeof data)
                        : set((d) => ({ ...d, [k as string]: v })),
                errors: {},
                processing: false,
                transform: (fn: (d: Record<string, unknown>) => unknown) => {
                    state.transform = () => fn(data);
                },
                post: state.post,
            };
        },
    };
});

vi.mock('axios', () => ({
    default: {
        get: vi.fn(() => Promise.resolve({ data: state.stock })),
    },
}));

vi.mock('@/Layouts/AdminLayout', () => ({
    default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.stubGlobal('route', (name: string) => `/${name}`);

const props = {
    products: [
        {
            id: 1,
            name: 'Lều 4 người',
            category: 'Lều',
            price_per_day: 100000,
            deposit: 200000,
            early_return_pct: 0,
            location_ids: [],
        },
    ],
    combos: [
        {
            id: 7,
            name: 'Combo đôi',
            price_per_day: 150000,
            deposit: 300000,
            early_return_pct: 0,
            location_ids: [],
            items: [{ name: 'Lều 4 người', qty: 1 }],
        },
    ],
    service_locations: [{ id: 3, name: 'Vinh' }],
    delivery_methods: [{ value: 'self_pickup', label: 'Tự đến xem đồ' }],
};

const pick = (name: RegExp) =>
    fireEvent.click(screen.getByRole('button', { name }));

describe('Admin tạo đơn', () => {
    beforeEach(() => {
        state.transform = null;
        state.post.mockClear();
    });

    it('thêm món → hiện dòng + tiền thuê ước tính, buổi hiện khi thuê 1 ngày', async () => {
        render(<AdminOrderCreate {...props} />);
        await screen.findByText('còn 1');

        // Mặc định nhận = trả = hôm nay → có chọn buổi.
        expect(
            screen.getByRole('button', { name: 'Buổi sáng' }),
        ).toBeInTheDocument();

        pick(/Lều 4 người/);

        expect(
            screen.getByRole('spinbutton', { name: /Số lượng Lều 4 người/ }),
        ).toHaveValue(1);
        // 100k × 1 × 1 ngày; khách trả = thuê + cọc 200k
        expect(screen.getByText(/^300\.000\s?đ$/)).toBeInTheDocument();
    });

    it('cảnh báo khi số lượng vượt số còn', async () => {
        render(<AdminOrderCreate {...props} />);
        await screen.findByText('còn 1');

        pick(/Lều 4 người/);
        fireEvent.change(
            screen.getByRole('spinbutton', { name: /Số lượng Lều 4 người/ }),
            { target: { value: '2' } },
        );

        expect(screen.getByText(/Chỉ còn 1/)).toBeInTheDocument();
    });

    it('gửi server tách items/combos, ép kiểu cơ sở + giảm giá', async () => {
        render(<AdminOrderCreate {...props} />);
        await screen.findByText('còn 1');
        pick(/Lều 4 người/);
        pick(/Combo đôi/);
        fireEvent.change(screen.getByRole('combobox', { name: 'Cơ sở' }), {
            target: { value: '3' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Tạo đơn' }));

        expect(state.post).toHaveBeenCalledWith(
            '/admin.orders.store',
            expect.anything(),
        );
        expect(state.transform?.({})).toMatchObject({
            service_location_id: 3,
            items: [{ product_id: 1, quantity: 1 }],
            combos: [{ combo_id: 7, quantity: 1 }],
            discount: null,
        });
    });
});
