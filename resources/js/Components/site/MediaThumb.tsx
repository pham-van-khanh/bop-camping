// Ô thumbnail ảnh/video dùng chung cho các form + list đánh giá (DRY —
// ProductReviews + ReviewInvite). Video có lớp phủ nút play.
export type ReviewMedia = { type: 'image' | 'video'; url: string };

export default function MediaThumb({
    m,
    size,
    onClick,
}: {
    m: ReviewMedia;
    size: number;
    onClick?: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
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
