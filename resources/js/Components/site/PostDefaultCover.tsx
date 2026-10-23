/**
 * Ảnh bìa mặc định cho bài Cẩm nang chưa có ảnh (bopcamping-s762): ảnh chung + tiêu đề
 * bài in đè lên.
 *
 * Bản .jpg cùng thư mục là og:image dự phòng (Shop\PostController::FALLBACK_IMAGE) —
 * đổi ảnh thì đổi cả hai chỗ.
 */
const SRC = '/images/cam-nang/cover-default-1600.webp';
const SRCSET =
    '/images/cam-nang/cover-default-800.webp 800w, /images/cam-nang/cover-default-1600.webp 1600w';

const VARIANTS = {
    // Thẻ bài ở /cam-nang + "Bài liên quan". Ảnh gốc rất ngang (2.5:1), khung thẻ 16:10
    // nên phải cắt bớt hai bên — lệch trái để giữ lều + bàn ghế, bỏ bớt mặt hồ.
    card: {
        sizes: '(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw',
        img: 'transition duration-500 group-hover:scale-[1.03]',
        pad: 'p-4 sm:p-5',
        title: 'line-clamp-3 text-[19px]',
    },
    // Đầu trang chi tiết bài.
    hero: {
        sizes: '(min-width: 1080px) 1000px, 100vw',
        img: '',
        pad: 'p-5 sm:p-8',
        title: 'line-clamp-3 text-[length:clamp(22px,3.4vw,36px)]',
    },
} as const;

export default function PostDefaultCover({
    title,
    variant,
}: {
    title: string;
    variant: keyof typeof VARIANTS;
}) {
    const v = VARIANTS[variant];

    return (
        <>
            {/* alt rỗng + chữ aria-hidden: tiêu đề đã có ở heading ngay đó, đọc lại là thừa. */}
            <img
                src={SRC}
                srcSet={SRCSET}
                sizes={v.sizes}
                alt=""
                {...(variant === 'hero'
                    ? { fetchPriority: 'high' as const }
                    : { loading: 'lazy' as const, decoding: 'async' as const })}
                className={`absolute inset-0 h-full w-full object-cover object-[28%_50%] ${v.img}`}
            />
            <div
                aria-hidden="true"
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(to top, rgba(24,35,15,.85) 0%, rgba(24,35,15,.5) 45%, rgba(24,35,15,0) 78%)',
                }}
            />
            <div
                aria-hidden="true"
                data-testid="post-default-cover-title"
                className={`absolute inset-x-0 bottom-0 ${v.pad}`}
            >
                <div className="mb-1.5 font-mono text-[11px] font-bold tracking-[0.18em] text-[#f3e6cf]">
                    CẨM NANG
                </div>
                <div
                    className={`font-extrabold leading-[1.22] tracking-tight text-white [text-shadow:0_1px_3px_rgba(0,0,0,.4)] ${v.title}`}
                >
                    {title}
                </div>
            </div>
        </>
    );
}
