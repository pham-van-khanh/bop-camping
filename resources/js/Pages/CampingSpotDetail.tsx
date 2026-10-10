import Breadcrumbs from '@/Components/site/Breadcrumbs';
import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import SpotCard, { type SpotCardData } from '@/Components/site/SpotCard';
import SiteLayout from '@/Layouts/SiteLayout';
import { terrainGradient } from '@/lib/terrain';
import { Link } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

type SpotMedia = {
    type: 'image' | 'video';
    url: string;
    srcset: string | null;
};

interface Props {
    spot: {
        name: string;
        slug: string;
        province: string;
        district: string | null;
        terrain_tag: string;
        description: string | null;
        season_label: string;
        map_url: string | null;
        media: SpotMedia[];
    };
    /** Cơ sở gần nhất (đang mở) — null khi điểm ở xa mọi kho. */
    store: { name: string; slug: string; area: string | null } | null;
    combos: ComboCardData[];
    nearby: SpotCardData[];
    locations: { name: string; slug: string }[];
}

/**
 * Trang một điểm cắm trại /diem-cam-trai/{slug} (bopcamping-y05c) — bắt truy vấn
 * "cắm trại ở <địa điểm>". Nội dung từ dữ liệu admin nhập (ảnh, mô tả, mùa đẹp, bản đồ)
 * + combo có sẵn ở kho gần nhất làm "Gợi ý đồ cần mang".
 */
