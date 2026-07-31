import ComboLocationPicker from '@/Pages/Admin/combo/ComboLocationPicker';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-dwa5 (T3) — chọn cơ sở bán combo + bảng "Món tại cơ sở này".
 *
 * Chốt đúng ranh giới đã quyết ở PRD mục 6 (R2):
 *  - chip enable/disable theo TƯ CÁCH THÀNH VIÊN (món có phục vụ ở cơ sở đó không)
 *  - tồn CHỈ để hiển thị: combo mọi món tồn 0 vẫn phải chọn được cơ sở
 */

const VINH = { id: 1, name: 'Vinh', slug: 'vinh' };
const HANOI = { id: 2, name: 'Hà Nội', slug: 'ha-noi' };

const LEU = { id: 10, name: 'Lều', service_location_ids: [1, 2] };
const DEM = { id: 11, name: 'Đệm hơi', service_location_ids: [1] }; // chỉ Vinh

function renderPicker(
    overrides: Partial<Parameters<typeof ComboLocationPicker>[0]> = {},
) {
    const onChange = vi.fn();
    const utils = render(
        <ComboLocationPicker
            locations={[VINH, HANOI]}
            locationStock={{ 1: { 10: 3, 11: 5 }, 2: { 10: 2 } }}
            products={[LEU, DEM]}
            items={[
                { product_id: 10, quantity: 1 },
                { product_id: 11, quantity: 1 },
            ]}
            value={[]}
            onChange={onChange}
            {...overrides}
        />,
    );
    return { ...utils, onChange };
}

const chip = (name: string) =>
    screen.getByRole('button', { name: new RegExp(name) });

describe('ComboLocationPicker', () => {
    it('chặn cơ sở có món không phục vụ, kèm lý do', () => {
        renderPicker();

        expect(chip('Vinh')).toBeEnabled();
        // Đệm hơi không phục vụ Hà Nội -> chặn.
        expect(chip('Hà Nội')).toBeDisabled();
        expect(
            screen.getByText(/Đệm hơi.*không phục vụ ở đây/),
        ).toBeInTheDocument();
    });

    it('bấm chip hợp lệ thì gọi onChange với id đó', async () => {
        const user = userEvent.setup();
        const { onChange } = renderPicker();

        await user.click(chip('Vinh'));

        expect(onChange).toHaveBeenCalledWith([1]);
    });

    /**
     * CA QUYẾT ĐỊNH — tồn 0 KHÔNG được chặn chọn cơ sở. Đây đúng trạng thái combo
     * `relax`/`bbq-party` trên prod (mọi món tồn 0).
     */
    it('mọi món tồn 0 thì chip vẫn chọn được', () => {
        renderPicker({
            products: [
                { id: 10, name: 'Bàn gấp', service_location_ids: [1, 2] },
                { id: 11, name: 'Ghế', service_location_ids: [1, 2] },
            ],
            locationStock: { 1: { 10: 0, 11: 0 }, 2: { 10: 0, 11: 0 } },
        });

        expect(chip('Vinh')).toBeEnabled();
        expect(chip('Hà Nội')).toBeEnabled();
    });

    it('bảng chỉ liệt kê món tồn > 0, món tồn 0 nằm ở dòng cảnh báo', () => {
        renderPicker({
            // Vinh: Lều còn 3, Đệm hơi hết.
            locationStock: { 1: { 10: 3, 11: 0 }, 2: { 10: 2 } },
            value: [1],
        });

        expect(screen.getByText('Món tại Vinh')).toBeInTheDocument();
        expect(screen.getByText('còn 3')).toBeInTheDocument();

        // Đệm hơi (tồn 0) phải ở dòng cảnh báo, KHÔNG ở danh sách còn hàng.
        const warning = screen.getByText(/1 món đang hết hàng tại cơ sở này/);
        expect(warning).toBeInTheDocument();
        expect(warning.textContent).toContain('Đệm hơi');
        expect(warning.textContent).toContain('Vẫn lưu được');
    });

    it('chưa chọn món thì không chọn được cơ sở nào', () => {
        renderPicker({ items: [] });

        expect(chip('Vinh')).toBeDisabled();
        expect(chip('Hà Nội')).toBeDisabled();
        expect(
            screen.getByText(/Chọn sản phẩm cho combo trước/),
        ).toBeInTheDocument();
    });

    /** Thêm món làm cơ sở đang tích thành không hợp lệ → tự bỏ tích + báo ra ngoài. */
    it('đổi món làm cơ sở hết hợp lệ thì tự bỏ tích', () => {
        const onChange = vi.fn();
        const onAutoDeselect = vi.fn();

        render(
            <ComboLocationPicker
                locations={[VINH, HANOI]}
                locationStock={{ 1: { 10: 3, 11: 5 }, 2: { 10: 2 } }}
                products={[LEU, DEM]}
                // Hà Nội đang được tích, nhưng items có Đệm hơi (không phục vụ Hà Nội).
                items={[
                    { product_id: 10, quantity: 1 },
                    { product_id: 11, quantity: 1 },
                ]}
                value={[1, 2]}
                onChange={onChange}
                onAutoDeselect={onAutoDeselect}
            />,
        );

        expect(onChange).toHaveBeenCalledWith([1]);
        expect(onAutoDeselect).toHaveBeenCalledWith([HANOI]);
    });

    it('không có cơ sở nào đang mở thì báo rõ', () => {
        renderPicker({ locations: [] });

        expect(
            screen.getByText(/Chưa có cơ sở nào đang mở/),
        ).toBeInTheDocument();
    });

    it('hiện lỗi từ server', () => {
        renderPicker({ error: 'Có món không phục vụ tại cơ sở đã chọn.' });

        expect(
            screen.getByText('Có món không phục vụ tại cơ sở đã chọn.'),
        ).toBeInTheDocument();
    });
});
