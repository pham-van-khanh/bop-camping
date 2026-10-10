import { terrainGradient } from '@/lib/terrain';
import { Link } from '@inertiajs/react';

/** Dữ liệu thẻ điểm cắm trại — khớp LocalLandingController::spotCard(). */
export type SpotCardData = {
    id: number;
    name: string;
    slug: string;
    province: string;
    district: string | null;
    terrain_tag: string;
    season_label: string;
    description: string | null;
    nearest_name: string | null;
    image: string | null;
    image_srcset: string | null;
};

/**
 * Thẻ điểm cắm trại dẫn sang trang riêng /diem-cam-trai/{slug} (bopcamping-y05c).
 * Dùng chung cho trang khu vực, danh sách điểm và mục "điểm gần đây".
 */
export default function SpotCard({ s }: { s: SpotCardData }) {
    return (
        <Link
            href={`/diem-cam-trai/${s.slug}`}
            className="group flex flex-col overflow-hidden rounded-card border border-cardBorder bg-card transition hover:-translate-y-0.5 hover:shadow-cardhover"
        >
            <div
                className="relative h-[150px] overflow-hidden"
                style={{ background: terrainGradient(s.terrain_tag) }}
            >
                {s.image && (
                    <img
                        src={s.image}
                        srcSet={s.image_srcset ?? undefined}
                        sizes="(min-width: 1024px) 340px, (min-width: 640px) 50vw, 100vw"
                        alt={`Cắm trại ở ${s.name}`}
                        loading="lazy"
                        className="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                    />
                )}
                <span
                    className="absolute bottom-3 left-3 rounded-pill px-3 py-1 font-mono text-[12px] text-white"
                    style={{ background: 'rgba(24,35,15,.55)' }}
                >
                    {s.terrain_tag}
                </span>
            </div>
            <div className="flex flex-1 flex-col p-[18px]">
                <h3 className="text-[17px] font-bold text-ink group-hover:text-grass">
                    {s.name}
                </h3>
                <div className="mt-1 font-mono text-[12.5px] text-grass">
                    {s.province}
                    {s.district ? ` · ${s.district}` : ''}
                </div>
                {s.description && (
                    <p className="mt-2.5 line-clamp-2 text-[14px] leading-[1.55] text-[#3f4a32]">
                        {s.description}
                    </p>
                )}
                <div className="flex-1" />
                <div className="mt-3.5 flex items-center justify-between border-t border-[#eef2e3] pt-3">
                    <span className="font-mono text-[11px] tracking-[0.08em] text-moss">
                        MÙA ĐẸP
                    </span>
                    <span className="font-mono text-[13px] font-bold text-campfire">
                        {s.season_label}
                    </span>
                </div>
            </div>
        </Link>
    );
}
