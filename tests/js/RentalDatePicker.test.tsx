import RentalDatePicker from '@/Components/site/RentalDatePicker';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-oub7 (T3) — RentalDatePicker: module "Bạn đi ngày nào?" (PRD FR-1).
 *
 * DateRangeCalendar hiển thị 2 tháng (tháng hiện tại + tháng sau) và không gắn
 * nhãn/ngày lên từng nút — chỉ có số ngày làm accessible name. Số ngày 5 và 10
 * luôn tồn tại ở mọi tháng (>=28 ngày) nên dùng ổn định cho test bất kể ngày
 * chạy test là hôm nào. Tháng sau (occurrence thứ 2 trong DOM) luôn nằm trong
 * tương lai nên không bao giờ bị disable vì "quá khứ".
 */

const getMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    router: { get: getMock },
}));

const locations = [
    { name: 'Vinh', slug: 'vinh' },
    { name: 'Hà Nội', slug: 'ha-noi' },
];

/** Chọn ngày nhận (start) = mùng 5 và ngày trả (end) = mùng 10 của THÁNG SAU. */
async function pickNextMonthRange(user: ReturnType<typeof userEvent.setup>) {
    const fives = screen.getAllByRole('button', { name: '5' });
    const tens = screen.getAllByRole('button', { name: '10' });
    await user.click(fives[1]); // tháng sau (occurrence thứ 2)
    await user.click(tens[1]);
}

beforeEach(() => {
    getMock.mockClear();
});

describe('RentalDatePicker', () => {
    it('nút Xác nhận disabled khi chưa chọn đủ khoảng ngày, enabled khi đã đủ start + end', async () => {
        const user = userEvent.setup();
        render(
            <RentalDatePicker variant="hero" serviceLocations={locations} />,
        );

        const confirmBtn = screen.getByRole('button', { name: 'Xác nhận' });
        expect(confirmBtn).toBeDisabled();

        const fives = screen.getAllByRole('button', { name: '5' });
        await user.click(fives[1]); // chỉ mới chọn start
        expect(confirmBtn).toBeDisabled();

        const tens = screen.getAllByRole('button', { name: '10' });
        await user.click(tens[1]); // đủ start + end
        expect(confirmBtn).toBeEnabled();
    });

    it('bấm Xác nhận gọi router.get đúng targetPath (mặc định /thiet-bi) kèm query start/end', async () => {
        const user = userEvent.setup();
        render(
            <RentalDatePicker variant="hero" serviceLocations={locations} />,
        );

        await pickNextMonthRange(user);
        await user.click(screen.getByRole('button', { name: 'Xác nhận' }));

        expect(getMock).toHaveBeenCalledTimes(1);
        const [path, query, options] = getMock.mock.calls[0];
        expect(path).toBe('/thiet-bi');
        expect(query).toHaveProperty('start');
        expect(query).toHaveProperty('end');
        expect(query.start < query.end).toBe(true);
        expect(query).not.toHaveProperty('vi-tri');
        expect(options).toMatchObject({ preserveState: false });
    });

    it('giữ preserveParams (cat/q/sort) và bỏ key rỗng khi bấm Xác nhận ở variant compact', async () => {
        const user = userEvent.setup();
        render(
            <RentalDatePicker
                variant="compact"
                serviceLocations={locations}
                targetPath="/combos"
                preserveParams={{ cat: 'leu-trai', q: '', sort: 'pop' }}
            />,
        );

        await pickNextMonthRange(user);
        await user.click(screen.getByRole('button', { name: 'Xác nhận' }));

        expect(getMock).toHaveBeenCalledTimes(1);
        const [path, query] = getMock.mock.calls[0];
        expect(path).toBe('/combos');
        expect(query).toMatchObject({ cat: 'leu-trai', sort: 'pop' });
        expect(query).not.toHaveProperty('q');
        expect(query).toHaveProperty('start');
        expect(query).toHaveProperty('end');
    });

    it('chọn địa điểm rồi Xác nhận thì query có "vi-tri"; không chọn thì không có key này', async () => {
        const user = userEvent.setup();
        render(
            <RentalDatePicker variant="hero" serviceLocations={locations} />,
        );

        await user.selectOptions(
            screen.getByLabelText('Địa điểm nhận đồ'),
            'ha-noi',
        );
        await pickNextMonthRange(user);
        await user.click(screen.getByRole('button', { name: 'Xác nhận' }));

        expect(getMock).toHaveBeenCalledTimes(1);
        const [, query] = getMock.mock.calls[0];
        expect(query).toMatchObject({ 'vi-tri': 'ha-noi' });
    });
});
