import { emit, EVENTS } from '@/lib/bus';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

const STAR = '★';

/** Hàng sao tương tác (bản gọn cho form đánh giá tổng thể ở trang chủ). */
function StarPicker({
    value,
    onPick,
}: {
    value: number;
    onPick: (n: number) => void;
}) {
    const [hover, setHover] = useState(0);
    return (
        <div
            className="flex justify-center"
            style={{ gap: 4 }}
            onMouseLeave={() => setHover(0)}
        >
            {[1, 2, 3, 4, 5].map((n) => (
                <button
                    key={n}
                    type="button"
                    onMouseEnter={() => setHover(n)}
                    onClick={() => onPick(n)}
                    aria-label={`${n} sao`}
                    style={{
                        fontSize: 30,
                        lineHeight: 1,
                        color: (hover || value) >= n ? '#C97B36' : '#d8dcc8',
                    }}
                >
                    {STAR}
                </button>
            ))}
        </div>
    );
}

/**
 * CTA + form viết đánh giá tổng thể shop ngay trang chủ (bopcamping-saeb).
 *
 * Trước đây chỉ gửi được qua link token trong mail sau chuyến đi, nên khách muốn khen lúc
 * khác thì không có đường nào. Vẫn chặn theo "đã thuê và trả đồ": khối này hiện ngay trang
 * chủ nên là chỗ dễ bị spam nhất.
 *
 * Ba trạng thái: chưa đăng nhập (mời đăng nhập) · đã đăng nhập nhưng chưa thuê (nhắc) ·
 * đủ điều kiện (mở form).
 */
export default function SystemReviewCta({
    isLoggedIn,
    canReview,
}: {
    isLoggedIn: boolean;
    canReview: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [done, setDone] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        rating: 0,
        content: '',
    });

    const submit = () => {
        post(route('reviews.system.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setDone(true);
                setOpen(false);
                reset();
            },
        });
    };

    if (done) {
        return (
            <div
                className="mx-auto max-w-[560px] rounded-card p-5 text-center"
                style={{ background: '#dcebc4', color: '#3a5a1f' }}
            >
                <div className="text-[15px] font-bold">Cảm ơn bạn! 🎉</div>
                <p className="mt-1 text-[13.5px]">
                    Cảm ơn bạn đã dành thời gian chia sẻ với tụi mình.
                </p>
            </div>
        );
    }

    if (!open) {
        return (
            <div className="text-center">
                <button
                    onClick={() =>
                        isLoggedIn ? setOpen(true) : emit(EVENTS.openLogin)
                    }
                    className="h-[46px] rounded-control border border-cardBorder bg-card px-6 text-[14px] font-bold text-pine transition hover:border-grass"
                >
                    Viết đánh giá của bạn
                </button>
                {isLoggedIn && !canReview && (
                    <p className="mx-auto mt-2.5 max-w-[440px] text-[13px] leading-[1.55] text-moss">
                        Đánh giá dành cho khách đã thuê và trả đồ — hẹn bạn sau
                        chuyến đi đầu tiên nhé!
                    </p>
                )}
            </div>
        );
    }

    // Đã bấm mở form nhưng chưa đủ điều kiện: nói rõ thay vì cho gõ xong mới bị từ chối.
    if (!canReview) {
        return (
            <div
                className="mx-auto max-w-[560px] rounded-card bg-card p-5 text-center text-[14px] leading-[1.6] text-moss"
                style={{ border: '1px dashed #cdd6b6' }}
            >
                Đánh giá dành cho khách đã thuê và trả đồ — hẹn bạn sau chuyến
                đi đầu tiên nhé!
                <div>
                    <button
                        onClick={() => setOpen(false)}
                        className="mt-3 rounded-[10px] border border-cardBorder bg-white px-4 py-1.5 text-[13px] font-semibold text-pine"
                    >
                        Đóng
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div
            className="mx-auto max-w-[560px] rounded-card bg-card p-5"
            style={{ border: '1px solid #E3E8D6' }}
        >
            <h3 className="mb-3 text-center text-[16px] font-bold text-ink">
                Chuyến đi của bạn thế nào?
            </h3>
            <StarPicker
                value={data.rating}
                onPick={(n) => setData('rating', n)}
            />
            <textarea
                value={data.content}
                onChange={(e) => setData('content', e.target.value)}
                placeholder="Đồ thuê, giao nhận, hỗ trợ của tụi mình ra sao?"
                rows={3}
                maxLength={1500}
                className="mt-3 w-full rounded-[11px] border border-cardBorder bg-white px-3.5 py-2.5 text-[14px] text-ink outline-none focus:border-grass"
            />

            {(errors.rating ||
                errors.content ||
                (errors as Record<string, string>).review) && (
                <p className="mt-2 text-[13px] text-red-500">
                    {(errors as Record<string, string>).review ||
                        errors.rating ||
                        errors.content}
                </p>
            )}

            <div className="mt-3 flex items-center justify-between gap-3">
                <button
                    onClick={() => setOpen(false)}
                    className="text-[13px] font-semibold text-moss underline"
                >
                    Để sau
                </button>
                <button
                    onClick={submit}
                    disabled={processing || data.rating === 0}
                    className="h-11 rounded-control px-6 text-[15px] font-bold text-white transition disabled:cursor-not-allowed"
                    style={{
                        background:
                            !processing && data.rating ? '#557A2B' : '#c4cfae',
                    }}
                >
                    {processing ? 'Đang gửi…' : 'Gửi đánh giá'}
                </button>
            </div>
        </div>
    );
}
