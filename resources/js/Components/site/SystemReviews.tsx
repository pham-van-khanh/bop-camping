import { useEffect, useState } from 'react';

export type SystemReview = {
    id: number;
    reviewer_name: string;
    rating: number;
    content: string | null;
    meta: string;
};

const initial = (name: string) => (name.trim()[0] ?? '?').toUpperCase();

/** Carousel quote đánh giá tổng thể shop (system) ở trang chủ. Auto-next ~5.2s. */
export default function SystemReviews({
    reviews,
}: {
    reviews: SystemReview[];
}) {
    const [idx, setIdx] = useState(0);
    const [paused, setPaused] = useState(false);
    const cur = reviews[idx];

    useEffect(() => {
        if (reviews.length <= 1 || paused) return;
        const t = setInterval(
            () => setIdx((i) => (i + 1) % reviews.length),
            5200,
        );
        return () => clearInterval(t);
    }, [reviews.length, paused]);

    if (!cur) return null;

    return (
        <div
            className="relative mx-auto max-w-[720px] rounded-card bg-card p-7 text-center"
            style={{ border: '1px solid #E3E8D6' }}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
        >
            <span className="pointer-events-none absolute left-5 top-2 font-mono text-[56px] leading-none text-[#eef2e3]">
                “
            </span>
            <div className="relative">
                <div
                    className="font-mono text-[15px]"
                    style={{ color: '#C97B36' }}
                >
                    {'★'.repeat(cur.rating)}
                    <span className="text-[#d8dcc8]">
                        {'★'.repeat(5 - cur.rating)}
                    </span>
                </div>
                {cur.content && (
                    <p className="mx-auto mt-3 max-w-[600px] text-[17px] leading-[1.6] text-ink">
                        {cur.content}
                    </p>
                )}
                <div className="mt-4 flex items-center justify-center gap-2.5">
                    <span
                        className="grid h-10 w-10 place-items-center rounded-full font-bold text-white"
                        style={{ background: '#557A2B' }}
                    >
                        {initial(cur.reviewer_name)}
                    </span>
                    <div className="text-left">
                        <div className="text-[14px] font-bold text-ink">
                            {cur.reviewer_name}
                        </div>
                        <div className="font-mono text-[12px] text-moss">
                            {cur.meta}
                        </div>
                    </div>
                </div>
            </div>

            {reviews.length > 1 && (
                <div className="mt-5 flex items-center justify-center gap-1.5">
                    {reviews.map((_, i) => (
                        <button
                            key={i}
                            onClick={() => setIdx(i)}
                            aria-label={`Đánh giá ${i + 1}`}
                            className="h-2 rounded-full transition-all"
                            style={{
                                width: i === idx ? 20 : 8,
                                background: i === idx ? '#557A2B' : '#cdd6b6',
                            }}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
