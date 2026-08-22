// Ô thumbnail ảnh/video dùng chung cho các form + list đánh giá (DRY —
// ProductReviews + ReviewInvite). Video có lớp phủ nút play.
export type ReviewMedia = { type: 'image' | 'video'; url: string };

export default function MediaThumb({
    m,
    size,
    onClick,
    label,
}: {
    m: ReviewMedia;
    size: number;
    onClick?: () => void;
    /**
     * Tên khả truy cập của ô (bopcamping-1xja). Ảnh/video là nội dung DUY NHẤT của nút
     * này, nên nếu không có nhãn thì trình đọc màn hình chỉ đọc "nút" — không biết là
     * nút gì. Đặt nhãn lên chính NÚT chứ không lên thẻ img: nhánh video không có img
     * nên gắn vào img sẽ bỏ sót đúng một nửa trường hợp.
     */
    label?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={
                label ??
                (m.type === 'video' ? 'Video đính kèm' : 'Ảnh đính kèm')
            }
            className="relative overflow-hidden rounded-[9px] border border-cardBorder"
            style={{ width: size, height: size }}
        >
            {m.type === 'video' ? (
                <>
                    <video
                        src={m.url}
                        className="h-full w-full object-cover"
                        muted
                    />
                    <span className="absolute inset-0 grid place-items-center bg-black/25 text-white">
                        ▶
                    </span>
                </>
            ) : (
                <img
                    src={m.url}
                    alt=""
                    className="h-full w-full object-cover"
                />
            )}
        </button>
    );
}
