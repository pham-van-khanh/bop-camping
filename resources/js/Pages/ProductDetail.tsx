import { Head, Link } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import DateRangeCalendar from '@/Components/site/DateRangeCalendar';
import { catLabel, getProduct } from '@/lib/catalog';
import { dayCount, fromISO, money, rangeText, toISO } from '@/lib/format';
import { addLine } from '@/lib/cart';
import { emit, EVENTS } from '@/lib/bus';

export default function ProductDetail({ id }: { id: number }) {
    const product = getProduct(Number(id));
    const [activeImg, setActiveImg] = useState(0);
    const [start, setStart] = useState<string | null>(null);
    const [end, setEnd] = useState<string | null>(null);
    const [qty, setQty] = useState(1);

    // Ngày "hết hàng" mẫu (ổn định theo id) để minh hoạ lịch.
    const unavailable = useMemo(() => {
        const set = new Set<string>();
        if (!product) return set;
        const base = new Date();
        [5, 6, 12, 13, 20].forEach((o) => {
            const off = o + (product.id % 4);
            set.add(toISO(new Date(base.getFullYear(), base.getMonth(), base.getDate() + off)));
        });
        return set;
    }, [product]);

    if (!product) {
        return (
            <>
                <Head title="Không tìm thấy thiết bị" />
                <main className="mx-auto max-w-[640px] px-5 py-20 text-center">
                    <div className="mb-2.5 text-[34px]">⛺</div>
                    <h1 className="mb-2 text-2xl font-extrabold text-ink">Không tìm thấy thiết bị</h1>
                    <p className="mb-6 text-moss">Món này có thể đã ngừng cho thuê.</p>
                    <Link href="/thiet-bi" className="inline-grid h-[46px] place-items-center rounded-control bg-grass px-6 font-bold text-white">Về danh sách</Link>
                </main>
            </>
        );
    }

    const gallery = [150, 35, 205, 330].map((a) => product.grad.replace('150deg', `${a}deg`));
    const days = start && end ? dayCount(start, end) : 0;

    let availState: 'none' | 'ok' | 'bad' = 'none';
    if (start && end) {
        availState = 'ok';
        for (let t = fromISO(start).getTime(); t <= fromISO(end).getTime(); t += 86400000) {
            if (unavailable.has(toISO(new Date(t)))) { availState = 'bad'; break; }
        }
    }
    const canAdd = availState === 'ok';
    const subtotal = product.price * qty * days;
    const subDeposit = product.deposit * qty;
    const lowStock = product.stock <= 2;

    const onChange = (s: string | null, e: string | null) => { setStart(s); setEnd(e); };

    const add = () => {
        if (!canAdd || !start || !end) return;
        addLine({ id: product.id, name: product.name, cat: product.cat, grad: product.grad, price: product.price, deposit: product.deposit, qty, start, end });
        emit(EVENTS.toast, `Đã thêm ${product.name} vào giỏ`);
    };

    return (
        <>
            <Head title={product.name} />
            <main className="mx-auto max-w-[1120px] px-5 pb-12 pt-6">
                <Link href="/thiet-bi" className="mb-2.5 inline-block py-2 text-[14px] font-semibold text-moss hover:text-grass">← Quay lại danh sách</Link>
                <div className="grid items-start gap-[34px]" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))' }}>
                    {/* gallery */}
                    <div>
                        <div className="relative h-[360px] overflow-hidden rounded-card" style={{ background: gallery[activeImg] }}>
                            <div className="absolute inset-0" style={{ background: 'radial-gradient(220px 150px at 76% 20%, rgba(255,255,255,.34), transparent 60%)' }} />
                            <div className="absolute inset-x-0 bottom-0 h-[90px]" style={{ background: 'linear-gradient(180deg,rgba(24,35,15,0),rgba(24,35,15,.4))' }} />
                            <span className="absolute bottom-4 left-[18px] font-mono text-[13px] tracking-[0.06em] text-white">{catLabel(product.cat)}</span>
                        </div>
                        <div className="mt-2.5 grid grid-cols-4 gap-2.5">
                            {gallery.map((g, i) => (
                                <button
                                    key={i}
                                    onClick={() => setActiveImg(i)}
                                    aria-label={`Ảnh ${i + 1}`}
                                    className="h-[70px] rounded-[11px] transition"
                                    style={{ background: g, outline: i === activeImg ? '2px solid #557A2B' : '1px solid #E3E8D6', outlineOffset: i === activeImg ? 1 : 0 }}
                                />
                            ))}
                        </div>
                    </div>

                    {/* info */}
                    <div>
                        <h1 className="mb-2.5 font-extrabold leading-[1.15] tracking-tight text-ink" style={{ fontSize: 'clamp(24px,3vw,30px)' }}>{product.name}</h1>
                        <div className="mb-[18px] flex flex-wrap items-center gap-3.5">
                            <span className="font-mono text-[13px] text-campfire">★ {product.rating}</span>
                            <span className="rounded-pill px-2.5 py-1 text-[12px] font-semibold" style={{ background: lowStock ? '#f7e7da' : '#dceaf6', color: lowStock ? '#C97B36' : '#2a6ea0' }}>
                                {lowStock ? `Sắp hết · còn ${product.stock} bộ` : `Còn ${product.stock} bộ trong kho`}
                            </span>
                        </div>
                        <div className="mb-5 flex flex-wrap gap-2.5">
                            <div className="min-w-[140px] flex-1 rounded-[13px] px-4 py-3.5" style={{ background: '#eef2e3' }}>
                                <div className="mb-1 text-[12px] text-moss">Giá thuê</div>
                                <div><span className="font-mono text-[22px] font-bold text-grass">{money(product.price)}</span><span className="text-[13px] text-[#8a967a]">/ngày</span></div>
                            </div>
                            <div className="min-w-[140px] flex-1 rounded-[13px] px-4 py-3.5" style={{ background: '#f7efe5' }}>
                                <div className="mb-1 text-[12px]" style={{ color: '#9a7a4a' }}>Tiền cọc (hoàn lại)</div>
                                <div className="font-mono text-[22px] font-bold text-campfire">{money(product.deposit)}</div>
                            </div>
                        </div>
                        <p className="mb-[18px] text-[15px] leading-[1.6] text-[#3f4a32]">{product.blurb}</p>
                        <div className="mb-5 border-t border-cardBorder pt-4">
                            {product.specs.map((sp) => (
                                <div key={sp.k} className="flex justify-between py-[7px] text-[14px]">
                                    <span className="text-[#8a967a]">{sp.k}</span>
                                    <span className="font-semibold text-pine">{sp.v}</span>
                                </div>
                            ))}
                        </div>

                        <DateRangeCalendar start={start} end={end} unavailable={unavailable} onChange={onChange} />

                        {/* range + qty + availability */}
                        <div className="mt-4 rounded-[14px] border border-cardBorder bg-white p-4">
                            <div className="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <div className="text-[12px] text-[#8a967a]">Khoảng thuê</div>
                                    <div className="font-mono text-[15px] font-bold text-ink">{rangeText(start, end)}</div>
                                </div>
                                <div className="flex items-center gap-2.5">
                                    <span className="text-[13px] text-moss">Số bộ</span>
                                    <div className="flex items-center overflow-hidden rounded-[10px] border border-cardBorder">
                                        <button onClick={() => setQty((q) => Math.max(1, q - 1))} className="h-9 w-[34px] bg-[#f1f4ea] text-[18px] text-grass">−</button>
                                        <span className="w-[38px] text-center font-mono font-bold">{qty}</span>
                                        <button onClick={() => setQty((q) => Math.min(product.stock, q + 1))} className="h-9 w-[34px] bg-[#f1f4ea] text-[18px] text-grass">+</button>
                                    </div>
                                </div>
                            </div>

                            {availState !== 'none' && (
                                <div className="rounded-[10px] px-3 py-2 text-[13px] font-semibold"
                                    style={availState === 'ok' ? { background: '#dcebc4', color: '#3a5a1f' } : { background: '#f6ddd6', color: '#b3493a' }}>
                                    {availState === 'ok' ? `Còn đủ ${product.stock} bộ cho khoảng này` : 'Khoảng này có ngày đã hết, chọn ngày khác giúp tụi mình nhé'}
                                </div>
                            )}

                            <div className="mt-3.5 flex items-center justify-between gap-3">
                                <div>
                                    <div className="text-[12px] text-[#8a967a]">Tạm tính {days > 0 ? `(${days} ngày)` : ''}</div>
                                    <div className="font-mono text-[20px] font-bold text-grass">{money(subtotal)}</div>
                                    <div className="font-mono text-[11px] text-campfire">+ cọc {money(subDeposit)}</div>
                                </div>
                                <button
                                    onClick={add}
                                    disabled={!canAdd}
                                    className="h-[50px] rounded-control px-6 font-bold text-white transition disabled:cursor-not-allowed"
                                    style={{ background: canAdd ? '#557A2B' : '#c4cfae' }}
                                >
                                    {start && end ? 'Thêm vào giỏ' : 'Chọn ngày'}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}

ProductDetail.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
