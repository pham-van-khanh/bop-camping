import { ReactNode, useCallback, useEffect, useRef, useState } from 'react';

/**
 * Hero ảnh full-bleed dạng slideshow (RULES mục 9).
 * 7 ảnh thật, trượt ngang 0.8s + Ken Burns zoom nhẹ; tự chạy ~4.8s;
 * tạm dừng khi hover / tab ẩn / prefers-reduced-motion.
 * Nội dung (chip, H1, CTA) truyền vào qua children, hiển thị đè lên ảnh.
 */

type Slide = { src: string; title: string };

const DEFAULT_SLIDES: Slide[] = [
    {
        src: '/images/album/beach-night-tent.webp',
        title: 'Lều sáng đèn bên biển đêm',
    },
    {
        src: '/images/album/forest-camp-aerial.webp',
        title: 'Trại giữa rừng thông',
    },
    {
        src: '/images/album/cliff-turquoise.webp',
        title: 'Vách đá bên biển xanh',
    },
    {
        src: '/images/album/cloud-sea-sunrise.webp',
        title: 'Biển mây đón bình minh',
    },
    {
        src: '/images/album/beach-sunset-relax.webp',
        title: 'Hoàng hôn trên bãi biển',
    },
    { src: '/images/album/bay-overview.webp', title: 'Toàn cảnh vịnh' },
    {
        src: '/images/album/tent-interior-night.webp',
        title: 'Đêm ấm trong lều',
    },
];

const AUTOPLAY_MS = 4800;

function prefersReducedMotion() {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

export default function HeroSlideshow({
    children,
    aside,
    slides,
}: {
    children?: ReactNode;
    aside?: ReactNode;
    slides?: Slide[];
}) {
    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false);
    const reduced = useRef(prefersReducedMotion());

    // Dùng banner hero từ admin; nếu chưa có thì rơi về 7 ảnh mặc định.
    const SLIDES = slides && slides.length > 0 ? slides : DEFAULT_SLIDES;
    const total = SLIDES.length;
    const go = useCallback(
        (i: number) => setIndex(((i % total) + total) % total),
        [total],
    );
    const next = useCallback(() => go(index + 1), [go, index]);
    const prev = useCallback(() => go(index - 1), [go, index]);

    // Tự chạy: dừng khi hover, khi tab ẩn, hoặc khi giảm chuyển động.
    useEffect(() => {
        if (paused || reduced.current) return;
        const id = window.setInterval(
            () => setIndex((i) => (i + 1) % total),
            AUTOPLAY_MS,
        );
        return () => window.clearInterval(id);
    }, [paused, total]);

    useEffect(() => {
        const onVis = () => setPaused(document.hidden);
        document.addEventListener('visibilitychange', onVis);
        return () => document.removeEventListener('visibilitychange', onVis);
    }, []);

    return (
        <section
            aria-roledescription="carousel"
            aria-label="Ảnh cắm trại nổi bật"
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            className="relative w-full overflow-hidden"
            style={{
                minHeight: 'clamp(560px,90vh,780px)',
                background: '#0f1a08',
            }}
        >
            {/* Track ảnh */}
            <div className="absolute inset-0 overflow-hidden">
                <div
                    className="flex h-full w-full"
                    style={{
                        transform: `translateX(-${index * 100}%)`,
                        transition: reduced.current
                            ? 'none'
                            : 'transform .8s cubic-bezier(.22,.7,.2,1)',
                    }}
                >
                    {SLIDES.map((s, i) => (
                        <div
                            key={s.src}
                            className="relative h-full w-full flex-none"
                            aria-hidden={i !== index}
                        >
                            <div
                                className={
                                    i === index
                                        ? 'kenburns h-full w-full'
                                        : 'h-full w-full'
                                }
                                style={{
                                    background: `url(${s.src}) center/cover no-repeat`,
                                }}
                            />
                        </div>
                    ))}
                </div>
            </div>

            {/* Lớp phủ tối để chữ trắng đọc được */}
            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(90deg,rgba(8,14,5,.82) 0%,rgba(8,14,5,.52) 40%,rgba(8,14,5,.12) 72%,rgba(8,14,5,.3) 100%)',
                }}
            />
            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(180deg,rgba(8,14,5,.3) 0%,rgba(8,14,5,0) 26%,rgba(8,14,5,0) 58%,rgba(8,14,5,.6) 100%)',
                }}
            />

            {/* Nội dung đè lên */}
            <div
                className="relative mx-auto flex max-w-[1400px] flex-col justify-center px-5"
                style={{
                    minHeight: 'clamp(560px,90vh,780px)',
                    padding:
                        'clamp(44px,9vh,96px) 20px clamp(118px,16vh,150px)',
                }}
            >
                <div className="flex w-full flex-col gap-9 lg:flex-row lg:items-center lg:justify-between">
                    <div className="max-w-[640px]">{children}</div>
                    {aside && (
                        <div className="w-full lg:w-[372px] lg:flex-none">
                            {aside}
                        </div>
                    )}
                </div>
            </div>

            {/* Điều khiển: thumbnail + mũi tên */}
            <div
                className="absolute inset-x-0 z-[3]"
                style={{ bottom: 'clamp(20px,3vh,34px)' }}
            >
                {/*
                    Chừa chỗ bên phải cho cột nút nổi ở góc phải-dưới (ZaloFloatButton + FeedbackWidget),
                    nếu không mũi tên chuyển ảnh bị chúng che và bấm không được.
                    Bề rộng cần chừa = 20px cách mép + bề ngang nút rộng nhất + 10px hở:
                    mobile nút "Góp ý" ẩn chữ nên rộng 50px → 80px; từ sm chữ hiện, rộng 98px → 128px.
                    Chừa ở mọi bề rộng màn hình để mũi tên không bị nhảy vị trí theo viewport.
                */}
                <div className="mx-auto flex max-w-[1400px] items-end justify-between gap-4 px-5 pr-[80px] sm:pr-[128px]">
                    <div className="flex gap-2 overflow-x-auto pb-0.5">
                        {SLIDES.map((s, i) => {
                            const active = i === index;
                            return (
                                <button
                                    key={s.src}
                                    onClick={() => go(i)}
                                    aria-label={s.title}
                                    aria-current={active}
                                    className="h-8 flex-none rounded-[8px] bg-cover bg-center transition-all duration-300"
                                    style={{
                                        width: active ? 54 : 40,
                                        backgroundImage: `url(${s.src})`,
                                        opacity: active ? 1 : 0.55,
                                        outline: active
                                            ? '2px solid #bfe06a'
                                            : '1px solid rgba(255,255,255,.4)',
                                        outlineOffset: active ? 1 : 0,
                                    }}
                                />
                            );
                        })}
                    </div>
                    <div className="flex flex-none gap-2">
                        <button
                            onClick={prev}
                            aria-label="Ảnh trước"
                            className="grid h-[46px] w-[46px] place-items-center rounded-control text-xl text-white transition"
                            style={{
                                border: '1px solid rgba(255,255,255,.45)',
                                background: 'rgba(8,14,5,.4)',
                                backdropFilter: 'blur(6px)',
                            }}
                        >
                            ‹
                        </button>
                        <button
                            onClick={next}
                            aria-label="Ảnh kế"
                            className="grid h-[46px] w-[46px] place-items-center rounded-control text-xl text-white transition"
                            style={{
                                border: '1px solid rgba(255,255,255,.45)',
                                background: 'rgba(8,14,5,.4)',
                                backdropFilter: 'blur(6px)',
                            }}
                        >
                            ›
                        </button>
                    </div>
                </div>
            </div>
        </section>
    );
}
