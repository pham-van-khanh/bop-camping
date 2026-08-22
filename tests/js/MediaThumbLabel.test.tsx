import MediaThumb from '@/Components/site/MediaThumb';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

/**
 * bopcamping-1xja — ô ảnh/video đính kèm phải có TÊN KHẢ TRUY CẬP.
 *
 * Ảnh (hoặc video) là nội dung DUY NHẤT của nút này. Không có nhãn thì trình đọc màn
 * hình chỉ đọc "nút" — người dùng không biết bấm vào sẽ ra gì.
 *
 * Nhãn đặt lên chính NÚT chứ không lên thẻ <img>: nhánh video không có <img> nào, gắn
 * vào img sẽ bỏ sót đúng một nửa số trường hợp. Đây là chỗ dễ sai nhất nên test kiểm
 * CẢ HAI nhánh.
 */
describe('MediaThumb — tên khả truy cập', () => {
    it('ảnh: nút mang nhãn truyền vào, và img giữ alt rỗng để không đọc lặp', () => {
        render(
            <MediaThumb
                m={{ type: 'image', url: '/a.jpg' }}
                size={48}
                label="Ảnh 1 Nam gửi kèm đánh giá"
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Ảnh 1 Nam gửi kèm đánh giá' }),
        ).toBeInTheDocument();
        // alt rỗng là ĐÚNG ở đây — nhãn đã nằm trên nút.
        expect(document.querySelector('img')?.getAttribute('alt')).toBe('');
    });

    it('video: nút vẫn có nhãn dù nhánh này không hề có thẻ img', () => {
        render(
            <MediaThumb
                m={{ type: 'video', url: '/a.mp4' }}
                size={48}
                label="Video 2 Nam gửi kèm đánh giá"
            />,
        );

        expect(
            screen.getByRole('button', {
                name: 'Video 2 Nam gửi kèm đánh giá',
            }),
        ).toBeInTheDocument();
        expect(document.querySelector('img')).toBeNull();
    });

    it('không truyền nhãn thì vẫn có tên mặc định, không bao giờ để nút trống tên', () => {
        const { unmount } = render(
            <MediaThumb m={{ type: 'image', url: '/a.jpg' }} size={48} />,
        );
        expect(
            screen.getByRole('button', { name: 'Ảnh đính kèm' }),
        ).toBeInTheDocument();
        unmount();

        render(<MediaThumb m={{ type: 'video', url: '/a.mp4' }} size={48} />);
        expect(
            screen.getByRole('button', { name: 'Video đính kèm' }),
        ).toBeInTheDocument();
    });
});
