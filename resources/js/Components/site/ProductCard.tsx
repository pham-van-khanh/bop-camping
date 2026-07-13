import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { money } from '@/lib/format';
import type { ProductResource } from '@/types/product';

const GRAD: Record<string, string> = {
    'leu-cam-trai':      'linear-gradient(150deg,#3a5a40,#588157)',
    'tui-ngu':           'linear-gradient(150deg,#4a4e69,#9a8c98)',
    'bep-nau-an':        'linear-gradient(150deg,#7f4f24,#b6873a)',
    'ban-ghe-da-ngoai':  'linear-gradient(150deg,#4a6741,#7a9b6b)',
    'den-chieu-sang':    'linear-gradient(150deg,#3d405b,#6e7db0)',
    'ba-lo-tui':         'linear-gradient(150deg,#5c4033,#8b6355)',
};
const gradFor = (slug: string) => GRAD[slug] ?? 'linear-gradient(150deg,#4a6741,#7a9b6b)';

const truncate = (s: string | null, max = 80) =>
    s && s.length > max ? s.slice(0, max).trimEnd() + '…' : (s ?? '');

/** Badge vị trí phục vụ — pill trắng có icon ghim, nổi trên ảnh sản phẩm. */
function LocationBadge({ label }: { label: string }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-pill bg-white/90 px-2 py-1 text-[10.5px] font-bold text-pine shadow-[0_2px_6px_rgba(24,35,15,.18)] backdrop-blur-sm">
            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" className="flex-none">
                <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" fill="#C97B36" stroke="#C97B36" strokeWidth="1.5" strokeLinejoin="round" />
                <circle cx="12" cy="10" r="2.4" fill="#fff" />
            </svg>
            {label}
        </span>
    );
}

/** Thẻ sản phẩm dùng chung cho Trang chủ + Danh sách (RULES mục 7). */
export default function ProductCard({ p, compact = false, index = 0 }: { p: ProductResource; compact?: boolean; index?: number }) {
    const low = p.quantity <= 2;
    const bg  = gradFor(p.category.slug);
    const locations = p.locations ?? [];
    // Gộp "Toàn hệ thống" chỉ khi có ≥2 vị trí; 1 vị trí thì hiện thẳng tên nơi đó.
    const showAllBadge = !!p.all_locations && locations.length > 1;

    return (
        <motion.div
            initial={{ opacity: 0, y: 18 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2 }}
            transition={{ duration: 0.5, delay: Math.min(index, 6) * 0.05, ease: [0.2, 0.7, 0.2, 1] }}
        >
            <Link
                href={`/thiet-bi/${p.slug}`}
                className="group flex h-full flex-col overflow-hidden rounded-card border border-cardBorder bg-card transition duration-200 hover:-translate-y-1 hover:shadow-cardhover"
            >
                <div className={`relative ${compact ? 'h-[170px]' : 'h-[240px]'}`} style={{ background: bg }}>
                    {p.thumbnail && (
                        <img src={p.thumbnail} alt={p.name} className="absolute inset-0 h-full w-full object-cover" />
                    )}
                    <div className="absolute inset-0" style={{ background: 'radial-gradient(120px 90px at 78% 22%, rgba(255,255,255,.35), transparent 60%)' }} />
                    <span
                        className={`absolute left-3 top-3 rounded-pill px-2.5 py-1 font-mono text-[11px] font-bold text-white ${low ? 'bg-campfire' : ''}`}
                        style={low ? undefined : { background: 'rgba(44,61,34,.72)' }}
                    >
                        {low ? `Sắp hết · ${p.quantity} bộ` : `Còn ${p.quantity} bộ`}
                    </span>
                    {locations.length > 0 && (
                        <div className="absolute bottom-2.5 right-2.5 flex max-w-[70%] flex-wrap justify-end gap-1">
                            {showAllBadge ? (
                                <LocationBadge label="Toàn hệ thống" />
                            ) : (
                                locations.map((l) => <LocationBadge key={l.slug} label={l.name} />)
                            )}
                        </div>
                    )}
                </div>
                <div className="flex flex-1 flex-col px-4 pb-4 pt-3.5">
                    <div className="min-h-[39px] text-[15.5px] font-bold leading-[1.25] text-ink">{p.name}</div>
                    {!compact && <p className="mb-2.5 mt-1.5 min-h-[36px] text-[12.5px] leading-[1.45] text-[#8a967a]">{truncate(p.description)}</p>}
                    <div className={`flex items-end justify-between ${compact ? 'mt-2.5' : 'mt-auto'}`}>
                        <div className="font-mono text-[17px] font-bold text-ink">
                            {money(p.price_per_day)}<span className="font-sans text-[12px] font-normal text-[#8a967a]">/ngày</span>
                        </div>
                        <div className="font-mono text-[11px] text-campfire">cọc {money(p.deposit)}</div>
                    </div>
                </div>
            </Link>
        </motion.div>
    );
}
