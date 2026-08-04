import SelectInput from '@/Components/admin/SelectInput';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';

type Media = { type: 'image' | 'video'; url: string };
type ReviewRow = {
    id: number;
    reviewer_name: string;
    rating: number;
    content: string | null;
    category: 'system' | 'product';
    status: 'pending' | 'approved' | 'rejected';
    admin_note: string | null;
    product: { id: number; name: string } | null;
    created_at: string;
    media: Media[];
};
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};
type Props = PageProps<{
    reviews: Paginator<ReviewRow>;
    filters: { status: string; category: string };
    counts: { pending: number; approved: number; rejected: number };
}>;

const STATUS_TABS = [
    { key: 'pending', label: 'Chờ duyệt' },
    { key: 'approved', label: 'Đã duyệt' },
    { key: 'rejected', label: 'Đã từ chối' },
];
const STATUS_PILL: Record<string, { c: string; b: string; t: string }> = {
    pending: { c: '#9a7a2a', b: '#fbf2d8', t: 'Chờ duyệt' },
    approved: { c: '#3a5a1f', b: '#dcebc4', t: 'Đã duyệt' },
    rejected: { c: '#b3493a', b: '#f6ddd6', t: 'Đã từ chối' },
};
const Stars = ({ n }: { n: number }) => (
    <span className="font-mono text-[13px]" style={{ color: '#C97B36' }}>
        {'★'.repeat(n)}
        <span className="text-[#d8dcc8]">{'★'.repeat(5 - n)}</span>
    </span>
);

