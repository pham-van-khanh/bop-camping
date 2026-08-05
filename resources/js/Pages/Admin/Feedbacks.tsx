import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';

type FeedbackRow = {
    id: number;
    name: string;
    phone: string | null;
    email: string | null;
    content: string;
    status: 'new' | 'replied';
    reply_content: string | null;
    replied_at: string | null;
    created_at: string;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

const FILTERS = [
    { value: '', label: 'Tất cả' },
    { value: 'new', label: 'Chưa phản hồi' },
    { value: 'replied', label: 'Đã phản hồi' },
] as const;

/** Admin đọc + phản hồi góp ý của khách (Epic 2). */
export default function AdminFeedbacks({
    feedbacks,
    filters,
}: {
    feedbacks: Paginator<FeedbackRow>;
    filters: { status: string };
}) {
    const { flash } = usePage<PageProps>().props;
    const [toastMsg, setToastMsg] = useState('');
    const [openId, setOpenId] = useState<number | null>(null);

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const applyFilter = (status: string) =>
        router.get(route('admin.feedbacks'), status ? { status } : {}, {
            preserveState: true,
            replace: true,
        });

    const goPage = (page: number) =>
        router.get(
            route('admin.feedbacks'),
            { page, status: filters.status || undefined },
            { preserveScroll: true },
        );

    return (
        <div className="p-6">
            <Head title="Góp ý của khách" />
            <div className="mb-5">
                <h1 className="text-[22px] font-extrabold tracking-tight text-pine">
                    Góp ý của khách
                </h1>
                <p className="mt-1 text-[13px] text-moss">
                    Góp ý gửi từ widget trên website. Phản hồi qua email sẽ gửi
                    bằng địa chỉ cấu hình trong hệ thống.
                </p>
            </div>

            {/* Lọc trạng thái */}
            <div className="mb-4 flex gap-2">
                {FILTERS.map((f) => (
                    <button
                        key={f.value}
                        onClick={() => applyFilter(f.value)}
                        className={`rounded-pill px-4 py-2 text-[12.5px] font-semibold transition ${
                            (filters.status || '') === f.value
                                ? 'bg-grass text-white'
                                : 'border border-cardBorder bg-white text-pine hover:border-grass'
                        }`}
                    >
                        {f.label}
                    </button>
                ))}
            </div>

            {feedbacks.data.length === 0 ? (
                <div className="rounded-[14px] border border-dashed border-cardBorder bg-white px-5 py-10 text-center text-[13.5px] text-moss">
                    Chưa có góp ý nào{filters.status ? ' ở trạng thái này' : ''}
                    .
                </div>
            ) : (
                <div className="space-y-3">
                    {feedbacks.data.map((f) => (
                        <FeedbackCard
                            key={f.id}
                            f={f}
                            open={openId === f.id}
                            onToggle={() =>
                                setOpenId(openId === f.id ? null : f.id)
                            }
                        />
                    ))}
                </div>
            )}

            {/* Pagination */}
            {feedbacks.last_page > 1 && (
                <div className="mt-4 flex items-center justify-between text-[12.5px] text-moss">
                    <span className="font-mono">
                        {feedbacks.from}–{feedbacks.to} / {feedbacks.total}
                    </span>
                    <div className="flex gap-2">
                        <button
                            disabled={feedbacks.current_page <= 1}
                            onClick={() => goPage(feedbacks.current_page - 1)}
                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                        >
                            Trước
                        </button>
                        <button
                            disabled={
                                feedbacks.current_page >= feedbacks.last_page
                            }
                            onClick={() => goPage(feedbacks.current_page + 1)}
                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                        >
                            Sau
                        </button>
                    </div>
                </div>
            )}

            {toastMsg && (
                <div className="fixed bottom-6 left-1/2 z-[90] -translate-x-1/2 rounded-pill bg-pine px-5 py-2.5 text-[13.5px] font-semibold text-white shadow-lg">
                    {toastMsg}
                </div>
            )}
        </div>
    );
}

/** 1 góp ý: header gọn + expand ra chi tiết + form phản hồi. */
function FeedbackCard({
    f,
    open,
    onToggle,
}: {
    f: FeedbackRow;
    open: boolean;
    onToggle: () => void;
}) {
    const form = useForm({ reply_content: f.reply_content ?? '' });

    const send = (e: React.FormEvent) => {
        e.preventDefault();
        form.patch(route('admin.feedbacks.reply', f.id), {
            preserveScroll: true,
        });
    };

    return (
        <div className="overflow-hidden rounded-[14px] border border-cardBorder bg-white">
            <button
                onClick={onToggle}
                className="flex w-full flex-wrap items-center gap-3 px-4 py-3.5 text-left transition hover:bg-[#fafcf7]"
            >
                <span
                    className={`rounded-pill px-2.5 py-1 text-[11.5px] font-bold ${
                        f.status === 'new'
                            ? 'bg-[#f7e7da] text-[#8a5a1f]'
                            : 'bg-[#dcebc4] text-[#3a5a1f]'
                    }`}
                >
                    {f.status === 'new' ? 'Chưa phản hồi' : 'Đã phản hồi'}
                </span>
                <span className="min-w-[120px] text-[14px] font-bold text-pine">
                    {f.name}
                </span>
                <span className="min-w-0 flex-1 truncate text-[13px] text-moss">
                    {f.content}
                </span>
                <span className="font-mono text-[11.5px] text-moss">
                    {f.created_at}
                </span>
            </button>

            {open && (
                <div
                    className="border-t border-[#f1f4ea] px-4 py-4"
                    style={{ background: '#fafcf7' }}
                >
                    <div className="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-[13px] text-moss">
                        {f.phone && (
                            <span>
                                📞{' '}
                                <a
                                    href={`tel:${f.phone}`}
                                    className="font-mono font-bold text-grass hover:underline"
                                >
                                    {f.phone}
                                </a>
                            </span>
                        )}
                        {f.email && (
                            <span>
                                ✉️{' '}
                                <span className="font-semibold text-pine">
                                    {f.email}
                                </span>
                            </span>
                        )}
                        {f.replied_at && (
                            <span>Đã phản hồi lúc {f.replied_at}</span>
                        )}
                    </div>

                    <div className="mb-4 whitespace-pre-line rounded-[12px] border border-cardBorder bg-white px-4 py-3 text-[14px] leading-[1.65] text-ink">
                        {f.content}
                    </div>

                    <form onSubmit={send}>
                        <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                            Nội dung phản hồi
                            {f.email ? (
                                <span className="ml-1 font-normal text-moss">
                                    — email gửi tới khách tự kèm lời chào theo
                                    tên + cảm ơn + chữ ký shop, bạn chỉ soạn
                                    phần trả lời
                                </span>
                            ) : (
                                <span className="ml-1 font-normal text-campfire">
                                    — khách không để email: liên hệ qua SĐT/Zalo
                                    rồi lưu ghi chú tại đây
                                </span>
                            )}
                        </label>
                        <textarea
                            value={form.data.reply_content}
                            onChange={(e) =>
                                form.setData('reply_content', e.target.value)
                            }
                            rows={4}
                            placeholder={
                                f.email
                                    ? 'VD: Tụi mình đã ghi nhận và sẽ bổ sung tính năng này trong bản cập nhật tới…'
                                    : 'VD: Đã gọi trao đổi với khách ngày …, khách hài lòng.'
                            }
                            className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                        />
                        {form.errors.reply_content && (
                            <p className="mt-1 text-[12px] text-[#b3493a]">
                                {form.errors.reply_content}
                            </p>
                        )}
                        <div className="mt-2.5 flex justify-end">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-[10px] bg-grass px-5 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                            >
                                {form.processing
                                    ? 'Đang gửi…'
                                    : f.email
                                      ? f.status === 'replied'
                                          ? 'Gửi lại email phản hồi'
                                          : 'Gửi email phản hồi'
                                      : 'Lưu & đánh dấu đã phản hồi'}
                            </button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
}

AdminFeedbacks.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
