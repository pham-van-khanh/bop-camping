import { useEffect, useMemo, useRef, useState } from 'react';
import { useForm } from '@inertiajs/react';

export type ReviewMedia = { type: 'image' | 'video'; url: string };
export type ReviewItem = {
    id: number;
    reviewer_name: string;
    rating: number;
    content: string | null;
    meta: string;
    media: ReviewMedia[];
};
export type ReviewSummary = { count: number; avg: number };

type Props = {
    productId: number;
    productName: string;
    reviews: ReviewItem[];
    summary: ReviewSummary;
    canReview: boolean;
    isLoggedIn: boolean;
};

const STAR = '★';
const initial = (name: string) => (name.trim()[0] ?? '?').toUpperCase();

/** Hàng sao (hiển thị hoặc tương tác). */
function Stars({ value, onPick, size = 16 }: { value: number; onPick?: (n: number) => void; size?: number }) {
    const [hover, setHover] = useState(0);
    return (
        <div className="flex" style={{ gap: 2 }} onMouseLeave={() => setHover(0)}>
            {[1, 2, 3, 4, 5].map((n) => {
                const on = (hover || value) >= n;
                return (
                    <span
                        key={n}
                        onMouseEnter={onPick ? () => setHover(n) : undefined}
                        onClick={onPick ? () => onPick(n) : undefined}
                        role={onPick ? 'button' : undefined}
                        aria-label={onPick ? `${n} sao` : undefined}
                        style={{ fontSize: size, lineHeight: 1, cursor: onPick ? 'pointer' : 'default', color: on ? '#C97B36' : '#d8dcc8' }}
                    >
                        {STAR}
                    </span>
                );
            })}
        </div>
    );
}

function MediaThumb({ m, size, onClick }: { m: ReviewMedia; size: number; onClick?: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="relative overflow-hidden rounded-[9px] border border-cardBorder"
            style={{ width: size, height: size }}
        >
            {m.type === 'video' ? (
                <>
                    <video src={m.url} className="h-full w-full object-cover" muted />
                    <span className="absolute inset-0 grid place-items-center bg-black/25 text-white">▶</span>
                </>
            ) : (
                <img src={m.url} alt="" className="h-full w-full object-cover" />
            )}
        </button>
    );
}

