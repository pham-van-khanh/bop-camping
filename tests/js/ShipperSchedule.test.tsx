import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-w2yl + lvw3 — card đơn của shipper: mở chi tiết ra mới thấy món/tiền; link
 * Chỉ đường phải encode địa chỉ đúng; nút "Đã giao/Đã thu" và nút thu tiền chỉ hiện đúng
 * trạng thái và phải hỏi lại trước khi gửi (đổi trạng thái/tiền là việc không đùa được).
 * Layout thật vẫn phải xem trên trình duyệt.
 */
const state = vi.hoisted(() => ({ errors: {} as Record<string, string> }));
const patch = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { errors: state.errors, auth: { user: { name: 'Shipper A' } } } }),
    router: { patch, get: vi.fn(), post: vi.fn() },
}));

vi.mock('@/Layouts/ShipperLayout', () => ({
    default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// Ziggy thật nối param theo thứ tự trong URI; stub bắt chước đủ để assert payload/route.
vi.stubGlobal('route', (name: string, params?: number | Record<string, unknown>) =>
    typeof params === 'object' && params !== null
        ? `/${name}/${Object.values(params).join('/')}`
        : `/${name}/${params ?? ''}`);

const ORDER = {
    id: 7,
    code: 'BOP-ABC123',
    time: '14:30',
    time_is_default: false,
    pickup_date: '01/08/2030',
    pickup_time: '14:30',
    pickup_time_is_default: false,
    return_date: '03/08/2030',
    return_time: '12:00',
    return_time_is_default: false,
    customer_name: 'Nguyễn Test',
    customer_phone: '0912345678',
    customer_address: '12 Ngõ 5, Hà Nội',
    status: 'confirmed',
    amount_due: 150000,
    rental_due: 100000,
    rental_paid: false,
    deposit_total: 50000,
    deposit_paid: false,
    deposit_refund_status: 'pending',
    schedule_note: 'Gọi trước 15 phút',
    items: [{ name: 'Lều 2 người', quantity: 1 }],
};

const PROPS = {
    month: '2030-08',
    month_label: 'Tháng 8 · 2030',
    prev_month: '2030-07',
    next_month: '2030-09',
    days: [{ date: '2030-08-01', pickups: 1, returns: 0 }],
    date: '2030-08-01',
    date_label: 'Thứ năm, 01/08/2030',
    today: '2030-08-01',
    min_date: '2030-07-30',
    max_date: '2030-08-15',
    pickups: [ORDER],
    returns: [],
};

/** Card đóng mặc định — mở chi tiết trước khi kiểm nội dung bên trong. */
async function openDetail(user: ReturnType<typeof userEvent.setup>) {
    await user.click(screen.getByRole('button', { expanded: false }));
}

// Import sau khi mock để component nhận bản mock.
const { default: ShipperSchedule } = await import('@/Pages/Shipper/Schedule');

describe('Lịch giao của shipper', () => {
    beforeEach(() => {
        patch.mockClear();
        state.errors = {};
    });

    it('mở Google Maps với địa chỉ khách đã encode', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);
        await openDetail(user);

        expect(screen.getByRole('link', { name: /Chỉ đường/ })).toHaveAttribute(
            'href',
            'https://www.google.com/maps/dir/?api=1&destination=12%20Ng%C3%B5%205%2C%20H%C3%A0%20N%E1%BB%99i',
        );
    });

    it('hiện món, 2 khoản tiền và ghi chú shipper trong chi tiết', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);
        await openDetail(user);

        expect(screen.getByText('Lều 2 người')).toBeInTheDocument();
        expect(screen.getByText('Tiền thuê')).toBeInTheDocument();
        expect(screen.getByText('Tiền cọc')).toBeInTheDocument();
        expect(screen.getByText(/Gọi trước 15 phút/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /0912345678/ })).toHaveAttribute('href', 'tel:0912345678');
    });

    it('thu tiền thuê: hỏi lại 1 bước rồi gọi đúng route kèm kind', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);
        await openDetail(user);

        await user.click(screen.getByRole('button', { name: /Thu tiền thuê/ }));
        expect(patch).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: /Xác nhận thu/ }));
        expect(patch).toHaveBeenCalledWith(
            '/shipper.orders.collect/7/rental',
            {},
            expect.objectContaining({ preserveScroll: true }),
        );
    });

    it('khoản đã thu thì hiện dấu ✓, không còn nút thu', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} pickups={[{ ...ORDER, rental_paid: true, deposit_paid: true }]} />);
        await openDetail(user);

        expect(screen.queryByRole('button', { name: /Thu tiền/ })).not.toBeInTheDocument();
        expect(screen.getAllByText('✓ Đã thu')).toHaveLength(2);
        expect(screen.getByText(/Đã thu đủ tiền/)).toBeInTheDocument();
    });

    it('lượt THU có nút hoàn cọc; đã hoàn rồi thì hiện dấu đã hoàn', async () => {
        const user = userEvent.setup();
        render(
            <ShipperSchedule {...PROPS} pickups={[]} returns={[{ ...ORDER, status: 'renting' }]} />,
        );
        await openDetail(user);
        expect(screen.getByRole('button', { name: 'Đã hoàn cọc' })).toBeInTheDocument();
    });

    it('phải xác nhận thêm một bước mới gửi "đã giao"', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);
        await openDetail(user);

        await user.click(screen.getByRole('button', { name: 'Đã giao xong' }));
        expect(patch).not.toHaveBeenCalled();   // bấm lần đầu chỉ hỏi lại

        await user.click(screen.getByRole('button', { name: /Xác nhận đã giao/ }));
        expect(patch).toHaveBeenCalledWith('/shipper.orders.delivered/7', {}, expect.objectContaining({ preserveScroll: true }));
    });

    it('bấm "Chưa" thì huỷ, không gửi gì', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);
        await openDetail(user);

        await user.click(screen.getByRole('button', { name: 'Đã giao xong' }));
        await user.click(screen.getByRole('button', { name: 'Chưa' }));

        expect(patch).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Đã giao xong' })).toBeInTheDocument();
    });

    it('đơn chờ xác nhận thì không có nút đánh dấu', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} pickups={[{ ...ORDER, status: 'pending' }]} />);
        await openDetail(user);

        expect(screen.queryByRole('button', { name: 'Đã giao xong' })).not.toBeInTheDocument();
        expect(screen.getByText('Chờ shop xác nhận đơn')).toBeInTheDocument();
    });

    it('đơn đã giao rồi thì hiện dấu đã xong', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} pickups={[{ ...ORDER, status: 'renting' }]} />);
        await openDetail(user);

        expect(screen.getByText('✓ Đã giao')).toBeInTheDocument();
    });

    it('lượt THU dùng nút "Đã thu đồ" và gọi route thu đồ', async () => {
        const user = userEvent.setup();
        render(
            <ShipperSchedule
                {...PROPS}
                pickups={[]}
                returns={[{ ...ORDER, status: 'renting' }]}
            />,
        );
        await openDetail(user);

        await user.click(screen.getByRole('button', { name: 'Đã thu đồ' }));
        await user.click(screen.getByRole('button', { name: /Xác nhận đã thu đồ/ }));

        expect(patch).toHaveBeenCalledWith('/shipper.orders.collected/7', {}, expect.objectContaining({ preserveScroll: true }));
    });

    it('hiện cả mốc giao và mốc thu trong chi tiết', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);
        await openDetail(user);

        expect(screen.getByText('01/08/2030 · 14:30')).toBeInTheDocument();
        expect(screen.getByText('03/08/2030 · 12:00')).toBeInTheDocument();
    });

    it('KHÔNG in giờ ở đầu card — giờ chỉ nằm trong chi tiết (feedback 31/07)', () => {
        render(<ShipperSchedule {...PROPS} />);

        // Card đóng: chỉ có mã đơn + tên + địa chỉ, không có con giờ nào.
        expect(screen.queryByText('14:30')).not.toBeInTheDocument();
        expect(screen.getByText('BOP-ABC123')).toBeInTheDocument();
    });

    it('giờ mặc định được ghi rõ ở dòng mốc trong chi tiết', async () => {
        const user = userEvent.setup();
        render(
            <ShipperSchedule
                {...PROPS}
                pickups={[{ ...ORDER, pickup_time: '08:00', pickup_time_is_default: true }]}
            />,
        );
        await openDetail(user);

        expect(screen.getByText('01/08/2030 · 08:00')).toBeInTheDocument();
        expect(screen.getByText('mặc định')).toBeInTheDocument();
    });

    it('mốc không có giờ thì ghi "chưa chốt giờ" ở dòng đó', async () => {
        const user = userEvent.setup();
        render(
            <ShipperSchedule
                {...PROPS}
                pickups={[{ ...ORDER, time: null, pickup_time: null, pickup_time_is_default: false }]}
            />,
        );
        await openDetail(user);

        expect(screen.getByText('01/08/2030 · chưa chốt giờ')).toBeInTheDocument();
    });

    it('hiện lỗi trạng thái trả về từ server', async () => {
        const user = userEvent.setup();
        state.errors = { status: 'Đơn chưa được xác nhận hoặc đã giao rồi.' };
        render(<ShipperSchedule {...PROPS} />);
        await openDetail(user);

        expect(screen.getByText('Đơn chưa được xác nhận hoặc đã giao rồi.')).toBeInTheDocument();
    });
});
