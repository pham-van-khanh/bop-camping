import { ScheduleEditor, type Order } from '@/Pages/Admin/orderShared';
import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-mwjd — ScheduleEditor: admin chốt giờ giao/thu + ghi chú nội bộ shipper
 * trên khối chi tiết đơn (theo khuôn ExtraFeeEditor).
 */

// vi.mock được hoist lên đầu file nên state/spy phải khai báo bằng vi.hoisted.
const state = vi.hoisted(() => ({
    errors: {} as Record<string, string>,
}));
const patchMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { errors: state.errors } }),
    router: { patch: patchMock },
}));

function makeOrder(overrides: Partial<Order> = {}): Order {
    return {
        id: 42,
        code: 'BOP-0042',
        customer_name: 'Nguyễn Văn A',
        customer_phone: '0900000000',
        customer_email: 'a@example.com',
        customer_address: '123 Đường ABC',
        start_date: '01/08/2026',
        end_date: '03/08/2026',
        days: 3,
        is_half_day: false,
        session: null,
        requested_pickup_time: null,
        requested_return_time: null,
        confirmed_pickup_time: null,
        confirmed_return_time: null,
        schedule_note: null,
        schedule_confirmed_at: null,
        extra_fee: 0,
        extra_fee_note: null,
        start_date_iso: '2026-08-01',
        end_date_iso: '2026-08-03',
        total_price: 500000,
        deposit_total: 200000,
        discount_total: 0,
        amount_due: 700000,
        discount_breakdown: null,
        status: 'confirmed',
        payment_status: 'unpaid',
        rental_due: 100000,
        rental_paid: false, rental_paid_at: null, rental_paid_by: null,
        deposit_paid: false, deposit_paid_at: null, deposit_paid_by: null,
        deposit_refund_status: 'pending',
        deposit_refund_note: null,
        note: null,
        created_at: '01/08/2026 10:00',
        items: [],
        vouchers: [],
        referral: null,
        service_location: null,
        location_auto_assigned: false,
        is_parent: false,
        ...overrides,
    };
}

beforeEach(() => {
    state.errors = {};
    patchMock.mockClear();
    // route() là hàm global do Ziggy sinh ra lúc runtime — stub tối thiểu cho test.
    vi.stubGlobal(
        'route',
        (name: string, param?: unknown) =>
            `/admin/orders/${param}/${name.split('.').pop()}`,
    );
});

describe('ScheduleEditor', () => {
    it('render đúng giờ/ghi chú đã chốt sẵn có', () => {
        const order = makeOrder({
            confirmed_pickup_time: '14:30',
            confirmed_return_time: '09:00',
            schedule_note: 'Gọi trước 15 phút',
        });
        render(<ScheduleEditor order={order} />);

        expect(screen.getByLabelText('Giờ giao')).toHaveValue('14:30');
        expect(screen.getByLabelText('Giờ thu')).toHaveValue('09:00');
        expect(screen.getByLabelText('Ghi chú cho shipper')).toHaveValue(
            'Gọi trước 15 phút',
        );
    });

    it('render rỗng khi đơn chưa chốt giờ', () => {
        render(<ScheduleEditor order={makeOrder()} />);

        expect(screen.getByLabelText('Giờ giao')).toHaveValue('');
        expect(screen.getByLabelText('Giờ thu')).toHaveValue('');
        expect(screen.getByLabelText('Ghi chú cho shipper')).toHaveValue('');
    });

    it('đổi giờ + ghi chú rồi bấm "Lưu giờ" gọi router.patch đúng route và payload', async () => {
        const user = userEvent.setup();
        const order = makeOrder({ id: 7 });
        render(<ScheduleEditor order={order} />);

        fireEvent.change(screen.getByLabelText('Giờ giao'), {
            target: { value: '14:30' },
        });
        fireEvent.change(screen.getByLabelText('Giờ thu'), {
            target: { value: '09:00' },
        });
        fireEvent.change(screen.getByLabelText('Ghi chú cho shipper'), {
            target: { value: 'Nhà cuối hẻm' },
        });

        await user.click(screen.getByRole('button', { name: 'Lưu giờ' }));

        expect(patchMock).toHaveBeenCalledTimes(1);
        const [url, payload, options] = patchMock.mock.calls[0];
        expect(url).toBe('/admin/orders/7/schedule');
        expect(payload).toEqual({
            confirmed_pickup_time: '14:30',
            confirmed_return_time: '09:00',
            schedule_note: 'Nhà cuối hẻm',
        });
        expect(options).toMatchObject({ preserveScroll: true });
    });

    it('xoá trắng giờ đã chốt thì gửi null (huỷ chốt)', async () => {
        const user = userEvent.setup();
        const order = makeOrder({
            id: 9,
            confirmed_pickup_time: '14:30',
            confirmed_return_time: '09:00',
        });
        render(<ScheduleEditor order={order} />);

        fireEvent.change(screen.getByLabelText('Giờ giao'), {
            target: { value: '' },
        });
        fireEvent.change(screen.getByLabelText('Giờ thu'), {
            target: { value: '' },
        });

        await user.click(screen.getByRole('button', { name: 'Lưu giờ' }));

        expect(patchMock).toHaveBeenCalledTimes(1);
        const [, payload] = patchMock.mock.calls[0];
        expect(payload).toEqual({
            confirmed_pickup_time: null,
            confirmed_return_time: null,
            schedule_note: null,
        });
    });

    it('hiện lỗi validation từ props.errors', () => {
        state.errors = {
            confirmed_return_time:
                'Giờ thu phải sau giờ giao (đơn trong cùng ngày).',
        };
        render(<ScheduleEditor order={makeOrder()} />);

        expect(
            screen.getByText(
                'Giờ thu phải sau giờ giao (đơn trong cùng ngày).',
            ),
        ).toBeInTheDocument();
    });
});
