import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import RichTextEditor from '@/Components/admin/RichTextEditor';
import type { PageProps } from '@/types';

/**
 * Màn soạn "nội dung chi tiết" (setup/mô tả lớn) của sản phẩm — Epic 1 mục 1.4.
 * Tách riêng khỏi modal form sản phẩm vì editor cần full-width.
 * Nội dung hiện ở trang sản phẩm phía khách, nút "Xem thêm" cuộn tới đây.
 */
export default function AdminProductContent({
    product,
}: {
    product: { id: number; name: string; slug: string; setup_content: string | null };
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

    const form = useForm<{ setup_content: string }>({
        setup_content: product.setup_content ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(route('admin.products.content.update', product.id), { preserveScroll: true });
    };

    return (
        <div className="p-6">
            <Head title={`Nội dung — ${product.name}`} />
            <div className="mx-auto max-w-[880px]">
                <div className="mb-1 text-[12.5px] text-moss">
                    <Link href={route('admin.products')} className="font-semibold text-grass hover:underline">Sản phẩm</Link>
                    {' / '}
                    <span>{product.name}</span>
                </div>
                <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-[22px] font-extrabold tracking-tight text-pine">Nội dung chi tiết</h1>
                        <p className="mt-1 text-[13px] text-moss">
                            Hướng dẫn setup, ảnh minh hoạ… — hiện ở trang sản phẩm, nút “Xem thêm” cuộn tới khối này.
                            Để trống = ẩn khối và ẩn nút “Xem thêm”.
                        </p>
                    </div>
                    <a
                        href={`/thiet-bi/${product.slug}#chi-tiet`}
                        target="_blank"
                        rel="noreferrer"
                        className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                    >
                        Xem trang khách ↗
                    </a>
                </div>

                <form onSubmit={submit}>
                    <RichTextEditor
                        value={form.data.setup_content}
                        onChange={(html) => form.setData('setup_content', html)}
                        minHeight={480}
                    />
                    {form.errors.setup_content && (
                        <p className="mt-2 text-[12px] text-[#b3493a]">{form.errors.setup_content}</p>
                    )}
                    <div className="mt-4 flex items-center justify-end gap-3">
                        <Link
                            href={route('admin.products')}
                            className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                        >
                            Quay lại
                        </Link>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-[10px] bg-grass px-6 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                        >
                            {form.processing ? 'Đang lưu…' : 'Lưu nội dung'}
                        </button>
                    </div>
                </form>
            </div>

            {toastMsg && (
                <div className="fixed bottom-6 left-1/2 z-[90] -translate-x-1/2 rounded-pill bg-pine px-5 py-2.5 text-[13.5px] font-semibold text-white shadow-lg">
                    {toastMsg}
                </div>
            )}
        </div>
    );
}

AdminProductContent.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