export default function CampingSpotDetail({
    spot,
    store,
    combos,
    nearby,
    locations,
}: Props) {
    const [idx, setIdx] = useState(0);
    const media = spot.media;
    const cur = media[idx];
    const cities = locations.map((l) => l.name).join(' hoặc ');

    return (
        <>
            <main className="mx-auto max-w-[1400px] px-5 pb-12 pt-6">
                <Breadcrumbs
                    items={[
                        { label: 'Trang chủ', href: '/' },
                        { label: 'Điểm cắm trại', href: '/diem-cam-trai' },
                        { label: spot.name },
                    ]}
                />

                <div className="grid grid-cols-1 items-start gap-7 lg:grid-cols-[minmax(0,1fr)_420px]">
                    {/* Gallery */}
                    <div className="min-w-0">
                        <div
                            className="relative overflow-hidden rounded-card"
                            style={{
                                aspectRatio: '16/10',
                                background: terrainGradient(spot.terrain_tag),
                            }}
                        >
                            {cur &&
                                (cur.type === 'video' ? (
                                    <video
                                        key={cur.url}
                                        src={cur.url}
                                        controls
                                        muted
                                        playsInline
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <img
                                        key={cur.url}
                                        src={cur.url}
                                        srcSet={cur.srcset ?? undefined}
                                        sizes="(min-width: 1024px) 900px, 100vw"
                                        alt={`Cắm trại ở ${spot.name} – ảnh ${idx + 1}`}
                                        className="h-full w-full object-cover"
                                    />
                                ))}
                            {!cur && (
                                <div className="absolute inset-0 grid place-items-center px-6 text-center font-mono text-[13px] tracking-[0.08em] text-white/85">
                                    {spot.terrain_tag.toUpperCase()} ·{' '}
                                    {spot.province.toUpperCase()}
                                </div>
                            )}
                            {media.length > 1 && (
                                <div className="pointer-events-none absolute bottom-3 right-3 rounded-full bg-black/45 px-2.5 py-1 font-mono text-[11px] text-white">
                                    {idx + 1}/{media.length}
                                </div>
                            )}
                        </div>
                        {media.length > 1 && (
                            <div className="mt-2.5 flex gap-2 overflow-x-auto pb-1">
                                {media.map((m, i) => (
                                    <button
                                        key={m.url}
                                        type="button"
                                        onClick={() => setIdx(i)}
                                        aria-label={`Xem ${m.type === 'video' ? 'video' : 'ảnh'} ${i + 1} của ${spot.name}`}
                                        className="relative h-14 w-20 flex-none overflow-hidden rounded-[10px]"
                                        style={{
                                            outline:
                                                i === idx
                                                    ? '2px solid #557A2B'
                                                    : '1px solid #E3E8D6',
                                        }}
                                    >
                                        {m.type === 'video' ? (
                                            <>
                                                <video
                                                    src={m.url}
                                                    muted
                                                    className="h-full w-full object-cover"
                                                />
                                                <span className="absolute inset-0 grid place-items-center bg-black/25 text-[11px] text-white">
                                                    ▶
                                                </span>
                                            </>
                                        ) : (
                                            <img
                                                src={m.url}
                                                srcSet={m.srcset ?? undefined}
                                                sizes="80px"
                                                alt=""
                                                loading="lazy"
                                                className="h-full w-full object-cover"
                                            />
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Thông tin */}
                    <div className="min-w-0">
                        <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                            ĐIỂM CẮM TRẠI · {spot.terrain_tag.toUpperCase()}
                        </div>
                        <h1
                            className="mb-2 font-extrabold tracking-tight text-ink"
                            style={{ fontSize: 'clamp(26px,3.4vw,38px)' }}
                        >
                            Cắm trại ở {spot.name}
                        </h1>
                        <dl className="mb-4 grid grid-cols-2 gap-3 rounded-[16px] border border-cardBorder bg-card p-4 text-[14px]">
                            <Fact label="Tỉnh / thành">
                                {spot.province}
                                {spot.district ? ` · ${spot.district}` : ''}
                            </Fact>
                            <Fact label="Địa hình">{spot.terrain_tag}</Fact>
                            <Fact label="Mùa đẹp">
                                <span className="font-bold text-campfire">
                                    {spot.season_label}
                                </span>
                            </Fact>
                            <Fact label="Kho gần nhất">
                                {store ? (
                                    <Link
                                        href={`/thue-do-cam-trai/${store.slug}`}
                                        className="font-semibold text-grass hover:text-pine"
                                    >
                                        {store.name}
                                    </Link>
                                ) : (
                                    'Chưa có kho gần'
                                )}
                            </Fact>
                        </dl>
                        {spot.description && (
                            <p className="whitespace-pre-line text-[15.5px] leading-[1.7] text-[#3f4a32]">
                                {spot.description}
                            </p>
                        )}
                        <div className="mt-5 flex flex-wrap gap-3">
                            {spot.map_url && (
                                <a
                                    href={spot.map_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-control bg-grass px-5 py-3 text-[14px] font-bold text-white transition hover:bg-pine"
                                >
                                    Mở bản đồ
                                </a>
                            )}
                            <Link
                                href={
                                    store
                                        ? `/thue-do-cam-trai/${store.slug}`
                                        : '/thiet-bi'
                                }
                                className="rounded-control border border-cardBorder bg-white px-5 py-3 text-[14px] font-semibold text-pine transition hover:border-grass"
                            >
                                {store
                                    ? `Thuê đồ tại ${store.name}`
                                    : 'Xem đồ cho thuê'}
                            </Link>
                        </div>
                    </div>
                </div>

                {/* Gợi ý đồ cần mang = combo có sẵn ở kho gần nhất */}
                <section className="mt-10">
                    <h2 className="text-[20px] font-extrabold tracking-tight text-ink">
                        Gợi ý đồ cần mang
                    </h2>
                    <p className="mb-4 mt-0.5 text-[14px] text-moss">
                        {store
                            ? `Combo trọn bộ có sẵn ở kho ${store.name} — nhận đồ rồi lên đường tới ${spot.name}.`
                            : `Thuê đồ theo ngày ở ${cities || 'kho của tụi mình'} rồi mang theo tới ${spot.name}.`}
                    </p>
                    {combos.length > 0 ? (
                        <div
                            className="grid gap-[18px]"
                            style={{
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(min(100%, 258px), 1fr))',
                            }}
                        >
                            {combos.map((c, i) => (
                                <ComboCard key={c.id} c={c} index={i} />
                            ))}
                        </div>
                    ) : (
                        <Link
                            href="/thiet-bi"
                            className="inline-block text-[14.5px] font-semibold text-grass hover:text-pine"
                        >
                            Xem lều, bếp, túi ngủ cho thuê →
                        </Link>
                    )}
                </section>

                {nearby.length > 0 && (
                    <section className="mt-10">
                        <h2 className="mb-4 text-[20px] font-extrabold tracking-tight text-ink">
                            Điểm cắm trại gần đó
                        </h2>
                        <div
                            className="grid gap-[18px]"
                            style={{
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(min(100%, 260px), 1fr))',
                            }}
                        >
                            {nearby.map((s) => (
                                <SpotCard key={s.id} s={s} />
                            ))}
                        </div>
                    </section>
                )}

                <div className="mt-8">
                    <Link
                        href="/diem-cam-trai"
                        className="text-[14.5px] font-semibold text-grass hover:text-pine"
                    >
                        ← Tất cả điểm cắm trại
                    </Link>
                </div>
            </main>
        </>
    );
}

function Fact({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <dt className="font-mono text-[11px] tracking-[0.08em] text-moss">
                {label.toUpperCase()}
            </dt>
            <dd className="mt-0.5 break-words text-ink">{children}</dd>
        </div>
    );
}

CampingSpotDetail.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
