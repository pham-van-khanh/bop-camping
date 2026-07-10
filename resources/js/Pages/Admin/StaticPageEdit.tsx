import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import RichTextEditor from '@/Components/admin/RichTextEditor';
import type { PageProps } from '@/types';

/**
 * Sửa 1 trang nội dung (Epic 4): tiêu đề + ảnh bìa + nội dung TipTap full-width.
 * Phía khách render nội dung theo bố cục magazine (ảnh/text xen kẽ tự sắp).
 */
export default function AdminStaticPageEdit({
    page,
}: {
    page: { id: number; slug: string; title: string; cover_url: string | null; content: string | null };
}) {
    const { flash } = usePage<PageProps>().props;
    const [toastMsg, setToastMsg] = useState('');

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const form = useForm<{ title: string; cover: File | null; content: string }>({
        title: page.title,
        cover: null,
        content: page.content ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // PHP không nạp $_FILES cho PUT → POST + _method=put để upload ảnh bìa.
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post(route('admin.pages.update', page.id), { forceFormData: true, preserveScroll: true });
    };

    return (
        <>
            <Head title={`Sửa — ${page.title}`} />
            <div className="mx-auto max-w-[880px]">
                <div className="mb-1 text-[12.5px] text-moss">
                    <Link href={route('admin.pages')} className="font-semibold text-grass hover:underline">Trang nội dung</Link>
                    {' / '}
                    <span>/{page.slug}</span>
                </div>
                <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <h1 className="text-[22px] font-extrabold tracking-tight text-pine">Sửa trang nội dung</h1>
                    <a
                        href={`/${page.slug}`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                    >
                        Xem trang khách ↗
                    </a>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                            Tiêu đề <span className="text-[#b3493a]">*</span>
                        </label>
                        <input
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                        />
                        {form.errors.title && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.title}</p>}
                    </div>

                    <div>
                        <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                            Ảnh bìa
                            {page.cover_url && <span className="ml-1 font-normal text-moss">(để trống = giữ ảnh cũ)</span>}
                        </label>
                        {page.cover_url && (
                            <img src={page.cover_url} alt="" className="mb-2 h-24 w-full max-w-[420px] rounded-[10px] border border-cardBorder object-cover" />
                        )}
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={(e) => form.setData('cover', e.target.files?.[0] ?? null)}
                            className="w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] file:mr-3 file:rounded-[7px] file:border-0 file:bg-[#f1f4ea] file:px-3 file:py-1 file:text-[12px] file:font-semibold file:text-pine"
                        />
                        {form.errors.cover && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.cover}</p>}
                    </div>

                    <div>
                        <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                            Nội dung
                            <span className="ml-1 font-normal text-moss">
                                (soạn tuần tự: đoạn văn, ảnh, đoạn văn… — phía khách tự sắp bố cục ảnh/text xen kẽ)
                            </span>
                        </label>
                        <RichTextEditor
                            value={form.data.content}
                            onChange={(html) => form.setData('content', html)}
                            minHeight={440}
                        />
                        {form.errors.content && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.content}</p>}
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <Link
                            href={route('admin.pages')}
                            className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                        >
                            Quay lại
                        </Link>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-[10px] bg-grass px-6 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                        >
                            {form.processing ? 'Đang lưu…' : 'Lưu trang'}
                        </button>
                    </div>
                </form>
            </div>

            {toastMsg && (
                <div className="fixed bottom-6 left-1/2 z-[90] -translate-x-1/2 rounded-pill bg-pine px-5 py-2.5 text-[13.5px] font-semibold text-white shadow-lg">
                    {toastMsg}
                </div>
            )}
        </>
    );
}

AdminStaticPageEdit.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
