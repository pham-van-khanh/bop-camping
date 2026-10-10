import PostCard from '@/Components/site/PostCard';
import SiteLayout from '@/Layouts/SiteLayout';
import type { PostPaginator } from '@/types/post';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

/**
 * /cam-nang — danh sách bài Cẩm nang (bopcamping-ennp), mới nhất trước.
 * Thẻ <title>/meta cho crawler do server dựng (SeoService); <Head> ở đây chỉ để
 * Inertia đổi tiêu đề tab khi điều hướng phía client (app.tsx tự nối tên app).
 */
export default function Posts({ posts }: { posts: PostPaginator }) {
    return (
        <>
            <Head title="Cẩm nang cắm trại" />

            <section className="mx-auto max-w-[1200px] px-4 pb-6 pt-10 sm:px-5 sm:pt-14">
                <nav
                    aria-label="Breadcrumb"
                    className="mb-3 text-[12.5px] text-moss"
                >
                    <Link href="/" className="hover:text-grass">
                        Trang chủ
                    </Link>
                    <span className="mx-1.5" aria-hidden="true">
                        /
                    </span>
                    <span className="text-pine">Cẩm nang</span>
                </nav>
                <div className="mb-1 font-mono text-[12px] font-bold tracking-[0.14em] text-campfire">
                    CẨM NANG
                </div>
                <h1
                    className="max-w-[760px] font-extrabold leading-[1.15] tracking-tight text-ink"
                    style={{ fontSize: 'clamp(26px,4vw,40px)' }}
                >
                    Cẩm nang cắm trại cho người mới
                </h1>
                <p className="mt-3 max-w-[640px] text-[15px] leading-[1.65] text-moss">
                    Checklist đồ cần mang, cách chọn lều, mẹo cắm trại mùa mưa —
                    những gì tụi mình rút ra sau nhiều chuyến đi, viết gọn để
                    bạn chuẩn bị nhanh hơn.
                </p>
            </section>

            <main className="mx-auto max-w-[1200px] px-4 pb-16 sm:px-5">
                {posts.data.length === 0 ? (
                    <div className="rounded-card border border-dashed border-cardBorder bg-card px-6 py-14 text-center">
                        <p className="text-[15px] font-semibold text-pine">
                            Bài viết đang được chuẩn bị.
                        </p>
                        <p className="mt-1 text-[14px] text-moss">
                            Trong lúc chờ, bạn có thể xem đồ cho thuê nhé.
                        </p>
                        <Link
                            href="/thiet-bi"
                            className="mt-5 inline-block rounded-control bg-grass px-6 py-3 text-[14px] font-bold text-white transition hover:bg-pine"
                        >
                            Xem đồ cho thuê →
                        </Link>
                    </div>
                ) : (
                    <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((p) => (
                            <li key={p.id}>
                                <PostCard post={p} />
                            </li>
                        ))}
                    </ul>
                )}

                {posts.last_page > 1 && (
                    <nav
                        aria-label="Phân trang"
                        className="mt-10 flex items-center justify-center gap-3"
                    >
                        {posts.prev_page_url ? (
                            <Link
                                href={posts.prev_page_url}
                                rel="prev"
                                className="rounded-control border border-cardBorder bg-card px-4 py-2 text-[13.5px] font-semibold text-pine transition hover:border-grass"
                            >
                                ← Mới hơn
                            </Link>
                        ) : (
                            <span className="px-4 py-2 text-[13.5px] text-moss/50">
                                ← Mới hơn
                            </span>
                        )}
                        <span className="font-mono text-[13px] text-moss">
                            {posts.current_page} / {posts.last_page}
                        </span>
                        {posts.next_page_url ? (
                            <Link
                                href={posts.next_page_url}
                                rel="next"
                                className="rounded-control border border-cardBorder bg-card px-4 py-2 text-[13.5px] font-semibold text-pine transition hover:border-grass"
                            >
                                Cũ hơn →
                            </Link>
                        ) : (
                            <span className="px-4 py-2 text-[13.5px] text-moss/50">
                                Cũ hơn →
                            </span>
                        )}
                    </nav>
                )}
            </main>
        </>
    );
}

Posts.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
