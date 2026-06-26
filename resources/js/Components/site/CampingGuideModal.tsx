import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { terrainGradient } from '@/lib/terrain';

export type SpotMedia = { type: 'image' | 'video'; url: string };
export type GuideSpot = {
    id: number;
    name: string;
    terrain_tag: string;
    province: string;
    district: string | null;
    description: string | null;
    season_label: string;
    map_url: string | null;
    nearest_name: string | null;
    media: SpotMedia[];
};
export type ProvinceGroup = { province: string; spots: GuideSpot[] };

/**
 * Modal Cẩm nang cắm trại — các điểm gom theo tỉnh/thành (thẻ gradient theo địa hình).
 * (Bấm thẻ xem chi tiết ảnh/video sẽ bổ sung ở bước sau.)
 */
export default function CampingGuideModal({
    provinces,
    cities,
    onClose,
}: {
    provinces: ProvinceGroup[];
    cities: string;
    onClose: () => void;
}) {
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            window.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [onClose]);

    return (
        <div onClick={onClose} className="fixed inset-0 z-[95] flex items-start justify-center overflow-y-auto p-4 sm:p-6" style={{ background: 'rgba(24,35,15,.6)', backdropFilter: 'blur(4px)' }}>
            <div onClick={(e) => e.stopPropagation()} className="my-2 w-full max-w-[1080px] overflow-hidden rounded-[22px] bg-[#f3f5ec] shadow-2xl">
                {/* Header */}
                <div className="relative px-6 py-6 sm:px-9 sm:py-7" style={{ background: 'linear-gradient(135deg,#2C3D22,#3f5a2a)' }}>
                    <div className="mb-1.5 font-mono text-[12px] tracking-[0.14em] text-[#bfe06a]">CẨM NANG CẮM TRẠI</div>
                    <h2 className="max-w-[640px] font-extrabold tracking-tight text-white" style={{ fontSize: 'clamp(24px,3.4vw,34px)' }}>
                        Những nơi cắm trại đẹp khắp Việt Nam
                    </h2>
                    <p className="mt-2 max-w-[640px] text-[14px] leading-[1.6] text-white/80">
                        Tụi mình tổng hợp các điểm cắm trại được yêu thích theo từng tỉnh thành, kèm địa hình và mùa đẹp nhất. Thuê đủ đồ ở {cities} rồi lên đường thôi.
                    </p>
                    <button onClick={onClose} aria-label="Đóng" className="absolute right-5 top-5 grid h-9 w-9 place-items-center rounded-full text-white/90 transition hover:bg-white/15" style={{ background: 'rgba(255,255,255,.12)' }}>×</button>
                </div>

                {/* Body */}
                <div className="px-6 py-6 sm:px-9">
                    {provinces.length === 0 ? (
                        <p className="py-12 text-center text-[14px] text-moss">Chưa có điểm cắm trại nào.</p>
                    ) : (
                        provinces.map((g) => (
                            <section key={g.province} className="mb-8 last:mb-2">
                                <div className="mb-3.5 flex items-center gap-2.5">
                                    <h3 className="text-[20px] font-extrabold tracking-tight text-ink">{g.province}</h3>
                                    <span className="rounded-full bg-[#e7eed5] px-2.5 py-1 font-mono text-[11.5px] text-[#3a5a1f]">{g.spots.length} điểm</span>
                                </div>
                                <div className="grid gap-4" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))' }}>
                                    {g.spots.map((s) => (
                                        <article key={s.id} className="overflow-hidden rounded-[16px] bg-card" style={{ border: '1px solid #E3E8D6' }}>
                                            <div className="relative h-[120px]" style={{ background: terrainGradient(s.terrain_tag) }}>
                                                <span className="absolute bottom-3 left-3 rounded-pill px-3 py-1 font-mono text-[12px] text-white" style={{ background: 'rgba(24,35,15,.45)' }}>{s.terrain_tag}</span>
                                            </div>
                                            <div className="p-[18px]">
                                                <h4 className="text-[17px] font-bold text-ink">{s.name}</h4>
                                                <div className="mt-1 flex items-center gap-1.5 font-mono text-[12.5px] text-grass">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" /><circle cx="12" cy="10" r="2.4" stroke="currentColor" strokeWidth="1.7" /></svg>
                                                    {s.province}{s.district ? ` · ${s.district}` : ''}
                                                </div>
                                                {s.description && <p className="mt-2.5 text-[14px] leading-[1.55] text-[#3f4a32]">{s.description}</p>}
                                                <div className="mt-3.5 flex items-center justify-between border-t pt-3" style={{ borderColor: '#eef2e3' }}>
                                                    <span className="font-mono text-[11px] tracking-[0.08em] text-moss">MÙA ĐẸP</span>
                                                    <span className="font-mono text-[13px] font-bold text-campfire">{s.season_label}</span>
                                                </div>
                                            </div>
                                        </article>
                                    ))}
                                </div>
                            </section>
                        ))
                    )}
                </div>

                {/* Footer CTA */}
                <div className="px-6 pb-6 sm:px-9">
                    <div className="flex flex-col items-center justify-between gap-3 rounded-[16px] px-5 py-4 sm:flex-row" style={{ background: '#e7eed5', border: '1px solid #d3ddb9' }}>
                        <p className="text-[14px] text-[#3f4a32]">Đã chọn được điểm? Thuê đủ đồ và tụi mình giao tận nơi tại {cities}.</p>
                        <Link href="/thiet-bi" className="flex-none rounded-[12px] bg-grass px-6 py-2.5 text-[14px] font-bold text-white transition hover:bg-pine">Xem thiết bị</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
