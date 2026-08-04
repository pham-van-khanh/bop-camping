import { LocationBadges } from '@/Components/site/LocationBadge';
import { money } from '@/lib/format';
import type { ProductResource } from '@/types/product';
import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';

const GRAD: Record<string, string> = {
    'leu-cam-trai': 'linear-gradient(150deg,#3a5a40,#588157)',
    'tui-ngu': 'linear-gradient(150deg,#4a4e69,#9a8c98)',
    'bep-nau-an': 'linear-gradient(150deg,#7f4f24,#b6873a)',
    'ban-ghe-da-ngoai': 'linear-gradient(150deg,#4a6741,#7a9b6b)',
    'den-chieu-sang': 'linear-gradient(150deg,#3d405b,#6e7db0)',
    'ba-lo-tui': 'linear-gradient(150deg,#5c4033,#8b6355)',
};
const gradFor = (slug: string) =>
    GRAD[slug] ?? 'linear-gradient(150deg,#4a6741,#7a9b6b)';

const truncate = (s: string | null, max = 80) =>
    s && s.length > max ? s.slice(0, max).trimEnd() + '…' : (s ?? '');

/** Thẻ sản phẩm dùng chung cho Trang chủ + Danh sách (RULES mục 7). */
export default function ProductCard({
    p,
    compact = false,
    index = 0,
}: {
    p: ProductResource;
    compact?: boolean;
    index?: number;
}) {
    const bg = gradFor(p.category.slug);
    const locations = p.locations ?? [];

    // Khách đã chọn khoảng ngày (bopcamping-3kn9): badge tồn phải nói theo KHOẢNG ĐÓ,
    // không phải tổng tồn cả kho — nếu không thì "Còn 8 bộ" trên món đã kín lịch là nói dối.
    const hasRange = p.available !== null && p.available !== undefined;
    const stock = hasRange ? (p.available as number) : p.quantity;
    const soldOut = hasRange && stock < 1;
    const low = !soldOut && stock <= 2;
    /**
     * Khách chưa chọn kho thì con số là max qua các kho. Kho nào giữ số đó thì nói ra
     * (bopcamping-kvcc) — không nói thì khách thêm 3 vào giỏ rồi tới checkout mới biết không
     * kho nào đủ 3, vì cả giỏ phải nằm trong MỘT kho. Server chỉ gửi tên khi các kho LỆCH nhau.
     *
     * `hasRange` là chốt phòng thủ CÓ CHỦ ĐÍCH: chưa chọn ngày thì `stock` là tồn TĨNH cả kho,
     * ghép thêm tên kho vào đó sẽ thành một con số sai kiểu mới. Không cần chốt `!soldOut` vì
     * nhánh 'Hết hàng' phía dưới vốn không dùng `at`.
     */
    const at = hasRange ? (p.available_at ?? null) : null;

    return (
        <motion.div
            initial={{ opacity: 0, y: 18 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, amount: 0.2 }}
            transition={{
                duration: 0.5,
                delay: Math.min(index, 6) * 0.05,
                ease: [0.2, 0.7, 0.2, 1],
            }}
        >
            <Link
                href={`/thiet-bi/${p.slug}`}
                aria-label={
                    soldOut
                        ? `${p.name} — hết hàng trong khoảng ngày đã chọn`
                        : undefined
                }
                className={`group flex h-full flex-col overflow-hidden rounded-card border border-cardBorder bg-card transition duration-200 hover:-translate-y-1 hover:shadow-cardhover ${soldOut ? 'opacity-50 grayscale-[35%] hover:opacity-100' : ''}`}
            >
                <div
                    className={`relative h-[240px]`}
                    style={{ background: bg }}
                >
                    {p.thumbnail && (
                        <img
                            src={p.thumbnail}
                            srcSet={p.thumbnail_srcset ?? undefined}
                            /* Thẻ rộng ~293px ở lưới desktop, gần full width trên mobile
                               → @2x cần ~586px, browser chọn bậc 800 (bopcamping-ix4n). */
                            sizes="(min-width: 1024px) 300px, (min-width: 640px) 50vw, 100vw"
                            loading="lazy"
                            alt={p.name}
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    )}
                    <div
                        className="absolute inset-0"
                        style={{
                            background:
                                'radial-gradient(120px 90px at 78% 22%, rgba(255,255,255,.35), transparent 60%)',
                        }}
                    />
                    <span
                        className={`absolute left-3 top-3 max-w-[calc(100%-1.5rem)] truncate rounded-pill px-2.5 py-1 font-mono text-[11px] font-bold text-white ${low || soldOut ? 'bg-campfire' : ''}`}
                        style={
                            low || soldOut
                                ? undefined
                                : { background: 'rgba(44,61,34,.72)' }
                        }
                    >
                        {soldOut
                            ? 'Hết hàng'
                            : `${low ? 'Sắp hết · ' : 'Còn '}${stock} bộ${at ? ` tại ${at}` : ''}`}
                    </span>
                    <LocationBadges
                        locations={locations}
                        allLocations={p.all_locations}
                    />
                </div>
                <div className="flex flex-1 flex-col px-4 pb-4 pt-3.5">
                    <div className="min-h-[39px] text-[15.5px] font-bold leading-[1.25] text-ink">
                        {p.name}
                    </div>
                    {!compact && (
                        <p className="mb-2.5 mt-1.5 min-h-[36px] text-[12.5px] leading-[1.45] text-[#8a967a]">
                            {truncate(p.description)}
                        </p>
                    )}
                    <div
                        className={`flex items-end justify-between ${compact ? 'mt-2.5' : 'mt-auto'}`}
                    >
                        <div className="font-mono text-[17px] font-bold text-ink">
                            {money(p.price_per_day)}
                            <span className="font-sans text-[12px] font-normal text-[#8a967a]">
                                /ngày
                            </span>
                        </div>
                        <div className="font-mono text-[11px] text-campfire">
                            cọc {money(p.deposit)}
                        </div>
                    </div>
                </div>
            </Link>
        </motion.div>
    );
}
