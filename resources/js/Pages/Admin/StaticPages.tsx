import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

type PageRow = { id: number; slug: string; title: string; updated_at: string | null };

/** Danh sách trang nội dung (Epic 4) — hiện có trang giới thiệu, sau này thêm chính sách... */
export default function AdminStaticPages({ pages }: { pages: PageRow[] }) {
    return (
        <div className="p-6">
            <Head title="Trang nội dung" />
            <div className="mb-5">
                <h1 className="text-[22px] font-extrabold tracking-tight text-pine">Trang nội dung</h1>
                <p className="mt-1 text-[13px] text-moss">Các trang tĩnh của website — sửa tiêu đề, ảnh bìa và nội dung.</p>
            </div>

            <div className="overflow-hidden rounded-[14px] border border-cardBorder bg-white">
                {pages.map((p, i) => (
                    <div key={p.id} className={`flex flex-wrap items-center gap-3 px-4 py-3.5 ${i > 0 ? 'border-t border-[#f1f4ea]' : ''}`}>
                        <div className="min-w-[180px] flex-1">
                            <div className="text-[14px] font-bold text-pine">{p.title}</div>
                            <div className="font-mono text-[11.5px] text-moss">/{p.slug}</div>
                        </div>
                        {p.updated_at && <span className="font-mono text-[11.5px] text-moss">sửa {p.updated_at}</span>}
                        <a
                            href={`/${p.slug}`}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                        >
                            Xem ↗
                        </a>
                        <Link
                            href={route('admin.pages.edit', p.id)}
                            className="rounded-[8px] bg-grass px-3.5 py-1.5 text-[12px] font-bold text-white transition hover:bg-pine"
                        >
                            Sửa nội dung
                        </Link>
                    </div>
                ))}
            </div>
        </div>
    );
}

AdminStaticPages.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
