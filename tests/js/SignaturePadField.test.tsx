import SignaturePadField from '@/Components/SignaturePadField';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * bopcamping-4jao — ô vẽ chữ ký.
 *
 * jsdom không có canvas thật nên signature_pad được mock ở ranh giới thư viện: cái đang
 * kiểm là HÀNH VI của component (khi nào báo có chữ ký, khi nào nút Xoá bật/tắt), không
 * phải chất lượng nét vẽ — thứ chỉ đo được trên trình duyệt thật.
 */

const mocks = vi.hoisted(() => {
    const listeners: Record<string, () => void> = {};

    return {
        listeners,
        clear: vi.fn(),
        off: vi.fn(),
        toDataURL: vi.fn(() => 'data:image/png;base64,FAKE'),
        addEventListener: vi.fn((event: string, cb: () => void) => {
            listeners[event] = cb;
        }),
    };
});

vi.mock('signature_pad', () => ({
    default: class {
        clear = mocks.clear;
        off = mocks.off;
        toDataURL = mocks.toDataURL;
        addEventListener = mocks.addEventListener;
    },
}));

describe('SignaturePadField', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        for (const key of Object.keys(mocks.listeners)) {
            delete mocks.listeners[key];
        }
    });

    it('mới mở thì nút Xoá chữ ký bị khoá', () => {
        render(<SignaturePadField onChange={vi.fn()} />);

        expect(
            screen.getByRole('button', { name: 'Xoá chữ ký' }),
        ).toBeDisabled();
    });

    it('vẽ xong một nét thì báo data URL PNG lên cha', () => {
        const onChange = vi.fn();
        render(<SignaturePadField onChange={onChange} />);

        mocks.listeners.endStroke?.();

        expect(onChange).toHaveBeenLastCalledWith('data:image/png;base64,FAKE');
    });

    it('bấm Xoá thì báo null lên cha và khoá lại nút Xoá', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();
        render(<SignaturePadField onChange={onChange} />);

        // act() để React kịp render lại sau khi state đổi — endStroke là callback ngoài React.
        act(() => mocks.listeners.endStroke?.());

        const clearButton = screen.getByRole('button', { name: 'Xoá chữ ký' });
        expect(clearButton).toBeEnabled();

        await user.click(clearButton);

        expect(mocks.clear).toHaveBeenCalled();
        expect(onChange).toHaveBeenLastCalledWith(null);
        expect(clearButton).toBeDisabled();
    });

    it('cha render lại KHÔNG làm mất nét đang ký', () => {
        // Bẫy kinh điển: đưa onChange thẳng vào deps của useEffect thì mỗi lần cha render
        // là canvas bị dựng lại và xoá sạch chữ ký khách vừa vẽ.
        const { rerender } = render(<SignaturePadField onChange={vi.fn()} />);
        mocks.clear.mockClear();

        rerender(<SignaturePadField onChange={vi.fn()} />);

        expect(mocks.clear).not.toHaveBeenCalled();
    });
});
