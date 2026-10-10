import PostCard, { PostMeta } from '@/Components/site/PostCard';
import SiteLayout from '@/Layouts/SiteLayout';
import { money } from '@/lib/format';
import type { PostCardData, PostDetailData, PostGearItem } from '@/types/post';
import { Link } from '@inertiajs/react';
import { ReactNode, useMemo } from 'react';

/**
 * /cam-nang/{slug} — một bài Cẩm nang (bopcamping-ennp).
 *
 * `post.content` là HTML đã sanitize ở server (Post model → EditorHtml::clean) nên
 * render thẳng được. Cuối bài là khối "Đồ dùng trong bài" — đây là chỗ chuyển người
 * đọc sang trang thuê, lý do tồn tại của cả mục Cẩm nang.
 */
export default function PostDetail({
    post,
    products,
    combos,
    related,
}: {
    post: PostDetailData;
    products: PostGearItem[];
    combos: PostGearItem[];
    related: PostCardData[];
}) {
    // Ảnh trong thân bài nằm dưới màn hình đầu → tải lười cho nhẹ trang.
    const html = useMemo(
        () =>
            (post.content ?? '').replace(
                /<img(?![^>]*\bloading=)/g,
                '<img loading="lazy" decoding="async"',
            ),
        [post.content],
    );
    const gear = [...combos, ...products];

    return (
        <>
            <article>
                <header className="mx-auto max-w-[760px] px-4 pt-8 sm:px-5 sm:pt-12">
                    <nav
                        aria-label="Breadcrumb"
                        className="mb-4 text-[12.5px] text-moss"
                    >
                        <Link href="/" className="hover:text-grass">
                            Trang chủ
                        </Link>
                        <span className="mx-1.5" aria-hidden="true">
                            /
                        </span>
                        <Link href="/cam-nang" className="hover:text-grass">
                            Cẩm nang
                        </Link>
                    </nav>
                    <div className="mb-2 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">
                        CẨM NANG
                    </div>
                    <h1
                        className="font-extrabold leading-[1.18] tracking-tight text-ink"
                        style={{ fontSize: 'clamp(26px,4.2vw,40px)' }}
                    >
                        {post.title}
                    </h1>
                    {post.excerpt && (
                        <p className="mt-4 text-[16.5px] leading-[1.65] text-moss">
                            {post.excerpt}
                        </p>
                    )}
                    <div className="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1 border-y border-cardBorder py-3 text-[13px] text-moss">
                        <span className="font-semibold text-pine">
                            {post.author}
                        </span>
                        <PostMeta post={post} />
                    </div>
                </header>

                {post.cover && (
                    <figure className="mx-auto mt-6 max-w-[1040px] px-0 sm:px-5">
                        <img
                            src={post.cover.url}
                            srcSet={post.cover.srcset ?? undefined}
                            sizes="(min-width: 1080px) 1000px, 100vw"
                            alt={post.title}
                            fetchPriority="high"
                            className="aspect-[16/9] w-full object-cover sm:rounded-card"
                        />
                    </figure>
                )}

                <div className="mx-auto max-w-[760px] px-4 pb-4 pt-8 sm:px-5">
                    <div
                        className="editor-content article-content"
                        dangerouslySetInnerHTML={{ __html: html }}
                    />
                </div>
            </article>

            <aside className="mx-auto max-w-[1040px] px-4 pb-6 pt-6 sm:px-5">
                {gear.length > 0 ? (
                    <section
                        aria-labelledby="gear-heading"
                        className="rounded-[20px] p-5 sm:p-7"
                        style={{ background: '#eef2e3' }}
                    >
                        <div className="mb-1 font-mono text-[11.5px] font-bold tracking-[0.14em] text-campfire">
                            KHÔNG CẦN MUA — THUÊ THEO NGÀY
                        </div>
                        <h2
                            id="gear-heading"
                            className="text-[20px] font-extrabold tracking-tight text-ink"
                        >
                            Đồ dùng trong bài
                        </h2>
                        <ul className="mt-5 grid gap-3 md:grid-cols-2">
                            {gear.map((g) => (
                                <li key={g.url}>
                                    <GearCard item={g} />
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : (
                    <section
                        className="flex flex-col items-center gap-3 rounded-[20px] px-6 py-9 text-center"
                        style={{ background: '#eef2e3' }}
                    >
                        <h2 className="text-[20px] font-extrabold tracking-tight text-ink">
                            Sẵn sàng cho chuyến đi?
                        </h2>
                        <p className="max-w-[440px] text-[14px] leading-[1.6] text-moss">
                            Thuê đúng món, đúng ngày — đồ sạch, đủ phụ kiện, trả
                            tiền khi nhận.
                        </p>
                        <Link
                            href="/thiet-bi"
                            className="mt-1 rounded-control bg-grass px-7 py-3.5 text-[14.5px] font-bold text-white transition hover:bg-pine"
                        >
                            Xem đồ cho thuê →
                        </Link>
                    </section>
                )}
            </aside>

            {related.length > 0 && (
                <section
                    aria-labelledby="related-heading"
                    className="mx-auto max-w-[1200px] px-4 pb-16 pt-8 sm:px-5"
                >
                    <h2
                        id="related-heading"
                        className="mb-5 text-[22px] font-extrabold tracking-tight text-ink"
                    >
                        Đọc thêm
                    </h2>
                    <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {related.map((p) => (
                            <li key={p.id}>
                                <PostCard post={p} headingLevel="h3" />
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </>
    );
}

function GearCard({ item }: { item: PostGearItem }) {
    return (
        <div className="flex h-full items-center gap-3 rounded-[14px] border border-cardBorder bg-card p-3">
            <Link
                href={item.url}
                className="relative h-[72px] w-[72px] flex-none overflow-hidden rounded-[10px]"
                style={{
                    background: 'linear-gradient(150deg,#4a6741,#7a9b6b)',
                }}
                tabIndex={-1}
                aria-hidden="true"
            >
                {item.image && (
                    <img
                        src={item.image}
                        srcSet={item.srcset ?? undefined}
                        sizes="72px"
                        alt=""
                        loading="lazy"
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                )}
            </Link>
            {/* Nút nằm dưới tên (không chen ngang) để tên dài không bị cắt trên mobile. */}
            <div className="min-w-0 flex-1">
                <Link
                    href={item.url}
                    className="line-clamp-2 text-[14px] font-bold leading-snug text-ink hover:text-grass"
                >
                    {item.name}
                </Link>
                <div className="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                    <span className="text-[13px] text-moss">
                        <span className="font-bold text-pine">
                            {money(item.price)}
                        </span>
                        /ngày
                    </span>
                    <Link
                        href={item.url}
                        className="rounded-control bg-grass px-3.5 py-1.5 text-[13px] font-bold text-white transition hover:bg-pine"
                    >
                        Thuê ngay
                    </Link>
                </div>
            </div>
        </div>
    );
}

PostDetail.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