export default function AdminReviews() {
    const { reviews, filters, counts, flash } = usePage<Props>().props;
    const [toast, setToast] = useState('');
    const [rejectId, setRejectId] = useState<number | null>(null);
    const [note, setNote] = useState('');
    const [lightbox, setLightbox] = useState<{
        media: Media[];
        index: number;
    } | null>(null);

    useEffect(() => {
        if (flash.success) {
            setToast(flash.success);
            const t = setTimeout(() => setToast(''), 2500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const go = (patch: Record<string, string>) =>
        router.get(
            route('admin.reviews'),
            { ...filters, ...patch },
            { preserveScroll: true, replace: true },
        );

    const decide = (
        id: number,
        status: 'approved' | 'rejected',
        admin_note = '',
    ) =>
        router.patch(
            route('admin.reviews.update', id),
            { status, admin_note },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRejectId(null);
                    setNote('');
                },
            },
        );

    return (
        <>
            <Head title="Admin · Đánh giá" />
            <div className="mx-auto max-w-[920px]">
                <h1 className="mb-1 text-[22px] font-extrabold text-ink">
                    Duyệt đánh giá
                </h1>
                <p className="mb-4 text-[14px] text-moss">
                    Chỉ đánh giá đã duyệt mới hiển thị công khai.
                </p>

                {toast && (
                    <div className="mb-4 rounded-[10px] border border-grass/40 bg-[#eef2e3] px-4 py-2.5 text-[14px] font-semibold text-pine">
                        {toast}
                    </div>
                )}

                {/* Bộ lọc */}
                <div className="mb-4 flex flex-wrap items-center gap-3">
                    <div className="flex gap-1 rounded-[12px] border border-cardBorder bg-white p-1">
                        {STATUS_TABS.map((t) => (
                            <button
                                key={t.key}
                                onClick={() => go({ status: t.key })}
                                className={`rounded-[9px] px-3.5 py-2 text-[13px] font-semibold transition ${filters.status === t.key ? 'bg-grass text-white' : 'text-pine hover:bg-[#f1f4ea]'}`}
                            >
                                {t.label}
                                {t.key === 'pending' && counts.pending > 0
                                    ? ` (${counts.pending})`
                                    : ''}
                            </button>
                        ))}
                    </div>
                    <div className="w-[180px]">
                        <SelectInput
                            value={filters.category}
                            onChange={(v) => go({ category: v })}
                            ariaLabel="Loại đánh giá"
                            options={[
                                { value: 'all', label: 'Tất cả loại' },
                                { value: 'product', label: 'Sản phẩm' },
                                { value: 'system', label: 'Hệ thống' },
                            ]}
                        />
                    </div>
                </div>

                {reviews.data.length === 0 ? (
                    <div className="rounded-card border border-cardBorder bg-card py-12 text-center text-[14px] text-moss">
                        Không có đánh giá nào.
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {reviews.data.map((r) => (
                            <div
                                key={r.id}
                                className="rounded-card border border-cardBorder bg-card p-4"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-bold text-ink">
                                                {r.reviewer_name}
                                            </span>
                                            <Stars n={r.rating} />
                                            <span
                                                className="rounded-pill px-2 py-0.5 text-[11px] font-semibold"
                                                style={{
                                                    color: STATUS_PILL[r.status]
                                                        .c,
                                                    background:
                                                        STATUS_PILL[r.status].b,
                                                }}
                                            >
                                                {STATUS_PILL[r.status].t}
                                            </span>
                                            <span className="rounded-pill bg-[#eef2e3] px-2 py-0.5 font-mono text-[11px] text-moss">
                                                {r.category === 'system'
                                                    ? 'Hệ thống'
                                                    : 'Sản phẩm'}
                                            </span>
                                        </div>
                                        <div className="mt-0.5 font-mono text-[12px] text-moss">
                                            {r.product
                                                ? r.product.name + ' · '
                                                : ''}
                                            {r.created_at}
                                        </div>
                                    </div>
                                </div>

                                {r.content && (
                                    <p className="mt-2 text-[14px] leading-[1.55] text-ink">
                                        {r.content}
                                    </p>
                                )}

                                {r.media.length > 0 && (
                                    <div className="mt-2.5 flex flex-wrap gap-2">
                                        {r.media.map((m, i) => (
                                            <button
                                                key={i}
                                                onClick={() =>
                                                    setLightbox({
                                                        media: r.media,
                                                        index: i,
                                                    })
                                                }
                                                className="relative block h-16 w-16 overflow-hidden rounded-[10px] border border-cardBorder"
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
                                        ))}
                                    </div>
                                )}

                                {r.status === 'rejected' && r.admin_note && (
                                    <p className="mt-2 text-[12.5px] text-[#b3493a]">
                                        Lý do từ chối: {r.admin_note}
                                    </p>
                                )}

                                {/* Hành động */}
                                {rejectId === r.id ? (
                                    <div className="mt-3 flex flex-wrap items-center gap-2">
                                        <input
                                            value={note}
                                            onChange={(e) =>
                                                setNote(e.target.value)
                                            }
                                            placeholder="Lý do từ chối (tuỳ chọn)"
                                            className="h-10 flex-1 rounded-[10px] border border-cardBorder bg-white px-3 text-[13px] outline-none focus:border-grass"
                                        />
                                        <button
                                            onClick={() =>
                                                decide(r.id, 'rejected', note)
                                            }
                                            className="h-10 rounded-control bg-[#b3493a] px-4 text-[13px] font-bold text-white"
                                        >
                                            Xác nhận từ chối
                                        </button>
                                        <button
                                            onClick={() => {
                                                setRejectId(null);
                                                setNote('');
                                            }}
                                            className="h-10 rounded-control border border-cardBorder px-4 text-[13px] font-semibold text-pine"
                                        >
                                            Huỷ
                                        </button>
                                    </div>
                                ) : (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {r.status !== 'approved' && (
                                            <button
                                                onClick={() =>
                                                    decide(r.id, 'approved')
                                                }
                                                className="h-9 rounded-control bg-grass px-4 text-[13px] font-bold text-white transition hover:bg-pine"
                                            >
                                                ✓ Duyệt
                                            </button>
                                        )}
                                        {r.status !== 'rejected' && (
                                            <button
                                                onClick={() => {
                                                    setRejectId(r.id);
                                                    setNote('');
                                                }}
                                                className="h-9 rounded-control border border-[#f6ddd6] px-4 text-[13px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                            >
                                                Từ chối
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                )}

                {reviews.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-[12.5px] text-moss">
                        <span className="font-mono">
                            {reviews.from}–{reviews.to} / {reviews.total}
                        </span>
                        <div className="flex gap-2">
                            <button
                                disabled={reviews.current_page <= 1}
                                onClick={() =>
                                    go({
                                        page: String(reviews.current_page - 1),
                                    })
                                }
                                className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine disabled:opacity-40"
                            >
                                Trước
                            </button>
                            <button
                                disabled={
                                    reviews.current_page >= reviews.last_page
                                }
                                onClick={() =>
                                    go({
                                        page: String(reviews.current_page + 1),
                                    })
                                }
                                className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine disabled:opacity-40"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {lightbox && (
                <Lightbox
                    state={lightbox}
                    onClose={() => setLightbox(null)}
                    onNav={(i) => setLightbox({ ...lightbox, index: i })}
                />
            )}
        </>
    );
}

function Lightbox({
    state,
    onClose,
    onNav,
}: {
    state: { media: Media[]; index: number };
    onClose: () => void;
    onNav: (i: number) => void;
}) {
    const { media, index } = state;
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
    }, [index]);

    return (
        <div
            onClick={onClose}
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
                        className="absolute left-4 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-[22px] text-white"
                    >
                        ‹
                    </button>
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            next();
                        }}
                        className="absolute right-4 grid h-11 w-11 place-items-center rounded-full bg-white/15 text-[22px] text-white"
                        style={{ top: '50%' }}
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
                        alt=""
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

AdminReviews.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
