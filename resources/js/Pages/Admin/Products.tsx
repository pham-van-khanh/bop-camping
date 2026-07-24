import { Head, router, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useRef, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductStatusPill from '@/Components/ProductStatusPill';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';

type ProductImage = { id: number; path: string; sort_order: number; type: 'image' | 'video' };
type CategoryOption = { id: number; name: string };
type Product = {
    id: number;
    name: string;
    slug: string;
    has_setup_content: boolean;
    price_per_day: number;
    quantity: number;
    deposit: number | null;
    thumbnail: string | null;
    status: 'active' | 'hidden';
    category: { id: number; name: string } | null;
    combo_names: string[];
    images: ProductImage[];
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

export default function AdminProducts({
    products,
    categories,
    filters,
}: {
    products: Paginator<Product>;
    categories: CategoryOption[];
    filters: { search: string; category: number | null };
}) {
    const { flash } = usePage<PageProps>().props;

    // Lọc sản phẩm: tìm theo tên (debounce) + lọc danh mục. Giữ state qua phân trang.
    const [search, setSearch] = useState(filters.search ?? '');
    const [category, setCategory] = useState<number | null>(filters.category ?? null);

    const applyFilters = (next: { search?: string; category?: number | null }) => {
        const s = next.search !== undefined ? next.search : search;
        const c = next.category !== undefined ? next.category : category;
        router.get(
            route('admin.products'),
            { search: s || undefined, category: c || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    };

    // Debounce ô tìm: chỉ gọi server sau khi ngừng gõ 350ms.
    const searchDebounce = useRef<ReturnType<typeof setTimeout>>();
    const onSearchChange = (value: string) => {
        setSearch(value);
        clearTimeout(searchDebounce.current);
        searchDebounce.current = setTimeout(() => applyFilters({ search: value }), 350);
    };

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

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.products.destroy', deleteId), {
            preserveScroll: true,
            onSuccess: () => { setDeleteId(null); setDeleteError(''); },
            onError: (errors) =>
                setDeleteError((errors as Record<string, string>).message ?? 'Xoá không thành công, thử lại nhé.'),
        });
    };

    return (
        <>
            <Head title="Admin · Sản phẩm" />

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
                        <h1 className="text-[22px] font-extrabold text-pine">Sản phẩm</h1>
                        <p className="mt-0.5 text-[13px] text-moss">
                            <span className="font-mono">{products.total}</span> sản phẩm
                        </p>
                    </div>
                    <button
                        onClick={() => router.get(route('admin.products.create'))}
                        className="flex items-center gap-2 rounded-[11px] bg-grass px-4 py-2.5 text-[13.5px] font-bold text-white transition hover:bg-pine"
                    >
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <path d="M12 5v14M5 12h14" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" />
                        </svg>
                        Thêm sản phẩm
                    </button>
                </div>

                {/* Bộ lọc: tìm theo tên + lọc danh mục */}
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[#8a967a]">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" strokeWidth="2" />
                            <path d="m20 20-3-3" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
                        </svg>
                        <input
                            value={search}
                            onChange={(e) => onSearchChange(e.target.value)}
                            placeholder="Tìm theo tên sản phẩm…"
                            className="h-11 w-full rounded-[11px] border border-cardBorder bg-white pl-9 pr-9 text-[14px] text-ink outline-none focus:border-grass"
                        />
                        {search && (
                            <button
                                onClick={() => { setSearch(''); applyFilters({ search: '' }); }}
                                aria-label="Xoá tìm kiếm"
                                className="absolute right-2.5 top-1/2 grid h-6 w-6 -translate-y-1/2 place-items-center rounded-full text-[15px] text-[#8a967a] hover:bg-[#f1f4ea]"
                            >
                                ×
                            </button>
                        )}
                    </div>
                    <select
                        value={category ?? ''}
                        onChange={(e) => { const c = e.target.value ? Number(e.target.value) : null; setCategory(c); applyFilters({ category: c }); }}
                        className="h-11 rounded-[11px] border border-cardBorder bg-white px-3 text-[14px] text-ink outline-none focus:border-grass sm:w-56"
                    >
                        <option value="">Tất cả danh mục</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>{c.name}</option>
                        ))}
                    </select>
                </div>

                {/* Table */}
                <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                    {products.data.length === 0 ? (
                        <div className="py-16 text-center text-moss">
                            <div className="mb-2 text-[32px]">⛺️</div>
                            <div className="text-[14px]">
                                {filters.search || filters.category ? 'Không tìm thấy sản phẩm phù hợp' : 'Chưa có sản phẩm nào'}
                            </div>
                            {filters.search || filters.category ? (
                                <button onClick={() => { setSearch(''); setCategory(null); applyFilters({ search: '', category: null }); }} className="mt-3 text-[13px] font-semibold text-grass underline">
                                    Xoá bộ lọc
                                </button>
                            ) : (
                                <button onClick={() => router.get(route('admin.products.create'))} className="mt-3 text-[13px] font-semibold text-grass underline">
                                    Thêm sản phẩm đầu tiên
                                </button>
                            )}
                        </div>
                    ) : (
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr className="border-b border-[#eef2e3]" style={{ background: '#f8faf4' }}>
                                    <th className="w-12 px-4 py-3 text-left font-semibold text-moss">Ảnh</th>
                                    <th className="px-4 py-3 text-left font-semibold text-moss">Tên sản phẩm</th>
                                    <th className="hidden px-4 py-3 text-left font-semibold text-moss md:table-cell">
                                        Danh mục
                                    </th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">Giá/ngày</th>
                                    <th className="hidden px-4 py-3 text-center font-semibold text-moss sm:table-cell">
                                        Kho
                                    </th>
                                    <th className="hidden px-4 py-3 text-center font-semibold text-moss lg:table-cell">
                                        Ảnh PL
                                    </th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">Trạng thái</th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.map((p) => (
                                    <tr
                                        key={p.id}
                                        className="cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7]"
                                        onClick={() => router.get(route('admin.products.edit', p.id))}
                                    >
                                        <td className="px-4 py-3">
                                            {p.thumbnail ? (
                                                <img
                                                    src={p.thumbnail}
                                                    alt={p.name}
                                                    className="h-10 w-10 rounded-[9px] object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-10 w-10 items-center justify-center rounded-[9px] bg-[#f1f4ea] text-[18px]">
                                                    ⛺
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="font-semibold text-pine">{p.name}</div>
                                            <div className="font-mono text-[11px] text-moss">{p.slug}</div>
                                        </td>
                                        <td className="hidden px-4 py-3 text-moss md:table-cell">
                                            {p.category?.name ?? <span className="text-[#c4cca8]">-</span>}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="font-mono font-bold text-pine">
                                                {money(p.price_per_day)}
                                            </div>
                                            {p.deposit ? (
                                                <div className="font-mono text-[11px] text-campfire">
                                                    cọc {money(p.deposit)}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="hidden px-4 py-3 text-center sm:table-cell">
                                            <span className="font-mono font-bold text-pine">{p.quantity}</span>
                                        </td>
                                        <td className="hidden px-4 py-3 text-center lg:table-cell">
                                            <span className="font-mono text-moss">{p.images.length}</span>
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <ProductStatusPill status={p.status} />
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div
                                                className="flex items-center justify-end gap-2"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <button
                                                    onClick={() => router.get(route('admin.products.content.edit', p.id))}
                                                    title="Soạn nội dung chi tiết (setup, ảnh minh hoạ)"
                                                    className="relative rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                >
                                                    Nội dung
                                                    {p.has_setup_content && (
                                                        <span className="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-grass ring-2 ring-white" title="Đã có nội dung" />
                                                    )}
                                                </button>
                                                <button
                                                    onClick={() => router.get(route('admin.products.edit', p.id))}
                                                    className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                >
                                                    Sửa
                                                </button>
                                                <button
                                                    onClick={() => { setDeleteId(p.id); setDeleteError(''); }}
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

                {/* Pagination */}
                {products.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-[12.5px] text-moss">
                        <span className="font-mono">{products.from}–{products.to} / {products.total}</span>
                        <div className="flex gap-2">
                            <button
                                disabled={products.current_page <= 1}
                                onClick={() => router.get(route('admin.products'), { page: products.current_page - 1, search: search || undefined, category: category || undefined }, { preserveScroll: true })}
                                className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                            >
                                Trước
                            </button>
                            <button
                                disabled={products.current_page >= products.last_page}
                                onClick={() => router.get(route('admin.products'), { page: products.current_page + 1, search: search || undefined, category: category || undefined }, { preserveScroll: true })}
                                className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Delete Confirm */}
            {deleteId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl">
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">Xác nhận xoá sản phẩm</h2>
                        <p className="mb-4 text-[13px] text-moss">
                            Tất cả ảnh của sản phẩm cũng sẽ bị xoá. Hành động không thể hoàn tác.
                        </p>
                        {/* US-07: xoá sản phẩm thuộc combo → combo tự ẩn */}
                        {(() => {
                            const comboNames = products.data.find((p) => p.id === deleteId)?.combo_names ?? [];
                            return comboNames.length > 0 ? (
                                <p className="mb-4 rounded-[9px] bg-[#fdf3f1] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#b3493a]">
                                    ⚠ Sản phẩm này thuộc combo: {comboNames.join(', ')}. Xoá sản phẩm sẽ tự ẩn các
                                    combo đó khỏi trang bán.
                                </p>
                            ) : null;
                        })()}
                        {deleteError && (
                            <div className="mb-4 rounded-[9px] bg-[#f6ddd6] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#b3493a]">
                                {deleteError}
                            </div>
                        )}
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => { setDeleteId(null); setDeleteError(''); }}
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

AdminProducts.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
