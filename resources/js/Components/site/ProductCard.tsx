import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { money } from '@/lib/format';
import { catLabel, type Product } from '@/lib/catalog';

/** Thẻ sản phẩm dùng chung cho Trang chủ + Danh sách (RULES mục 7). */
export default function ProductCard({ p, compact = false, index = 0 }: { p: Product; compact?: boolean; index?: number }) {
    const low = p.stock <= 2;
    return (
        <motion.div
            initial={{ opacity: 0, y: 18 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2 }}
            transition={{ duration: 0.5, delay: Math.min(index, 6) * 0.05, ease: [0.2, 0.7, 0.2, 1] }}
        >
            <Link
                href={`/thiet-bi/${p.id}`}
                className="group flex h-full flex-col overflow-hidden rounded-card border border-cardBorder bg-card transition duration-200 hover:-translate-y-1 hover:shadow-cardhover"
            >
                <div className="relative h-[170px]" style={{ background: p.grad }}>
                    <div className="absolute inset-0" style={{ background: 'radial-gradient(120px 90px at 78% 22%, rgba(255,255,255,.35), transparent 60%)' }} />
                    <div className="absolute inset-x-0 bottom-0 h-16" style={{ background: 'linear-gradient(180deg, rgba(24,35,15,0), rgba(24,35,15,.5))' }} />
                    <span
                        className={`absolute left-3 top-3 rounded-pill px-2.5 py-1 font-mono text-[11px] font-bold text-white ${low ? 'bg-campfire' : ''}`}
                        style={low ? undefined : { background: 'rgba(44,61,34,.72)' }}
                    >
                        {low ? `Sắp hết · ${p.stock} bộ` : `Còn ${p.stock} bộ`}
                    </span>
                    <span className="absolute bottom-3 left-3 font-mono text-[11px] tracking-[0.05em] text-white/90">{catLabel(p.cat)}</span>
                </div>
                <div className="flex flex-1 flex-col px-4 pb-4 pt-3.5">
                    <div className="min-h-[39px] text-[15.5px] font-bold leading-[1.25] text-ink">{p.name}</div>
                    {!compact && <p className="mb-2.5 mt-1.5 min-h-[36px] text-[12.5px] leading-[1.45] text-[#8a967a]">{p.blurb}</p>}
                    <div className={`flex items-end justify-between ${compact ? 'mt-2.5' : 'mt-auto'}`}>
                        <div className="font-mono text-[17px] font-bold text-ink">
                            {money(p.price)}<span className="font-sans text-[12px] font-normal text-[#8a967a]">/ngày</span>
                        </div>
                        <div className="font-mono text-[11px] text-campfire">cọc {money(p.deposit)}</div>
                    </div>
                </div>
            </Link>
        </motion.div>
    );
}
