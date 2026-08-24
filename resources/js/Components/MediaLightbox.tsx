import type { ReviewMedia } from '@/Components/site/MediaThumb';
import { useEffect } from 'react';

/**
 * Xem ảnh/video đính kèm ở cỡ lớn: nền tối toàn màn hình, mũi tên chuyển, Esc đóng,
 * bộ đếm "1 / n".
 *
 * Dùng chung cho trang duyệt đánh giá của admin (Pages/Admin/Reviews) và modal đánh giá
 * bên trang khách (Components/site/ProductReviews) — bopcamping-ydls. Trước đây chỉ admin
 * có, khách bấm vào ảnh thì không có gì xảy ra; tách ra đây để hai bên không lệch nhau về
 * sau (điều hướng bàn phím, bộ đếm, cách bấm nền để đóng).
 *
 * Ảnh dùng `object-contain`: ảnh khách gửi kèm là bằng chứng, cắt mất mép là mất thông tin.
 */
export default function MediaLightbox({
    media,
    index,
    onClose,
    onNav,
    subject = 'khách gửi kèm đánh giá',
}: {
    media: ReviewMedia[];
    index: number;
    onClose: () => void;
    /** Đổi sang tệp thứ i — state do phía gọi giữ, component này không tự nhớ. */
    onNav: (i: number) => void;
    /**
     * Mô tả cho `alt`, vd "Hà gửi kèm đánh giá". Ảnh là nội dung CHÍNH của lightbox nên
     * alt rỗng là xoá hẳn nội dung (bopcamping-1xja).
     */
    subject?: string;
}) {
    const m = media[index];
    const prev = () => onNav((index - 1 + media.length) % media.length);
    const next = () => onNav((index + 1) % media.length);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowLeft') prev();
            if (e.key === 'ArrowRight') next();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    });

    if (!m) return null;

    return (
        <div
            // Lightbox có thể được render BÊN TRONG một modal khác (modal đánh giá ở
            // trang khách). Nếu để click lọt lên trên, bấm nền để đóng lightbox sẽ đóng
            // luôn modal bọc ngoài (bopcamping-ydls).
            onClick={(e) => {
                e.stopPropagation();
                onClose();
            }}
            role="dialog"
            aria-modal="true"
            aria-label="Xem ảnh đính kèm"
            className="fixed inset-0 z-[95] flex items-center justify-center p-6"
            style={{ background: 'rgba(12,16,8,.82)' }}
        >
            <button
                onClick={onClose}
                aria-label="Đóng"
                className="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/15 text-[20px] text-white"
            >
                ×
            </button>
            {media.length > 1 && (
                <>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            prev();
                        }}
                        aria-label="Ảnh trước"
                        className="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-[22px] text-white"
                    >
                        ‹
                    </button>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            next();
                        }}
                        aria-label="Ảnh sau"
                        className="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-white/15 text-[22px] text-white"
                    >
                        ›
                    </button>
                </>
            )}
            <div
                onClick={(e) => e.stopPropagation()}
                className="max-h-[86vh] max-w-[90vw]"
            >
                {m.type === 'video' ? (
                    <video
                        src={m.url}
                        controls
                        autoPlay
                        className="max-h-[86vh] max-w-[90vw] rounded-[12px]"
                    />
                ) : (
                    <img
                        src={m.url}
                        alt={`Ảnh ${subject} (${index + 1}/${media.length})`}
                        className="max-h-[86vh] max-w-[90vw] rounded-[12px] object-contain"
                    />
                )}
                {media.length > 1 && (
                    <div className="mt-2 text-center font-mono text-[12px] text-white/70">
                        {index + 1} / {media.length}
                    </div>
                )}
            </div>
        </div>
    );
}
