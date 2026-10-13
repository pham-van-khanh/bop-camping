import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';

type PostRow = {
    id: number;
    title: string;
    slug: string;
    status: 'draft' | 'published';
    is_public: boolean;
    published_at: string | null;
    updated_at: string | null;
    cover_url: string | null;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

/** Nhãn trạng thái: nháp / hẹn giờ (đã đăng nhưng chưa tới giờ) / đang hiện. */
function StatusPill({ row }: { row: PostRow }) {
    if (row.status === 'draft') {
        return (
            <span className="whitespace-nowrap rounded-pill bg-[#efe9dc] px-2.5 py-0.5 text-[11.5px] font-bold text-[#7a6a4a]">
                Bản nháp
            </span>
        );
    }
    if (!row.is_public) {
        return (
            <span className="whitespace-nowrap rounded-pill bg-[#fbecd9] px-2.5 py-0.5 text-[11.5px] font-bold text-campfire">
                Hẹn giờ
            </span>
        );
    }
    return (
        <span className="whitespace-nowrap rounded-pill bg-[#dcebc4] px-2.5 py-0.5 text-[11.5px] font-bold text-[#3a5a1f]">
            Đang hiện
        </span>
    );
}

/** Admin "Cẩm nang" (bopcamping-ennp): danh sách bài viết. */
export default function AdminPosts({ posts }: { posts: Paginator<PostRow> }) {
    const { flash } = usePage<PageProps>().props;
    const [toastMsg, setToastMsg] = useState('');
    const [deleteId, setDeleteId] = useState<number | null>(null);

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.posts.destroy', deleteId), {
            preserveScroll: true,
            onFinish: () => setDeleteId(null),
        });
    };

    return (
        <>
            <Head title="Admin · Cẩm nang" />

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">
                    ✓ {toastMsg}
                </div>
            )}

            <div className="p-6">
                <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">
                            Cẩm nang
                        </h1>
                        <p className="mt-0.5 text-[13px] text-moss">
                            <span className="font-mono">{posts.total}</span> bài
                            viết · chỉ bài <b>Đang hiện</b> mới có trên trang
                            khách và sitemap
                        </p>
                    </div>
                    <Link
                        href={route('admin.posts.create')}
                        className="rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine"
                    >
                        + Viết bài mới
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-[14px] border border-cardBorder bg-white">
                    <table className="w-full min-w-[720px] text-[13.5px]">
                        <thead>
                            <tr className="border-b border-cardBorder bg-[#f8faf3] text-left text-[12px] font-bold uppercase tracking-wide text-moss">
                                <th className="px-4 py-3">Bài viết</th>
                                <th className="px-4 py-3">Trạng thái</th>
                                <th className="px-4 py-3">Ngày đăng</th>
                                <th className="px-4 py-3">Sửa lần cuối</th>
                                <th className="px-4 py-3 text-right">
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {posts.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-moss"
                                    >
                                        Chưa có bài viết nào.
                                    </td>
                                </tr>
                            )}
                            {posts.data.map((p) => (
                                <tr
                                    key={p.id}
                                    className="border-b border-cardBorder last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="h-11 w-16 flex-none overflow-hidden rounded-[8px] bg-[#e9e3d4]">
                                                {p.cover_url && (
                                                    <img
                                                        src={p.cover_url}
                                                        alt=""
                                                        className="h-full w-full object-cover"
                                                    />
                                                )}
                                            </div>
                                            <div className="min-w-0">
                                                <Link
                                                    href={route(
                                                        'admin.posts.edit',
                                                        p.id,
                                                    )}
                                                    className="font-semibold text-pine hover:text-grass"
                                                >
                                                    {p.title}
                                                </Link>
                                                <div className="truncate font-mono text-[11.5px] text-moss">
                                                    /cam-nang/{p.slug}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusPill row={p} />
                                    </td>
                                    <td className="px-4 py-3 font-mono text-[12.5px] text-moss">
                                        {p.published_at ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 font-mono text-[12.5px] text-moss">
                                        {p.updated_at}
                                    </td>
                                    <td className="whitespace-nowrap px-4 py-3 text-right">
                                        {p.is_public && (
                                            <a
                                                href={`/cam-nang/${p.slug}`}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="mr-3 text-[12.5px] font-semibold text-moss hover:text-grass"
                                            >
                                                Xem ↗
                                            </a>
                                        )}
                                        <Link
                                            href={route(
                                                'admin.posts.edit',
                                                p.id,
                                            )}
                                            className="mr-3 text-[12.5px] font-semibold text-grass hover:underline"
                                        >
                                            Sửa
                                        </Link>
                                        <button
                                            type="button"
                                            onClick={() => setDeleteId(p.id)}
                                            className="text-[12.5px] font-semibold text-[#b3493a] hover:underline"
                                        >
                                            Xoá
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {posts.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-end gap-2 text-[13px]">
                        <button
                            disabled={!posts.prev_page_url}
                            onClick={() =>
                                posts.prev_page_url &&
                                router.get(posts.prev_page_url)
                            }
                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine disabled:opacity-40"
                        >
                            ← Trước
                        </button>
                        <span className="font-mono text-moss">
                            {posts.current_page}/{posts.last_page}
                        </span>
                        <button
                            disabled={!posts.next_page_url}
                            onClick={() =>
                                posts.next_page_url &&
                                router.get(posts.next_page_url)
                            }
                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine disabled:opacity-40"
                        >
                            Sau →
                        </button>
                    </div>
                )}
            </div>

            {deleteId !== null && (
                <div
                    className="fixed inset-0 z-[90] grid place-items-center bg-black/40 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-post-title"
                >
                    <div className="w-full max-w-[380px] rounded-[16px] bg-white p-6 shadow-xl">
                        <h2
                            id="delete-post-title"
                            className="text-[16px] font-extrabold text-pine"
                        >
                            Xoá bài viết này?
                        </h2>
                        <p className="mt-1.5 text-[13.5px] text-moss">
                            Bài và ảnh bìa sẽ bị xoá vĩnh viễn. Nếu chỉ muốn ẩn,
                            hãy chuyển về Bản nháp.
                        </p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => setDeleteId(null)}
                                className="rounded-[10px] border border-cardBorder px-4 py-2 text-[13px] font-semibold text-pine"
                            >
                                Huỷ
                            </button>
                            <button
                                type="button"
                                onClick={doDelete}
                                className="rounded-[10px] bg-[#b3493a] px-4 py-2 text-[13px] font-bold text-white"
                            >
                                Xoá bài
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

AdminPosts.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
