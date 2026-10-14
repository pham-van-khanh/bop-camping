import type { PostCardData } from '@/types/post';
import { Link } from '@inertiajs/react';

/** Nền đất cho bài chưa có ảnh bìa — cùng tông thẻ sản phẩm/combo. */
const POST_GRAD = 'linear-gradient(150deg,#5c4033,#7f7a2b 55%,#557A2B)';

/** Dòng phụ: ngày đăng · phút đọc (dùng chung thẻ + trang chi tiết). */
export function PostMeta({
    post,
    className = '',
}: {
    post: Pick<
        PostCardData,
        'published_at' | 'published_label' | 'reading_minutes'
    >;
    className?: string;
}) {
    return (
        <div
            className={`flex flex-wrap items-center gap-x-2 font-mono text-[11.5px] tracking-[0.04em] ${className}`}
        >
            {post.published_at && (
                <time dateTime={post.published_at}>{post.published_label}</time>
            )}
            <span aria-hidden="true">·</span>
            <span>{post.reading_minutes} phút đọc</span>
        </div>
    );
}

/** Thẻ bài Cẩm nang — trang /cam-nang và khối "Bài liên quan". */
export default function PostCard({
    post,
    headingLevel = 'h2',
}: {
    post: PostCardData;
    headingLevel?: 'h2' | 'h3';
}) {
    const Heading = headingLevel;

    return (
        <Link
            href={`/cam-nang/${post.slug}`}
            className="group flex h-full flex-col overflow-hidden rounded-card border border-cardBorder bg-card transition hover:-translate-y-0.5 hover:shadow-cardhover"
        >
            <div
                className="relative aspect-[16/10] overflow-hidden"
                style={{ background: POST_GRAD }}
            >
                {post.cover ? (
                    <img
                        src={post.cover.url}
                        srcSet={post.cover.srcset ?? undefined}
                        sizes="(min-width: 1024px) 380px, (min-width: 640px) 50vw, 100vw"
                        alt={post.title}
                        loading="lazy"
                        decoding="async"
                        className="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]"
                    />
                ) : (
                    <div className="absolute inset-0 grid place-items-center font-mono text-[12px] font-bold tracking-[0.18em] text-[#f3e6cf]/80">
                        CẨM NANG
                    </div>
                )}
            </div>
            <div className="flex flex-1 flex-col p-4 sm:p-5">
                <PostMeta post={post} className="mb-2 text-campfire" />
                <Heading className="text-[17px] font-extrabold leading-snug tracking-tight text-ink group-hover:text-grass">
                    {post.title}
                </Heading>
                {post.excerpt && (
                    <p className="mt-2 line-clamp-3 text-[14px] leading-[1.6] text-moss">
                        {post.excerpt}
                    </p>
                )}
                <span className="mt-auto pt-4 text-[13px] font-bold text-grass">
                    Đọc tiếp →
                </span>
            </div>
        </Link>
    );
}
