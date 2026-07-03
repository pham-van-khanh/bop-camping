import { Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
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

/** Bỏ dấu + thường hoá để tìm theo tỉnh không cần gõ dấu. */
const norm = (s: string) =>
    s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd');

/**
 * Modal Cẩm nang cắm trại — điểm gom theo tỉnh/thành, có tìm theo tỉnh.
 * Bấm 1 thẻ → mở chi tiết đầy đủ (ảnh/video + link bản đồ + mọi thông tin).
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
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<GuideSpot | null>(null);

    const filtered = useMemo(() => {
        const q = norm(query.trim());
        if (!q) return provinces;
        return provinces.filter((g) => norm(g.province).includes(q) || g.spots.some((s) => norm(s.name).includes(q)));
    }, [provinces, query]);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (e.key !== 'Escape') return;
            if (selected) setSelected(null);
            else onClose();
        };
        window.addEventListener('keydown', onKey);
        document.body.style.overflow = 'hidden';
        return () => {
            window.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        };
    }, [onClose, selected]);

    return (
        <>
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

                    {/* Tìm theo tỉnh */}
                    <div className="relative mt-4 max-w-[420px]">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-moss"><circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="1.8" /><path d="m20 20-3-3" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" /></svg>
                        <input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Bạn muốn cắm trại ở tỉnh nào?"
                            className="h-11 w-full rounded-[12px] bg-white pl-10 pr-10 text-[14px] text-ink outline-none"
                            style={{ border: '1px solid rgba(207,224,168,.4)' }}
                        />
                        {query && (
                            <button onClick={() => setQuery('')} aria-label="Xoá tìm" className="absolute right-3 top-1/2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded-full text-moss hover:bg-[#eef2e3]">×</button>
                        )}
                    </div>

                    <button onClick={onClose} aria-label="Đóng" className="absolute right-5 top-5 grid h-9 w-9 place-items-center rounded-full text-white/90 transition hover:bg-white/15" style={{ background: 'rgba(255,255,255,.12)' }}>×</button>
                </div>

                {/* Body */}
                <div className="px-6 py-6 sm:px-9">
                    {filtered.length === 0 ? (
                        <p className="py-12 text-center text-[14px] text-moss">
                            {provinces.length === 0 ? 'Chưa có điểm cắm trại nào.' : `Không tìm thấy tỉnh nào khớp "${query}".`}
                        </p>
                    ) : (
                        filtered.map((g) => (
                            <section key={g.province} className="mb-8 last:mb-2">
                                <div className="mb-3.5 flex items-center gap-2.5">
                                    <h3 className="text-[20px] font-extrabold tracking-tight text-ink">{g.province}</h3>
                                    <span className="rounded-full bg-[#e7eed5] px-2.5 py-1 font-mono text-[11.5px] text-[#3a5a1f]">{g.spots.length} điểm</span>
                                </div>
                                <div className="grid gap-4" style={{ gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))' }}>
                                    {g.spots.map((s) => (
                                        <button
                                            key={s.id}
                                            onClick={() => setSelected(s)}
                                            className="group overflow-hidden rounded-[16px] bg-card text-left transition hover:-translate-y-0.5 hover:shadow-lg"
                                            style={{ border: '1px solid #E3E8D6' }}
                                        >
                                            <div className="relative h-[120px] bg-cover bg-center" style={s.media[0]?.type === 'image' ? { backgroundImage: `url(${s.media[0].url})` } : { background: terrainGradient(s.terrain_tag) }}>
                                                <span className="absolute bottom-3 left-3 rounded-pill px-3 py-1 font-mono text-[12px] text-white" style={{ background: 'rgba(24,35,15,.5)' }}>{s.terrain_tag}</span>
                                                {s.media.length > 0 && (
                                                    <span className="absolute right-3 top-3 flex items-center gap-1 rounded-pill px-2.5 py-1 font-mono text-[11px] text-white" style={{ background: 'rgba(24,35,15,.5)' }}>
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" strokeWidth="1.8" /><path d="m3 16 5-4 4 3 4-4 5 4" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" /></svg>
                                                        {s.media.length}
                                                    </span>
                                                )}
                                            </div>
                                            <div className="p-[18px]">
                                                <h4 className="text-[17px] font-bold text-ink">{s.name}</h4>
                                                <div className="mt-1 flex items-center gap-1.5 font-mono text-[12.5px] text-grass">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" /><circle cx="12" cy="10" r="2.4" stroke="currentColor" strokeWidth="1.7" /></svg>
                                                    {s.province}{s.district ? ` · ${s.district}` : ''}
                                                </div>
                                                {s.description && <p className="mt-2.5 line-clamp-2 text-[14px] leading-[1.55] text-[#3f4a32]">{s.description}</p>}
                                                <div className="mt-3.5 flex items-center justify-between border-t pt-3" style={{ borderColor: '#eef2e3' }}>
                                                    <span className="font-mono text-[11px] tracking-[0.08em] text-moss">MÙA ĐẸP</span>
                                                    <span className="font-mono text-[13px] font-bold text-campfire">{s.season_label}</span>
                                                </div>
                                            </div>
                                        </button>
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

        {/* Chi tiết tách khỏi lớp blur của modal để 'fixed' bám viewport (không bị neo theo vùng cuộn) */}
        {selected && <SpotDetail spot={selected} onBack={() => setSelected(null)} />}
        </>
    );
}

/** Chi tiết 1 điểm: gallery ảnh/video + mọi thông tin + link bản đồ. */
function SpotDetail({ spot, onBack }: { spot: GuideSpot; onBack: () => void }) {
    const [idx, setIdx] = useState(0);
    const media = spot.media;
    const cur = media[idx];

    return (
        <div onClick={onBack} className="fixed inset-0 z-[97] flex items-start justify-center overflow-y-auto p-4 sm:p-6" style={{ background: 'rgba(15,24,8,.72)', backdropFilter: 'blur(5px)' }}>
            <div onClick={(e) => e.stopPropagation()} className="my-2 w-full max-w-[760px] overflow-hidden rounded-[20px] bg-card shadow-2xl">
                {/* Gallery / cover */}
                <div className="relative" style={{ aspectRatio: '16/9', background: terrainGradient(spot.terrain_tag) }}>
                    {cur && (cur.type === 'video'
                        ? <video key={cur.url} src={cur.url} controls autoPlay muted loop playsInline className="h-full w-full object-cover" />
                        : <img src={cur.url} alt={spot.name} className="h-full w-full object-cover" />)}

                    {media.length > 1 && (
                        <>
                            <button onClick={() => setIdx((i) => (i - 1 + media.length) % media.length)} aria-label="Trước" className="absolute left-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white">‹</button>
                            <button onClick={() => setIdx((i) => (i + 1) % media.length)} aria-label="Sau" className="absolute right-3 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white">›</button>
                            <div className="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/45 px-2.5 py-1 font-mono text-[11px] text-white">{idx + 1}/{media.length}</div>
                        </>
                    )}
                    <span className="pointer-events-none absolute left-3 top-3 rounded-pill px-3 py-1 font-mono text-[12px] text-white" style={{ background: 'rgba(24,35,15,.5)' }}>{spot.terrain_tag}</span>
                    <button onClick={onBack} aria-label="Đóng" className="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-black/45 text-white">×</button>
                </div>

                {/* Thumbnails */}
                {media.length > 1 && (
                    <div className="flex gap-2 overflow-x-auto px-5 pt-3">
                        {media.map((m, i) => (
                            <button key={i} onClick={() => setIdx(i)} className="relative h-12 w-16 flex-none overflow-hidden rounded-[8px]" style={{ outline: i === idx ? '2px solid #557A2B' : '1px solid #E3E8D6' }}>
                                {m.type === 'video'
                                    ? <><video src={m.url} className="h-full w-full object-cover" muted /><span className="absolute inset-0 grid place-items-center bg-black/25 text-[10px] text-white">▶</span></>
                                    : <img src={m.url} alt="" className="h-full w-full object-cover" />}
                            </button>
                        ))}
                    </div>
                )}

                {/* Thông tin */}
                <div className="p-6">
                    <h3 className="text-[22px] font-extrabold tracking-tight text-ink">{spot.name}</h3>
                    <div className="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-[13px] text-grass">
                        <span className="flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" /><circle cx="12" cy="10" r="2.4" stroke="currentColor" strokeWidth="1.7" /></svg>
                            {spot.province}{spot.district ? ` · ${spot.district}` : ''}
                        </span>
                        <span className="text-campfire">Mùa đẹp: {spot.season_label}</span>
                        {spot.nearest_name && <span className="text-moss">Gần {spot.nearest_name}</span>}
                    </div>
                    {spot.description && <p className="mt-3.5 text-[15px] leading-[1.65] text-[#3f4a32]">{spot.description}</p>}

                    <div className="mt-5 flex flex-wrap gap-3">
                        {spot.map_url && (
                            <a href={spot.map_url} target="_blank" rel="noreferrer" className="flex items-center gap-2 rounded-[12px] bg-grass px-5 py-2.5 text-[14px] font-bold text-white transition hover:bg-pine">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z" stroke="currentColor" strokeWidth="1.7" /><circle cx="12" cy="10" r="2.4" stroke="currentColor" strokeWidth="1.7" /></svg>
                                Mở bản đồ
                            </a>
                        )}
                        <Link href="/thiet-bi" className="rounded-[12px] border border-cardBorder px-5 py-2.5 text-[14px] font-semibold text-pine transition hover:border-grass">Xem thiết bị thuê</Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
