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
        strokes: [] as unknown[],
        clear: vi.fn(),
        off: vi.fn(),
        toDataURL: vi.fn(() => 'data:image/png;base64,FAKE'),
        fromData: vi.fn(),
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
        fromData = mocks.fromData;
        addEventListener = mocks.addEventListener;
        toData = () => mocks.strokes;
    },
}));

/** jsdom cho offsetWidth/Height = 0; ép giá trị thật để nhánh resize chạy được. */
function setCanvasSize(width: number, height: number): void {
    const canvas = document.querySelector('canvas') as HTMLCanvasElement;
    Object.defineProperty(canvas, 'offsetWidth', {
        value: width,
        configurable: true,
    });
    Object.defineProperty(canvas, 'offsetHeight', {
        value: height,
        configurable: true,
    });
}

describe('SignaturePadField', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        mocks.strokes.length = 0;
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

    it('resize mà kích thước KHÔNG đổi thì không đụng vào chữ ký', () => {
        // Trên điện thoại, cuộn trang làm thanh địa chỉ thu lại là trình duyệt bắn 'resize'.
        // Nghe vô điều kiện thì khách đang ký sẽ mất nét giữa chừng.
        const onChange = vi.fn();
        render(<SignaturePadField onChange={onChange} />);

        // Đưa canvas về kích thước THẬT trước đã, không thì test chỉ chạm nhánh "ẩn, bỏ qua"
        // và xanh vì lý do sai.
        setCanvasSize(500, 200);
        act(() => {
            window.dispatchEvent(new Event('resize'));
        });

        act(() => mocks.listeners.endStroke?.());
        onChange.mockClear();
        mocks.clear.mockClear();

        // Lần resize thứ hai, KÍCH THƯỚC Y HỆT — phải là no-op hoàn toàn.
        act(() => {
            window.dispatchEvent(new Event('resize'));
        });

        expect(mocks.clear).not.toHaveBeenCalled();
        expect(onChange).not.toHaveBeenCalledWith(null);
        expect(
            screen.getByRole('button', { name: 'Xoá chữ ký' }),
        ).toBeEnabled();
    });

    it('resize làm ĐỔI kích thước thì vẽ lại nét cũ, không xoá trắng', () => {
        const onChange = vi.fn();
        render(<SignaturePadField onChange={onChange} />);
        act(() => mocks.listeners.endStroke?.());
        onChange.mockClear();

        mocks.strokes.push({ points: [{ x: 1, y: 1 }] });
        setCanvasSize(500, 200);
        act(() => {
            window.dispatchEvent(new Event('resize'));
        });

        // Cứu bằng toạ độ nét (fromData), không phải ảnh — nét vẫn sắc ở kích thước mới.
        expect(mocks.fromData).toHaveBeenCalledWith(mocks.strokes);
        expect(onChange).not.toHaveBeenCalledWith(null);
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
