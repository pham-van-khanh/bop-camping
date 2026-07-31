import RentalRangeBar from '@/Components/site/RentalRangeBar';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-3kn9 (T4) — thanh khoảng ngày trên trang danh sách.
 *
 * Chốt hai điều dễ vỡ nhất:
 *  1. Lịch LUÔN thu gọn mặc định (DateRangeCalendar cao 2 tháng, mở sẵn thì đẩy lưới
 *     sản phẩm xuống rất sâu ngay khi vào trang) — chỉ mở khi khách bấm.
 *  2. "Bỏ lọc ngày" giữ lại các filter khác (cat/q/sort/vi-tri) — bỏ ngày không được reset cả trang.
 */

const getMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    router: { get: getMock },
}));

const locations = [
    { name: 'Vinh', slug: 'vinh' },
    { name: 'Hà Nội', slug: 'ha-noi' },
];

const preserve = { cat: 'leu-cam-trai', q: 'leu', sort: 'low' };

function renderBar(
    overrides: Partial<Parameters<typeof RentalRangeBar>[0]> = {},
) {
    return render(
        <RentalRangeBar
            start="2030-08-12"
            end="2030-08-14"
            viTri="vinh"
            serviceLocations={locations}
            targetPath="/thiet-bi"
            preserveParams={preserve}
            {...overrides}
        />,
    );
}

/** Lịch nhận diện qua tiêu đề của DateRangeCalendar. */
const calendar = () => screen.queryByText('Chọn ngày nhận và trả');

describe('RentalRangeBar', () => {
    beforeEach(() => {
        getMock.mockClear();
    });

    it('đã có ngày thì thu gọn lịch và hiện tóm tắt khoảng', () => {
        renderBar();

        expect(calendar()).not.toBeInTheDocument();
        expect(screen.getByText('12/08 → 14/08')).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Đổi ngày' }),
        ).toBeInTheDocument();
    });

    /**
     * Lịch LUÔN thu gọn mặc định, kể cả khi chưa chọn ngày (ce91ee3): DateRangeCalendar cao
     * 2 tháng nên mở sẵn sẽ đẩy lưới sản phẩm xuống rất sâu ngay khi vào trang.
     * Khách chưa chọn ngày thì thấy lời mời + nút "Chọn ngày", chưa có gì để bỏ lọc.
     */
    it('chưa có ngày thì vẫn thu gọn, hiện nút Chọn ngày và không có nút bỏ lọc', () => {
        renderBar({ start: '', end: '' });

        expect(calendar()).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Chọn ngày' }),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Bỏ lọc ngày' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(/Chọn ngày đi để biết thiết bị nào còn rảnh/),
        ).toBeInTheDocument();
    });

    it('chưa có ngày: bấm Chọn ngày thì mở lịch', async () => {
        const user = userEvent.setup();
        renderBar({ start: '', end: '' });

        await user.click(screen.getByRole('button', { name: 'Chọn ngày' }));

        expect(calendar()).toBeInTheDocument();
    });

    it('bấm Đổi ngày mở lịch, bấm lại thì thu gọn', async () => {
        const user = userEvent.setup();
        renderBar();

        await user.click(screen.getByRole('button', { name: 'Đổi ngày' }));
        expect(calendar()).toBeInTheDocument();

        await user.click(screen.getByRole('button', { name: 'Thu gọn' }));
        expect(calendar()).not.toBeInTheDocument();
    });

    /**
     * Hồi quy: bỏ lọc ngày từng kéo mất luôn ?vi-tri= (component nhận viTri làm prop riêng
     * nhưng lại chỉ gửi preserveParams, mà trang không nhét vi-tri vào đó).
     */
    it('Bỏ lọc ngày giữ nguyên các filter khác VÀ địa điểm', async () => {
        const user = userEvent.setup();
        renderBar();

        await user.click(screen.getByRole('button', { name: 'Bỏ lọc ngày' }));

        expect(getMock).toHaveBeenCalledWith(
            '/thiet-bi',
            { ...preserve, 'vi-tri': 'vinh' },
            { preserveState: false },
        );
        // start/end KHÔNG được xuất hiện — đó chính là mục đích của nút này.
        const [, query] = getMock.mock.calls[0];
        expect(query).not.toHaveProperty('start');
        expect(query).not.toHaveProperty('end');
    });

    it('không có địa điểm thì không gửi key vi-tri rỗng', async () => {
        const user = userEvent.setup();
        renderBar({ viTri: '' });

        await user.click(screen.getByRole('button', { name: 'Bỏ lọc ngày' }));

        expect(getMock.mock.calls[0][1]).not.toHaveProperty('vi-tri');
    });

    it('hiện số món hết hàng khi có, câu trung tính khi không', () => {
        const { unmount } = renderBar({ unavailableCount: 3 });
        expect(
            screen.getByText('3 thiết bị hết hàng trong khoảng này'),
        ).toBeInTheDocument();
        unmount();

        renderBar({ unavailableCount: 0 });
        expect(
            screen.getByText('Đang xem đồ còn rảnh trong khoảng này'),
        ).toBeInTheDocument();
    });

    it('dùng được cho combo với danh từ riêng', () => {
        renderBar({
            noun: 'combo',
            unavailableCount: 2,
            targetPath: '/combos',
        });

        expect(
            screen.getByText('2 combo hết hàng trong khoảng này'),
        ).toBeInTheDocument();
    });
});
