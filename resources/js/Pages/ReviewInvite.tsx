import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import type { PageProps } from '@/types';

type ItemRow = { order_item_id: number; name: string };
type Props = PageProps<{
    found: boolean;
    token?: string;
    submitted?: boolean;
    customer_name?: string;
    code?: string;
    items?: ItemRow[];
}>;

function Stars({ value, onPick, size = 26 }: { value: number; onPick: (n: number) => void; size?: number }) {
    const [hover, setHover] = useState(0);
    return (
        <div className="flex" style={{ gap: 3 }} onMouseLeave={() => setHover(0)}>
            {[1, 2, 3, 4, 5].map((n) => (
                <span key={n} role="button" aria-label={`${n} sao`}
                    onMouseEnter={() => setHover(n)} onClick={() => onPick(n)}
                    style={{ fontSize: size, lineHeight: 1, cursor: 'pointer', color: (hover || value) >= n ? '#C97B36' : '#d8dcc8' }}>★</span>
            ))}
        </div>
    );
}

export default function ReviewInvite() {
    const { found, token, submitted, customer_name, code, items, flash } = usePage<Props>().props;
    const [done, setDone] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        system_rating: number;
        system_content: string;
        items: { order_item_id: number; rating: number; content: string }[];
    }>({
        system_rating: 0,
        system_content: '',
        items: (items ?? []).map((i) => ({ order_item_id: i.order_item_id, rating: 0, content: '' })),
    });

    const setItem = (idx: number, patch: Partial<{ rating: number; content: string }>) =>
        setData('items', data.items.map((it, j) => (j === idx ? { ...it, ...patch } : it)));

    const submit = () => post(route('review.invite.store', token), { preserveScroll: true, onSuccess: () => setDone(true) });

    const hasRating = data.system_rating > 0 || data.items.some((i) => i.rating > 0);

    return (
        <>
            <Head title="Đánh giá chuyến đi" />
            <main className="mx-auto max-w-[640px] px-5 pb-16 pt-8">
                {!found ? (
                    <Card>
                        <h1 className="text-[20px] font-extrabold text-ink">Không tìm thấy lời mời đánh giá</h1>
                        <p className="mt-2 text-[14px] text-moss">Link có thể đã hết hạn hoặc không đúng. Bạn có thể đánh giá trực tiếp ở trang sản phẩm sau khi đăng nhập.</p>
                        <Link href="/thiet-bi" className="mt-4 inline-grid h-11 place-items-center rounded-control bg-grass px-6 font-bold text-white">Xem thiết bị</Link>
                    </Card>
                ) : submitted || done ? (
                    <Card>
                        <div className="text-[40px]">🎉</div>
                        <h1 className="mt-2 text-[20px] font-extrabold text-ink">Cảm ơn bạn đã đánh giá!</h1>
                        <p className="mt-2 text-[14px] text-moss">{flash.success || 'Đánh giá sẽ hiển thị sau khi được tụi mình duyệt. Hẹn gặp lại chuyến sau!'}</p>
                        <Link href="/" className="mt-4 inline-grid h-11 place-items-center rounded-control bg-grass px-6 font-bold text-white">Về trang chủ</Link>
                    </Card>
                ) : (
                    <>
                        <h1 className="text-[26px] font-extrabold tracking-tight text-ink">Đánh giá chuyến đi</h1>
                        <p className="mt-1 text-[14px] text-moss">Chào {customer_name}, đơn <span className="font-mono font-bold text-grass">{code}</span> đã hoàn tất. Chia sẻ cảm nhận giúp tụi mình nhé!</p>

                        {/* Tổng thể shop */}
                        <Card className="mt-5">
                            <div className="mb-1 font-mono text-[12px] uppercase tracking-[0.1em] text-campfire">Trải nghiệm tổng thể</div>
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-[16px] font-bold text-ink">Dịch vụ & giao nhận của shop</h2>
                                <Stars value={data.system_rating} onPick={(n) => setData('system_rating', n)} />
                            </div>
                            <textarea value={data.system_content} onChange={(e) => setData('system_content', e.target.value)}
                                placeholder="Giao nhận, tư vấn, tình trạng đồ... thế nào?" rows={2}
                                className="w-full rounded-[11px] border border-cardBorder bg-white px-3.5 py-2.5 text-[14px] outline-none focus:border-grass" />
                        </Card>

                        {/* Từng sản phẩm */}
                        {(items ?? []).map((it, idx) => (
                            <Card key={it.order_item_id} className="mt-3">
                                <div className="mb-3 flex items-center justify-between gap-3">
                                    <h2 className="text-[15px] font-bold text-ink">{it.name}</h2>
                                    <Stars value={data.items[idx]?.rating ?? 0} onPick={(n) => setItem(idx, { rating: n })} />
                                </div>
                                <textarea value={data.items[idx]?.content ?? ''} onChange={(e) => setItem(idx, { content: e.target.value })}
                                    placeholder="Đồ này dùng thế nào?" rows={2}
                                    className="w-full rounded-[11px] border border-cardBorder bg-white px-3.5 py-2.5 text-[14px] outline-none focus:border-grass" />
                            </Card>
                        ))}

                        {(errors as Record<string, string>).review && (
                            <p className="mt-3 text-[13px] text-red-500">{(errors as Record<string, string>).review}</p>
                        )}

                        <button onClick={submit} disabled={processing || !hasRating}
                            className="mt-5 h-12 w-full rounded-control text-[16px] font-bold text-white transition disabled:cursor-not-allowed"
                            style={{ background: !processing && hasRating ? '#557A2B' : '#c4cfae' }}>
                            {processing ? 'Đang gửi…' : 'Gửi đánh giá'}
                        </button>
                        <p className="mt-2 text-center font-mono text-[12px] text-moss">Chỉ cần chấm sao mục bạn muốn đánh giá.</p>
                    </>
                )}
            </main>
        </>
    );
}

function Card({ children, className = '' }: { children: ReactNode; className?: string }) {
    return <div className={`rounded-card border border-cardBorder bg-card p-5 ${className}`}>{children}</div>;
}

ReviewInvite.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
