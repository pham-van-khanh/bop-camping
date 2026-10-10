import Breadcrumbs from '@/Components/site/Breadcrumbs';
import ComboCard, { type ComboCardData } from '@/Components/site/ComboCard';
import ProductCard from '@/Components/site/ProductCard';
import SpotCard, { type SpotCardData } from '@/Components/site/SpotCard';
import SiteLayout from '@/Layouts/SiteLayout';
import type { PageProps } from '@/types';
import type { ProductResource } from '@/types/product';
import { Head, Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

type LocationInfo = {
    name: string;
    slug: string;
    area: string | null;
    address: string | null;
    map_url: string | null;
};

interface Props {
    location: LocationInfo;
    intro: string;
    product_count: number;
    categories: { name: string; slug: string; count: number }[];
    products: ProductResource[];
    combos: ComboCardData[];
    spots: SpotCardData[];
    delivery_methods: { value: string; label: string; hint: string }[];
    other_locations: { name: string; slug: string }[];
}

const hourLabel = (h?: number) =>
    typeof h === 'number' ? `${String(h).padStart(2, '0')}:00` : null;

/**
 * Trang khu vực /thue-do-cam-trai/{slug} (bopcamping-y05c) — bắt truy vấn "thuê đồ cắm
 * trại <thành phố>". Mọi nội dung lấy từ dữ liệu thật của cơ sở: hàng có sẵn ở kho, combo
 * gán cho kho, điểm cắm trại gần, cách giao nhận. Không có FAQ vì chưa có FAQ riêng theo
 * khu vực — không bịa.
 */
export default function AreaLanding({
    location,
    intro,
    product_count,
    categories,
    products,
    combos,
    spots,
    delivery_methods,
    other_locations,
}: Props) {
    const { site } = usePage<PageProps>().props;
    const hotlines = [site?.hotline_primary, site?.hotline_secondary].filter(
        Boolean,
    ) as string[];
    const listHref = `/thiet-bi?vi-tri=${location.slug}`;
    const pickup = hourLabel(site?.pickup_hour);
    const ret = hourLabel(site?.return_hour);

    return (
        <>
            <Head title={`Thuê đồ cắm trại tại ${location.name}`} />
            <main className="mx-auto max-w-[1400px] px-5 pb-12 pt-6">
                <Breadcrumbs
                    items={[
                        { label: 'Trang chủ', href: '/' },
                        { label: `Thuê đồ cắm trại tại ${location.name}` },
                    ]}
                />

                {/* Hero: giới thiệu + thẻ thông tin kho */}
                <section className="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start">
                    <div className="min-w-0">
                        <div className="mb-2 font-mono text-[12px] tracking-[0.1em] text-campfire">
                            KHO ĐỒ CẮM TRẠI ·{' '}
                            {(location.area ?? location.name).toUpperCase()}
                        </div>
                        <h1
                            className="mb-3 font-extrabold tracking-tight text-ink"
                            style={{ fontSize: 'clamp(26px,3.6vw,40px)' }}
                        >
                            Thuê đồ cắm trại tại {location.name}
                        </h1>
                        <p className="max-w-[720px] whitespace-pre-line text-[16px] leading-[1.7] text-[#3f4a32]">
                            {intro}
                        </p>
                        <div className="mt-5 flex flex-wrap gap-3">
                            <Link
                                href={listHref}
                                className="inline-grid h-12 place-items-center rounded-control bg-grass px-6 text-[15px] font-bold text-white shadow-btn transition hover:bg-pine"
                            >
                                {product_count > 0
                                    ? `Xem ${product_count} món có sẵn tại ${location.name}`
                                    : `Xem đồ thuê tại ${location.name}`}
                            </Link>
                            {combos.length > 0 && (
                                <Link
                                    href={`/combos?vi-tri=${location.slug}`}
                                    className="inline-grid h-12 place-items-center rounded-control border border-cardBorder bg-white px-6 text-[15px] font-semibold text-pine transition hover:border-grass"
                                >
                                    Combo thuê trọn bộ
                                </Link>
                            )}
                        </div>
                    </div>

                    <aside className="rounded-[18px] border border-cardBorder bg-card p-5">
                        <h2 className="mb-3 text-[16px] font-extrabold text-pine">
                            Kho {location.name}
                        </h2>
                        <dl className="space-y-3 text-[14px]">
                            <InfoRow label="Địa chỉ">
                                {location.address ??
                                    `${location.name}${location.area ? ` – ${location.area}` : ''} · gọi hotline để được chỉ đường`}
                            </InfoRow>
                            {site?.working_hours && (
                                <InfoRow label="Giờ làm việc">
                                    {site.working_hours}
                                </InfoRow>
                            )}
                            {hotlines.length > 0 && (
                                <InfoRow label="Hotline">
                                    {hotlines.map((h) => (
                                        <a
                                            key={h}
                                            href={`tel:${h.replace(/\s/g, '')}`}
                                            className="mr-3 inline-block font-mono font-bold text-grass hover:text-pine"
                                        >
                                            {h}
                                        </a>
                                    ))}
                                </InfoRow>
                            )}
                        </dl>
                        <div className="mt-4 flex flex-wrap gap-2.5">
                            {location.map_url && (
                                <a
                                    href={location.map_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-control bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine"
                                >
                                    Mở bản đồ
                                </a>
                            )}
                            {site?.zalo_oa && (
                                <a
                                    href={site.zalo_oa}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="rounded-control border border-cardBorder bg-white px-4 py-2.5 text-[13.5px] font-semibold text-pine transition hover:border-grass"
                                >
                                    Nhắn Zalo
                                </a>
                            )}
                        </div>
                    </aside>
                </section>

                {/* Danh mục có ở kho này */}
                {categories.length > 0 && (
                    <section className="mt-9">
                        <h2 className="mb-3 text-[20px] font-extrabold tracking-tight text-ink">
                            Có gì cho thuê ở {location.name}
                        </h2>
                        <div className="flex flex-wrap gap-2.5">
                            {categories.map((c) => (
                                <Link
                                    key={c.slug}
                                    href={`/thiet-bi?cat=${c.slug}&vi-tri=${location.slug}`}
                                    className="rounded-pill border border-cardBorder bg-white px-4 py-2 text-[14px] font-semibold text-pine transition hover:border-grass"
                                >
                                    {c.name}{' '}
                                    <span className="font-mono text-[12px] text-moss">
                                        {c.count}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}

                {products.length > 0 && (
                    <section className="mt-9">
                        <SectionHead
                            title={`Đồ cắm trại sẵn kho ${location.name}`}
                            sub="Giá thuê theo ngày. Chọn ngày ở trang sản phẩm để xem còn hàng trong khoảng bạn đi."
                            href={listHref}
                            linkLabel="Xem tất cả"
                        />
                        <div
                            className="grid gap-[18px]"
                            style={{
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(min(100%, 240px), 1fr))',
                            }}
                        >
                            {products.map((p, i) => (
                                <ProductCard key={p.id} p={p} index={i} />
                            ))}
                        </div>
                    </section>
                )}

                {combos.length > 0 && (
                    <section className="mt-10">
                        <SectionHead
                            title="Combo thuê trọn bộ"
                            sub="Gói sẵn đồ cho cả chuyến, rẻ hơn thuê lẻ từng món."
                            href={`/combos?vi-tri=${location.slug}`}
                            linkLabel="Tất cả combo"
                        />
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
                    </section>
                )}

                {/* Giao nhận — từ Order::deliveryMethodOptions() + giờ giao/thu của shop */}
                <section className="mt-10 rounded-[18px] border border-[#d3ddb9] bg-[#eef2e3] p-5 sm:p-7">
                    <h2 className="mb-1 text-[20px] font-extrabold tracking-tight text-ink">
                        Nhận và trả đồ ở {location.name}
                    </h2>
                    <p className="mb-4 text-[14.5px] text-moss">
                        Đặt online, tụi mình gọi xác nhận. Cọc và tiền thuê trả
                        khi nhận đồ (COD).
                    </p>
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        {delivery_methods.map((m) => (
                            <div
                                key={m.value}
                                className="rounded-[14px] border border-cardBorder bg-white p-4"
                            >
                                <div className="text-[15px] font-bold text-pine">
                                    {m.label}
                                </div>
                                <p className="mt-1 text-[14px] leading-[1.55] text-[#3f4a32]">
                                    {m.hint}
                                </p>
                            </div>
                        ))}
                    </div>
                    {(pickup || ret) && (
                        <p className="mt-4 text-[14px] text-[#3f4a32]">
                            Giờ giao đồ mặc định{' '}
                            <b className="font-mono">{pickup}</b>
                            {ret && (
                                <>
                                    , thu đồ <b className="font-mono">{ret}</b>
                                </>
                            )}{' '}
                            — đổi giờ được khi gọi xác nhận.
                        </p>
                    )}
                    <Link
                        href="/chinh-sach-giao-nhan"
                        className="mt-3 inline-block text-[14px] font-semibold text-grass hover:text-pine"
                    >
                        Xem chính sách giao nhận →
                    </Link>
                </section>

                {spots.length > 0 && (
                    <section className="mt-10">
                        <SectionHead
                            title={`Điểm cắm trại gần ${location.name}`}
                            sub="Thuê đồ ở kho rồi lên đường — mỗi điểm có mùa đẹp và gợi ý đồ cần mang."
                            href="/diem-cam-trai"
                            linkLabel="Tất cả điểm cắm trại"
                        />
                        <div
                            className="grid gap-[18px]"
                            style={{
                                gridTemplateColumns:
                                    'repeat(auto-fill, minmax(min(100%, 260px), 1fr))',
                            }}
                        >
                            {spots.map((s) => (
                                <SpotCard key={s.id} s={s} />
                            ))}
                        </div>
                    </section>
                )}

                {other_locations.length > 0 && (
                    <section className="mt-10 border-t border-cardBorder pt-6">
                        <h2 className="mb-2 text-[15px] font-bold text-pine">
                            Khu vực khác
                        </h2>
                        <div className="flex flex-wrap gap-x-5 gap-y-2">
                            {other_locations.map((l) => (
                                <Link
                                    key={l.slug}
                                    href={`/thue-do-cam-trai/${l.slug}`}
                                    className="text-[14.5px] font-semibold text-grass hover:text-pine"
                                >
                                    Thuê đồ cắm trại tại {l.name} →
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </>
    );
}

function InfoRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="font-mono text-[11px] tracking-[0.08em] text-moss">
                {label.toUpperCase()}
            </dt>
            <dd className="mt-0.5 break-words text-ink">{children}</dd>
        </div>
    );
}

function SectionHead({
    title,
    sub,
    href,
    linkLabel,
}: {
    title: string;
    sub: string;
    href: string;
    linkLabel: string;
}) {
    return (
        <div className="mb-4 flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
            <div className="min-w-0">
                <h2 className="text-[20px] font-extrabold tracking-tight text-ink">
                    {title}
                </h2>
                <p className="mt-0.5 text-[14px] text-moss">{sub}</p>
            </div>
            <Link
                href={href}
                className="text-[14px] font-semibold text-grass hover:text-pine"
            >
                {linkLabel} →
            </Link>
        </div>
    );
}

AreaLanding.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
