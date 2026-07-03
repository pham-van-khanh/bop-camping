import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { money } from '@/lib/format';

/** Nền be/đất cho combo chưa có ảnh — đồng bộ tông Naturehike của thẻ sản phẩm. */
export const COMBO_GRAD = 'linear-gradient(150deg,#5c4033,#7f7a2b 55%,#557A2B)';

/** Dữ liệu tối thiểu để vẽ thẻ combo (trang chủ + /combos dùng chung). */
export type ComboCardData = {
    id: number;
    name: string;
    slug: string;
    combo_price: number;
    sum_individual: number;
    savings_amount: number;
    savings_percent: number;
    suitable_for: number | null;
    image?: string | null;
    items_count?: number;
    /** null/undefined = chưa chọn ngày; số = còn bao nhiêu bộ trong khoảng đã chọn. */
    available?: number | null;
};

export default function ComboCard({ c, index = 0 }: { c: ComboCardData; index?: number }) {
    const soldOut = c.available === 0;

    return (
        <motion.div
            initial={{ opacity: 0, y: 18 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2 }}
            transition={{ duration: 0.5, delay: Math.min(index, 6) * 0.05, ease: [0.2, 0.7, 0.2, 1] }}
        >
            <Link
                href={`/combos/${c.slug}`}
                className="group flex h-full flex-col overflow-hidden rounded-card border border-cardBorder bg-card transition duration-200 hover:-translate-y-1 hover:shadow-cardhover"
            >
                <div className="relative h-[170px]" style={{ background: COMBO_GRAD }}>
                    {c.image && <img src={c.image} alt={c.name} className="absolute inset-0 h-full w-full object-cover" />}
                    <div className="absolute inset-0" style={{ background: 'radial-gradient(120px 90px at 78% 22%, rgba(255,255,255,.3), transparent 60%)' }} />
                    <div className="absolute inset-x-0 bottom-0 h-16" style={{ background: 'linear-gradient(180deg, rgba(24,35,15,0), rgba(24,35,15,.5))' }} />

                    {/* Badge tiết kiệm — điểm bán chính của combo (US-05) */}
                    {c.savings_amount > 0 && (
                        <span className="absolute left-3 top-3 rounded-pill bg-campfire px-2.5 py-1 font-mono text-[11px] font-bold text-white">
                            Tiết kiệm {c.savings_percent}%
                        </span>
                    )}
                    {c.available != null && (
                        <span
                            className="absolute right-3 top-3 rounded-pill px-2.5 py-1 font-mono text-[11px] font-bold text-white"
                            style={{ background: soldOut ? '#b3493a' : 'rgba(44,61,34,.72)' }}
                        >
                            {soldOut ? 'Hết trong khoảng này' : `Còn ${c.available} bộ`}
                        </span>
                    )}
                    <span className="absolute bottom-3 left-3 font-mono text-[11px] tracking-[0.05em] text-white/90">
                        COMBO{c.items_count ? ` · ${c.items_count} MÓN` : ''}
                    </span>
                </div>

                <div className="flex flex-1 flex-col px-4 pb-4 pt-3.5">
                    <div className="min-h-[39px] text-[15.5px] font-bold leading-[1.25] text-ink">{c.name}</div>
                    {c.suitable_for && (
                        <span className="mt-1.5 self-start rounded-pill px-2.5 py-1 text-[11.5px] font-semibold" style={{ background: '#e7eed5', color: '#3a5a1f' }}>
                            Hợp cho {c.suitable_for} người
                        </span>
                    )}
                    <div className="mt-auto flex items-end justify-between pt-2.5">
                        <div>
                            <div className="font-mono text-[17px] font-bold text-grass">
                                {money(c.combo_price)}<span className="font-sans text-[12px] font-normal text-[#8a967a]">/ngày</span>
                            </div>
                            {c.savings_amount > 0 && (
                                <div className="font-mono text-[12px] text-[#8a967a] line-through">{money(c.sum_individual)}</div>
                            )}
                        </div>
                        <span className="text-[13px] font-bold text-grass transition group-hover:translate-x-0.5">Xem bộ →</span>
                    </div>
                </div>
            </Link>
        </motion.div>
    );
}