export default function ProductReviews({ productId, productName, reviews, summary, isLoggedIn }: Props) {
    const [idx, setIdx] = useState(0);
    const [modal, setModal] = useState<ReviewItem | null>(null);
    const [paused, setPaused] = useState(false);
    const cur = reviews[idx];

    // Auto-next 5.2s, tạm dừng khi hover/mở modal.
    useEffect(() => {
        if (reviews.length <= 1 || paused || modal) return;
        const t = setInterval(() => setIdx((i) => (i + 1) % reviews.length), 5200);
        return () => clearInterval(t);
    }, [reviews.length, paused, modal]);

    return (
        <div className="mt-4 flex flex-col gap-4">
            {/* ===== Carousel hiển thị ===== */}
            <div
                className="rounded-card bg-card p-5"
                style={{ border: '1px solid #E3E8D6' }}
                onMouseEnter={() => setPaused(true)}
                onMouseLeave={() => setPaused(false)}
            >
                <div className="mb-3 flex items-center justify-between">
                    <div className="font-mono text-[12px] tracking-[0.1em]">
                        <span className="text-campfire">KHÁCH ĐÃ THUÊ</span>
                        <span className="text-moss"> · {summary.count} đánh giá</span>
                    </div>
                    {summary.count > 0 && (
                        <div className="font-mono text-[15px] font-bold text-grass">★ {summary.avg.toFixed(1)}</div>
                    )}
                </div>

                {!cur ? (
                    <p className="py-6 text-center text-[14px] text-moss">Chưa có đánh giá nào. Hãy là người đầu tiên chia sẻ trải nghiệm!</p>
                ) : (
                    <>
                        <div className="relative">
                            <span className="absolute -top-2 left-0 font-mono text-[44px] leading-none text-[#eef2e3]">“</span>
                            <div className="pl-2 pt-5">
                                <Stars value={cur.rating} />
                                {cur.content && <p className="mt-2 text-[15px] leading-[1.6] text-ink">{cur.content}</p>}
                                {cur.media.length > 0 && (
                                    <div className="mt-3 flex gap-2">
                                        {cur.media.slice(0, 3).map((m, i) => (
                                            <MediaThumb key={i} m={m} size={46} onClick={() => setModal(cur)} />
                                        ))}
                                    </div>
                                )}
                                <div className="mt-3.5 flex items-center justify-between">
                                    <div className="flex items-center gap-2.5">
                                        <span className="grid h-10 w-10 place-items-center rounded-full font-bold text-white" style={{ background: '#C97B36' }}>{initial(cur.reviewer_name)}</span>
                                        <div>
                                            <div className="text-[14px] font-bold text-ink">{cur.reviewer_name}</div>
                                            <div className="font-mono text-[12px] text-moss">{cur.meta}</div>
                                        </div>
                                    </div>
                                    <button onClick={() => setModal(cur)} className="rounded-[10px] border border-cardBorder px-3.5 py-1.5 text-[13px] font-semibold text-pine transition hover:border-grass">Xem</button>
                                </div>
                            </div>
                        </div>

                        {reviews.length > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <div className="flex items-center gap-1.5">
                                    {reviews.map((_, i) => (
                                        <button
                                            key={i}
                                            onClick={() => setIdx(i)}
                                            aria-label={`Đánh giá ${i + 1}`}
                                            className="h-2 rounded-full transition-all"
                                            style={{ width: i === idx ? 20 : 8, background: i === idx ? '#557A2B' : '#cdd6b6' }}
                                        />
                                    ))}
                                </div>
                                <div className="flex gap-2">
                                    <button onClick={() => setIdx((i) => (i - 1 + reviews.length) % reviews.length)} className="grid h-8 w-8 place-items-center rounded-[10px] border border-cardBorder text-pine transition hover:border-grass">‹</button>
                                    <button onClick={() => setIdx((i) => (i + 1) % reviews.length)} className="grid h-8 w-8 place-items-center rounded-[10px] border border-cardBorder text-pine transition hover:border-grass">›</button>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            {/* ===== Form viết (ai cũng gửi được, đánh giá vào pending chờ duyệt) ===== */}
            <ReviewForm productId={productId} isLoggedIn={isLoggedIn} />

            {modal && <ReviewModalView review={modal} productName={productName} onClose={() => setModal(null)} />}
        </div>
    );
}

/** Form viết đánh giá: (tên nếu khách vãng lai) + sao + nội dung + upload ≤4 ảnh/video. */
function ReviewForm({ productId, isLoggedIn }: { productId: number; isLoggedIn: boolean }) {
    const fileRef = useRef<HTMLInputElement>(null);
    const [done, setDone] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<{ reviewer_name: string; rating: number; content: string; media: File[] }>({
        reviewer_name: '',
        rating: 0,
        content: '',
        media: [],
    });

    const previews = useMemo(
        () => data.media.map((f) => ({ url: URL.createObjectURL(f), type: f.type.startsWith('video/') ? 'video' as const : 'image' as const })),
        [data.media],
    );
    useEffect(() => () => previews.forEach((p) => URL.revokeObjectURL(p.url)), [previews]);

    const addFiles = (files: FileList | null) => {
        if (!files) return;
        setData('media', [...data.media, ...Array.from(files)].slice(0, 4));
        if (fileRef.current) fileRef.current.value = '';
    };
    const removeAt = (i: number) => setData('media', data.media.filter((_, j) => j !== i));

    const submit = () => {
        post(route('reviews.store', productId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setDone(true); reset(); },
        });
    };

    if (done) {
        return (
            <div className="rounded-card p-5" style={{ background: '#dcebc4', color: '#3a5a1f' }}>
                <div className="text-[15px] font-bold">Cảm ơn bạn! 🎉</div>
                <p className="mt-1 text-[13.5px]">Đánh giá sẽ hiển thị sau khi được tụi mình duyệt.</p>
                <button onClick={() => setDone(false)} className="mt-3 rounded-[10px] border border-grass/40 bg-white px-3.5 py-1.5 text-[13px] font-semibold text-pine">Viết tiếp</button>
            </div>
        );
    }

    return (
        <div className="rounded-card bg-card p-5" style={{ border: '1px solid #E3E8D6' }}>
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-[16px] font-bold text-ink">Chia sẻ trải nghiệm của bạn</h3>
                <Stars value={data.rating} onPick={(n) => setData('rating', n)} size={24} />
            </div>

            <div className="flex flex-col gap-3">
                {!isLoggedIn && (
                    <input
                        value={data.reviewer_name}
                        onChange={(e) => setData('reviewer_name', e.target.value)}
                        placeholder="Tên của bạn"
                        maxLength={60}
                        className="w-full rounded-[11px] border border-cardBorder bg-white px-3.5 py-2.5 text-[14px] text-ink outline-none focus:border-grass"
                    />
                )}
                <textarea
                    value={data.content}
                    onChange={(e) => setData('content', e.target.value)}
                    placeholder="Đồ thuê thế nào? Dựng có dễ không? Giao nhận ra sao?"
                    rows={3}
                    className="w-full rounded-[11px] border border-cardBorder bg-white px-3.5 py-2.5 text-[14px] text-ink outline-none focus:border-grass"
                />

                {/* Upload ảnh/video */}
                <div className="flex flex-wrap items-center gap-2.5">
                    {previews.map((p, i) => (
                        <div key={i} className="relative" style={{ width: 62, height: 62 }}>
                            <MediaThumb m={p} size={62} />
                            <button onClick={() => removeAt(i)} aria-label="Xoá" className="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-pine text-[12px] text-white">×</button>
                        </div>
                    ))}
                    {data.media.length < 4 && (
                        <button
                            type="button"
                            onClick={() => fileRef.current?.click()}
                            className="grid place-items-center rounded-[11px] text-grass"
                            style={{ width: 62, height: 62, border: '1.5px dashed #b9c79a' }}
                        >
                            <span className="text-[18px] leading-none">＋</span>
                            <span className="font-mono text-[10px]">Thêm</span>
                        </button>
                    )}
                    <span className="font-mono text-[12px] text-moss">{data.media.length}/4 · ảnh hoặc video</span>
                    <input ref={fileRef} type="file" accept="image/*,video/*" multiple hidden onChange={(e) => addFiles(e.target.files)} />
                </div>

                {(errors.reviewer_name || errors.rating || errors.content || errors['media.0' as keyof typeof errors] || (errors as Record<string, string>).review) && (
                    <p className="text-[13px] text-red-500">{errors.reviewer_name || errors.rating || (errors as Record<string, string>).review || errors.content || 'Có tệp không hợp lệ.'}</p>
                )}

                <div className="flex items-center justify-between">
                    <span className="font-mono text-[12px] text-moss">{data.rating ? `${data.rating} sao` : 'Chạm để chấm sao'}</span>
                    <button
                        onClick={submit}
                        disabled={processing || data.rating === 0 || (!isLoggedIn && data.reviewer_name.trim() === '')}
                        className="h-11 rounded-control px-6 text-[15px] font-bold text-white transition disabled:cursor-not-allowed"
                        style={{ background: !processing && data.rating && (isLoggedIn || data.reviewer_name.trim()) ? '#557A2B' : '#c4cfae' }}
                    >
                        {processing ? 'Đang gửi…' : 'Gửi đánh giá'}
                    </button>
                </div>
            </div>
        </div>
    );
}

/** Modal preview đánh giá (lưới ảnh/video đầy đủ). */
function ReviewModalView({ review, productName, onClose }: { review: ReviewItem; productName: string; onClose: () => void }) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    return (
        <div onClick={onClose} className="fixed inset-0 z-[92] flex items-center justify-center p-5" style={{ background: 'rgba(24,35,15,.55)', backdropFilter: 'blur(3px)' }}>
            <div onClick={(e) => e.stopPropagation()} className="w-full max-w-[460px] overflow-hidden rounded-[20px] bg-card">
                <div className="relative h-[120px]" style={{ background: 'linear-gradient(120deg,#2C3D22,#557A2B)' }}>
                    <div className="absolute inset-0 grid place-items-center font-mono text-[13px] tracking-[0.1em] text-white/80">{productName}</div>
                    <button onClick={onClose} aria-label="Đóng" className="absolute right-3 top-3 grid h-8 w-8 place-items-center rounded-full bg-black/30 text-white">×</button>
                </div>
                <div className="p-[22px]">
                    <div className="flex items-center gap-2.5">
                        <span className="grid h-12 w-12 place-items-center rounded-full text-[18px] font-bold text-white" style={{ background: '#C97B36' }}>{initial(review.reviewer_name)}</span>
                        <div>
                            <div className="text-[15px] font-bold text-ink">{review.reviewer_name}</div>
                            <div className="font-mono text-[12px] text-moss">{review.meta}</div>
                        </div>
                        <div className="ml-auto"><Stars value={review.rating} /></div>
                    </div>
                    {review.content && <p className="mt-3.5 text-[15.5px] leading-[1.6] text-ink">{review.content}</p>}
                    {review.media.length > 0 && (
                        <div className="mt-4 grid gap-2" style={{ gridTemplateColumns: `repeat(${Math.min(review.media.length, 2)},1fr)` }}>
                            {review.media.map((m, i) => (
                                <div key={i} className="overflow-hidden rounded-[10px]" style={{ aspectRatio: '1/1' }}>
                                    {m.type === 'video'
                                        ? <video src={m.url} controls autoPlay muted loop playsInline className="h-full w-full object-cover" />
                                        : <img src={m.url} alt="" className="h-full w-full object-cover" />}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
