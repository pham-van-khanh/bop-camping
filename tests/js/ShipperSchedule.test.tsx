import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-w2yl — card đơn của shipper: link Chỉ đường phải encode địa chỉ đúng, nút
 * "Đã giao/Đã thu" chỉ hiện đúng trạng thái và phải hỏi lại trước khi gửi (đổi trạng thái
 * là gửi mail cho khách). Layout thật vẫn phải xem trên trình duyệt.
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

vi.stubGlobal('route', (name: string, id?: number) => `/${name}/${id ?? ''}`);

const ORDER = {
    id: 7,
    code: 'BOP-ABC123',
    time: '14:30',
    customer_name: 'Nguyễn Test',
    customer_phone: '0912345678',
    customer_address: '12 Ngõ 5, Hà Nội',
    status: 'confirmed',
    payment_status: 'unpaid',
    amount_due: 150000,
    deposit_total: 50000,
    schedule_note: 'Gọi trước 15 phút',
    items: [{ name: 'Lều 2 người', quantity: 1 }],
};

const PROPS = {
    date: '2030-08-01',
    date_label: 'Thứ năm, 01/08/2030',
    today: '2030-08-01',
    prev_date: '2030-07-31',
    next_date: '2030-08-02',
    pickups: [ORDER],
    returns: [],
};

// Import sau khi mock để component nhận bản mock.
const { default: ShipperSchedule } = await import('@/Pages/Shipper/Schedule');

describe('Lịch giao của shipper', () => {
    beforeEach(() => {
        patch.mockClear();
        state.errors = {};
    });

    it('mở Google Maps với địa chỉ khách đã encode', () => {
        render(<ShipperSchedule {...PROPS} />);

        expect(screen.getByRole('link', { name: /Chỉ đường/ })).toHaveAttribute(
            'href',
            'https://www.google.com/maps/dir/?api=1&destination=12%20Ng%C3%B5%205%2C%20H%C3%A0%20N%E1%BB%99i',
        );
    });

    it('hiện số cần thu và ghi chú shipper', () => {
        render(<ShipperSchedule {...PROPS} />);

        expect(screen.getByText(/Thu khi giao/)).toBeInTheDocument();
        expect(screen.getByText(/Gọi trước 15 phút/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /0912345678/ })).toHaveAttribute('href', 'tel:0912345678');
    });

    it('phải xác nhận thêm một bước mới gửi "đã giao"', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);

        await user.click(screen.getByRole('button', { name: 'Đã giao xong' }));
        expect(patch).not.toHaveBeenCalled();   // bấm lần đầu chỉ hỏi lại

        await user.click(screen.getByRole('button', { name: /Xác nhận/ }));
        expect(patch).toHaveBeenCalledWith('/shipper.orders.delivered/7', {}, expect.objectContaining({ preserveScroll: true }));
    });

    it('bấm "Chưa" thì huỷ, không gửi gì', async () => {
        const user = userEvent.setup();
        render(<ShipperSchedule {...PROPS} />);

        await user.click(screen.getByRole('button', { name: 'Đã giao xong' }));
        await user.click(screen.getByRole('button', { name: 'Chưa' }));

        expect(patch).not.toHaveBeenCalled();
        expect(screen.getByRole('button', { name: 'Đã giao xong' })).toBeInTheDocument();
    });

    it('đơn chờ xác nhận thì không có nút đánh dấu', () => {
        render(<ShipperSchedule {...PROPS} pickups={[{ ...ORDER, status: 'pending' }]} />);

        expect(screen.queryByRole('button', { name: 'Đã giao xong' })).not.toBeInTheDocument();
        expect(screen.getByText('Chờ shop xác nhận đơn')).toBeInTheDocument();
    });

    it('đơn đã giao rồi thì hiện dấu đã xong', () => {
        render(<ShipperSchedule {...PROPS} pickups={[{ ...ORDER, status: 'renting' }]} />);

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

        await user.click(screen.getByRole('button', { name: 'Đã thu đồ' }));
        await user.click(screen.getByRole('button', { name: /Xác nhận/ }));

        expect(patch).toHaveBeenCalledWith('/shipper.orders.collected/7', {}, expect.objectContaining({ preserveScroll: true }));
    });

    it('hiện lỗi trạng thái trả về từ server', () => {
        state.errors = { status: 'Đơn chưa được xác nhận hoặc đã giao rồi.' };
        render(<ShipperSchedule {...PROPS} />);

        expect(screen.getByText('Đơn chưa được xác nhận hoặc đã giao rồi.')).toBeInTheDocument();
    });
});
