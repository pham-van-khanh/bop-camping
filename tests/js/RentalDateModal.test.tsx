import RentalDateModal from '@/Components/site/RentalDateModal';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Popup đặt lịch mở từ ô đặt lịch trên banner trang chủ.
 *
 * Chốt các thứ dễ vỡ của một modal: đóng bằng ESC / backdrop / nút ×, KHÔNG đóng khi
 * bấm vào trong panel (bấm chọn ngày mà modal tự sập là lỗi chí tử), và trả lại
 * body.overflow khi unmount (quên thì trang nền bị kẹt không cuộn được).
 */

const getMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    router: { get: getMock },
}));

const locations = [
    { name: 'Vinh', slug: 'vinh' },
    { name: 'Hà Nội', slug: 'ha-noi' },
];

function renderModal(onClose = vi.fn()) {
    const utils = render(
        <RentalDateModal serviceLocations={locations} onClose={onClose} />,
    );
    return { ...utils, onClose };
}

describe('RentalDateModal', () => {
    beforeEach(() => getMock.mockClear());
    afterEach(() => {
        document.body.style.overflow = '';
    });

    it('là dialog có nhãn và chứa lịch', () => {
        renderModal();

        const dialog = screen.getByRole('dialog', { name: 'Chọn ngày thuê' });
        expect(dialog).toHaveAttribute('aria-modal', 'true');
        expect(screen.getByText('Chọn ngày nhận và trả')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Xác nhận' })).toBeDisabled();
    });

    it('đóng bằng nút ×', async () => {
        const user = userEvent.setup();
        const { onClose } = renderModal();

        await user.click(screen.getByRole('button', { name: 'Đóng' }));

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('đóng bằng phím ESC', async () => {
        const user = userEvent.setup();
        const { onClose } = renderModal();

        await user.keyboard('{Escape}');

        expect(onClose).toHaveBeenCalledTimes(1);
    });

    it('KHÔNG đóng khi bấm vào trong panel', async () => {
        const user = userEvent.setup();
        const { onClose } = renderModal();

        // Bấm vào tiêu đề lịch — thao tác này xảy ra liên tục khi khách chọn ngày.
        await user.click(screen.getByText('Chọn ngày nhận và trả'));

        expect(onClose).not.toHaveBeenCalled();
    });

    it('chặn scroll trang nền khi mở và trả lại khi đóng', () => {
        const { unmount } = renderModal();

        expect(document.body.style.overflow).toBe('hidden');

        unmount();

        expect(document.body.style.overflow).toBe('');
    });

    it('lịch dùng ô ngày cỡ lớn (lg) cho PC', () => {
        renderModal();

        // size='lg' -> ô ngày h-[40px]; 'md' mặc định là h-[30px].
        const day = screen.getAllByRole('button', { name: '15' })[0];
        expect(day.className).toContain('h-[40px]');
        expect(day.className).not.toContain('h-[30px]');
    });
});
