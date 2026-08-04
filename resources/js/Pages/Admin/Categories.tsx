import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';

type Category = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image: string | null;
    product_count: number;
};

export default function AdminCategories({
    categories,
}: {
    categories: Category[];
}) {
    const { flash } = usePage<PageProps>().props;

    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<Category | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [deleteError, setDeleteError] = useState('');
    const [toastMsg, setToastMsg] = useState('');

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const form = useForm({
        name: '',
        description: '',
        image: null as File | null,
    });

    const openCreate = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setModalMode('create');
    };

    const openEdit = (cat: Category) => {
        form.setData({
            name: cat.name,
            description: cat.description ?? '',
            image: null,
        });
        form.clearErrors();
        setEditing(cat);
        setModalMode('edit');
    };

    const closeModal = () => {
        setModalMode(null);
        setEditing(null);
        form.reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { forceFormData: true, onSuccess: closeModal };
        if (modalMode === 'create') {
            form.transform((data) => data);
            form.post(route('admin.categories.store'), opts);
        } else if (editing) {
            // PHP không nạp $_FILES cho PUT → POST kèm _method spoofing để upload ảnh khi sửa hoạt động.
            form.transform((data) => ({ ...data, _method: 'put' }));
            form.post(route('admin.categories.update', editing.id), opts);
        }
    };

    const confirmDelete = (id: number) => {
        setDeleteId(id);
        setDeleteError('');
    };

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.categories.destroy', deleteId), {
            onSuccess: () => setDeleteId(null),
            onError: (errors) =>
                setDeleteError(
                    (errors as Record<string, string>).message ??
                        'Lỗi không xác định',
                ),
        });
    };

    return (
        <>
            <Head title="Admin · Danh mục" />

            {/* Toast */}
            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">
                    ✓ {toastMsg}
                </div>
            )}

            <div className="p-6">
                {/* Page header */}
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">
                            Danh mục
                        </h1>
                        <p className="mt-0.5 text-[13px] text-moss">
                            <span className="font-mono">
                                {categories.length}
                            </span>{' '}
                            danh mục · quản lý nhóm thiết bị camping
                        </p>
                    </div>
                    <button
                        onClick={openCreate}
                        className="flex items-center gap-2 rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine"
                    >
                        <svg
                            width="15"
                            height="15"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <path
                                d="M12 5v14M5 12h14"
                                stroke="currentColor"
                                strokeWidth="2.5"
                                strokeLinecap="round"
                            />
                        </svg>
                        Thêm danh mục
                    </button>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                    {categories.length === 0 ? (
                        <div className="py-16 text-center text-moss">
                            <div className="mb-2 text-[32px]">📁</div>
                            <div className="text-[14px]">
                                Chưa có danh mục nào
                            </div>
                            <button
                                onClick={openCreate}
                                className="mt-3 text-[13px] font-semibold text-grass underline"
                            >
                                Thêm danh mục đầu tiên
                            </button>
                        </div>
                    ) : (
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr
                                    className="border-b border-[#eef2e3]"
                                    style={{ background: '#f8faf4' }}
                                >
                                    <th className="w-16 px-4 py-3 text-left font-semibold text-moss">
                                        Ảnh
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-moss">
                                        Tên
                                    </th>
                                    <th className="hidden px-4 py-3 text-left font-semibold text-moss md:table-cell">
                                        Slug
                                    </th>
                                    <th className="hidden px-4 py-3 text-left font-semibold text-moss lg:table-cell">
                                        Mô tả
                                    </th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">
                                        Sản phẩm
                                    </th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {categories.map((cat) => (
                                    <tr
                                        key={cat.id}
                                        className="border-b border-[#f1f4ea] last:border-0 hover:bg-[#fafcf7]"
                                    >
                                        <td className="px-4 py-3">
                                            {cat.image ? (
                                                <img
                                                    src={cat.image}
                                                    alt={cat.name}
                                                    className="h-10 w-10 rounded-[9px] object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-10 w-10 items-center justify-center rounded-[9px] bg-[#f1f4ea] text-[18px]">
                                                    🏕
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 font-semibold text-pine">
                                            {cat.name}
                                        </td>
                                        <td className="hidden px-4 py-3 font-mono text-[12px] text-moss md:table-cell">
                                            {cat.slug}
                                        </td>
                                        <td className="hidden max-w-[220px] truncate px-4 py-3 text-moss lg:table-cell">
                                            {cat.description ?? (
                                                <span className="text-[#c4cca8]">
                                                    -
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <span className="rounded-pill bg-[#f1f4ea] px-2.5 py-1 font-mono text-[12px] font-bold text-pine">
                                                {cat.product_count}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() =>
                                                        openEdit(cat)
                                                    }
                                                    className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                >
                                                    Sửa
                                                </button>
                                                <button
                                                    onClick={() =>
                                                        confirmDelete(cat.id)
                                                    }
                                                    className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                                >
                                                    Xoá
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>

            {/* Create / Edit Modal */}
            {modalMode && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                    onClick={closeModal}
                >
                    <div
                        className="w-full max-w-md rounded-[18px] bg-white p-6 shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="mb-5 text-[18px] font-extrabold text-pine">
                            {modalMode === 'create'
                                ? 'Thêm danh mục'
                                : 'Sửa danh mục'}
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* Name */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Tên danh mục{' '}
                                    <span className="text-[#b3493a]">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="VD: Lều trại"
                                    autoFocus
                                />
                                {form.errors.name && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>

                            {/* Description */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Mô tả
                                </label>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="Mô tả ngắn về nhóm thiết bị"
                                />
                            </div>

                            {/* Image */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Ảnh đại diện
                                    {modalMode === 'edit' && editing?.image && (
                                        <span className="ml-1 font-normal text-moss">
                                            {' '}
                                            (để trống = giữ ảnh cũ)
                                        </span>
                                    )}
                                </label>
                                {modalMode === 'edit' && editing?.image && (
                                    <img
                                        src={editing.image}
                                        alt=""
                                        className="mb-2 h-16 w-16 rounded-[9px] border border-cardBorder object-cover"
                                    />
                                )}
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) =>
                                        form.setData(
                                            'image',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                    className="w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] file:mr-3 file:rounded-[7px] file:border-0 file:bg-[#f1f4ea] file:px-3 file:py-1 file:text-[12px] file:font-semibold file:text-pine"
                                />
                                {form.errors.image && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">
                                        {form.errors.image}
                                    </p>
                                )}
                            </div>

                            {/* Actions */}
                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={closeModal}
                                    className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                                >
                                    Huỷ
                                </button>
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-[10px] bg-grass px-5 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                                >
                                    {form.processing ? 'Đang lưu…' : 'Lưu lại'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Delete Confirm */}
            {deleteId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl">
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">
                            Xác nhận xoá
                        </h2>
                        <p className="mb-4 text-[13px] text-moss">
                            Bạn có chắc muốn xoá danh mục này? Hành động không
                            thể hoàn tác.
                        </p>
                        {deleteError && (
                            <div className="mb-4 rounded-[9px] bg-[#f6ddd6] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#b3493a]">
                                {deleteError}
                            </div>
                        )}
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => {
                                    setDeleteId(null);
                                    setDeleteError('');
                                }}
                                className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                            >
                                Huỷ
                            </button>
                            <button
                                onClick={doDelete}
                                className="rounded-[10px] bg-[#b3493a] px-5 py-2 text-[13px] font-bold text-white transition hover:bg-[#8a3328]"
                            >
                                Xoá
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

AdminCategories.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
