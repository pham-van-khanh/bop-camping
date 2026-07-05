import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useEffect, useRef, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductStatusPill from '@/Components/ProductStatusPill';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';

type ProductImage = { id: number; path: string; sort_order: number; type: 'image' | 'video' };
type CategoryOption = { id: number; name: string };
type ServiceLocationOption = { id: number; name: string; area: string | null; status: 'open' | 'coming' };
type AccessoryOption = { id: number; name: string; status: 'active' | 'hidden' };
type Product = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price_per_day: number;
    quantity: number;
    deposit: number | null;
    thumbnail: string | null;
    status: 'active' | 'hidden';
    category: { id: number; name: string } | null;
    service_location_ids: number[];
    accessory_ids: number[];
    combo_names: string[];
    images: ProductImage[];
};

type ProductFormData = {
    name: string;
    category_id: number | '';
    description: string;
    price_per_day: number | '';
    quantity: number | '';
    deposit: number | null;   // null (không phải '') để JSON request xử lý đúng nullable
    status: 'active' | 'hidden';
    thumbnail: File | null;
    service_location_ids: number[];
    // "Thường thuê cùng" (US-08) — thứ tự trong mảng = thứ tự hiển thị ở trang sản phẩm
    accessory_ids: number[];
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
    service_locations,
    accessory_options,
    filters,
}: {
    products: Paginator<Product>;
    categories: CategoryOption[];
    service_locations: ServiceLocationOption[];
    accessory_options: AccessoryOption[];
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

    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<Product | null>(null);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [deleteError, setDeleteError] = useState('');
    const [toastMsg, setToastMsg] = useState('');
    const [uploadingId, setUploadingId] = useState<number | null>(null);
    const uploadRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const openLocationIds = service_locations.filter((l) => l.status === 'open').map((l) => l.id);

    const form = useForm<ProductFormData>({
        name: '',
        category_id: '',
        description: '',
        price_per_day: '',
        quantity: '',
        deposit: null,
        status: 'active',
        thumbnail: null,
        service_location_ids: [],
        accessory_ids: [],
    });

    const blank = (): ProductFormData => ({
        name: '',
        category_id: categories[0]?.id ?? '',
        description: '',
        price_per_day: '',
        quantity: '',
        deposit: null,
        status: 'active',
        thumbnail: null,
        // Mặc định gắn tất cả vị trí đang mở khi thêm mới.
        service_location_ids: [...openLocationIds],
        accessory_ids: [],
    });

    const toggleLocation = (id: number) => {
        const cur = form.data.service_location_ids;
        form.setData('service_location_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    /* --- "Thường thuê cùng" (US-08): thứ tự click = thứ tự hiển thị --- */
    const [accSearch, setAccSearch] = useState('');
    const accessoryName = (id: number) => accessory_options.find((o) => o.id === id)?.name ?? `#${id}`;

    const toggleAccessory = (id: number) => {
        const cur = form.data.accessory_ids;
        form.setData('accessory_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    const openCreate = () => {
        form.setData(blank());
        form.clearErrors();
        setAccSearch('');
        setEditing(null);
        setModalMode('create');
    };

    const openEdit = (p: Product) => {
        form.setData({
            name: p.name,
            category_id: p.category?.id ?? '',
            description: p.description ?? '',
            price_per_day: p.price_per_day,
            quantity: p.quantity,
            deposit: p.deposit ?? null,
            status: p.status,
            thumbnail: null,
            service_location_ids: p.service_location_ids ?? [],
            accessory_ids: p.accessory_ids ?? [],
        });
        form.clearErrors();
        setAccSearch('');
        setEditing(p);
        setModalMode('edit');
    };

    const closeModal = () => {
        setModalMode(null);
        setEditing(null);
        form.reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Transform: đảm bảo kiểu đúng trước khi gửi.
        // deposit null → gửi null (JSON) hoặc '' (FormData, sau đó ConvertEmptyStringsToNull → null).
        // price_per_day / quantity là '' nghĩa là user chưa điền → backend trả required error đúng.
        form.transform((data) => ({
            ...data,
            // Đảm bảo category_id luôn là số (không phải string rỗng)
            category_id: data.category_id === '' ? '' : Number(data.category_id),
        }));

        const opts = { forceFormData: true, onSuccess: closeModal };
        // FormData không gửi được mảng rỗng → gửi '' để backend hiểu là "xoá hết gợi ý"
        // (khác với không gửi key = giữ nguyên).
        if (modalMode === 'create') {
            form.transform((data) => ({
                ...data,
                accessory_ids: data.accessory_ids.length ? data.accessory_ids : '',
            }));
            form.post(route('admin.products.store'), opts);
        } else if (editing) {
            // PHP không nạp $_FILES cho PUT → POST kèm _method spoofing để upload ảnh khi sửa hoạt động.
            form.transform((data) => ({
                ...data,
                _method: 'put',
                accessory_ids: data.accessory_ids.length ? data.accessory_ids : '',
            }));
            form.post(route('admin.products.update', editing.id), opts);
        }
    };

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.products.destroy', deleteId), {
            preserveScroll: true,
            onSuccess: () => { setDeleteId(null); setDeleteError(''); },
            onError: (errors) =>
                setDeleteError((errors as Record<string, string>).message ?? 'Xoá không thành công, thử lại nhé.'),
        });
    };

    /* --- Image upload --- */
    // Lưu ở ref (không phải state) vì chỉ dùng để nhớ sản phẩm đích cho lần chọn file
    // kế tiếp — set nó KHÔNG được kích hoạt trạng thái "đang tải" trên UI (xem dưới).
    const uploadTargetRef = useRef<number | null>(null);
    const triggerUpload = (productId: number) => {
        uploadTargetRef.current = productId;
        uploadRef.current?.click();
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const productId = uploadTargetRef.current;
        // Bấm Cancel ở hộp thoại chọn file → không có file → không set uploadingId,
        // nút không bị kẹt ở "Đang tải…" (trước đây set uploadingId ngay lúc mở dialog,
        // nếu khách Cancel thì onChange không bắn nên không có gì reset lại được).
        if (!productId || !e.target.files?.length) return;
        setUploadingId(productId);
        const formData = new FormData();
        Array.from(e.target.files).forEach((f) => formData.append('images[]', f));
        router.post(route('admin.products.images.store', productId), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploadingId(null);
                e.target.value = '';
            },
        });
    };

    const deleteImage = (productId: number, imageId: number) => {
        router.delete(route('admin.products.images.destroy', [productId, imageId]), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Admin · Sản phẩm" />

            {/* Hidden file input for image/video upload */}
            <input
                ref={uploadRef}
                type="file"
                accept="image/*,video/*"
                multiple
                className="hidden"
                onChange={handleFileChange}
            />

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
                            <span className="font-mono">{products.total}</span> sản phẩm · click vào hàng để quản lý ảnh
                        </p>
                    </div>
                    <button
                        onClick={openCreate}
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
                                <button onClick={openCreate} className="mt-3 text-[13px] font-semibold text-grass underline">
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
                                {products.data.map((p) => {
                                    const expanded = expandedId === p.id;
                                    return (
                                        <Fragment key={p.id}>
                                            <tr
                                                className="cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7]"
                                                onClick={() => setExpandedId(expanded ? null : p.id)}
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
                                                    {p.category?.name ?? (
                                                        <span className="text-[#c4cca8]">-</span>
                                                    )}
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
                                                            onClick={() => openEdit(p)}
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

                                            {/* Expanded: image gallery */}
                                            {expanded && (
                                                <tr className="border-b border-[#f1f4ea]">
                                                    <td
                                                        colSpan={8}
                                                        className="px-6 pb-5 pt-3"
                                                        style={{ background: '#fafcf7' }}
                                                    >
                                                        <div className="mb-2 flex items-center justify-between">
                                                            <span className="text-[12.5px] font-semibold text-moss">
                                                                Ảnh phụ ({p.images.length})
                                                            </span>
                                                            <button
                                                                onClick={() => triggerUpload(p.id)}
                                                                disabled={uploadingId === p.id}
                                                                className="flex items-center gap-1.5 rounded-[8px] border border-cardBorder bg-white px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass disabled:opacity-50"
                                                            >
                                                                <svg
                                                                    width="13"
                                                                    height="13"
                                                                    viewBox="0 0 24 24"
                                                                    fill="none"
                                                                >
                                                                    <path
                                                                        d="M12 5v14M5 12h14"
                                                                        stroke="currentColor"
                                                                        strokeWidth="2"
                                                                        strokeLinecap="round"
                                                                    />
                                                                </svg>
                                                                {uploadingId === p.id ? 'Đang tải…' : 'Upload ảnh/video'}
                                                            </button>
                                                        </div>

                                                        {p.images.length === 0 ? (
                                                            <div className="rounded-[10px] border border-dashed border-cardBorder py-6 text-center text-[12.5px] text-moss">
                                                                Chưa có ảnh phụ · click "Upload ảnh" để thêm
                                                            </div>
                                                        ) : (
                                                            <div className="flex flex-wrap gap-3">
                                                                {p.images.map((img) => (
                                                                    <div key={img.id} className="group relative">
                                                                        {img.type === 'video' ? (
                                                                            <video
                                                                                src={img.path}
                                                                                className="h-20 w-20 rounded-[10px] border border-cardBorder object-cover"
                                                                                muted
                                                                            />
                                                                        ) : (
                                                                            <img
                                                                                src={img.path}
                                                                                alt=""
                                                                                className="h-20 w-20 rounded-[10px] object-cover border border-cardBorder"
                                                                            />
                                                                        )}
                                                                        {img.type === 'video' && (
                                                                            <span className="pointer-events-none absolute inset-0 grid place-items-center text-white">▶</span>
                                                                        )}
                                                                        <button
                                                                            onClick={() => deleteImage(p.id, img.id)}
                                                                            className="absolute -right-1.5 -top-1.5 hidden h-5 w-5 items-center justify-center rounded-full bg-[#b3493a] text-[10px] font-bold text-white shadow group-hover:flex"
                                                                            title="Xoá ảnh"
                                                                        >
                                                                            ×
                                                                        </button>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            )}
                                        </Fragment>
                                    );
                                })}
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

            {/* Create / Edit Modal */}
            {modalMode && (
                <div
                    className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8"
                    onClick={closeModal}
                >
                    <div
                        className="w-full max-w-lg rounded-[18px] bg-white p-6 shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="mb-5 text-[18px] font-extrabold text-pine">
                            {modalMode === 'create' ? 'Thêm sản phẩm' : 'Sửa sản phẩm'}
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            {/* Name */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Tên sản phẩm <span className="text-[#b3493a]">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) => form.setData('name', e.target.value)}
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="VD: Lều 2 người Naturehike Cloud Up 2"
                                    autoFocus
                                />
                                {form.errors.name && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.name}</p>
                                )}
                            </div>

                            {/* Category */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Danh mục <span className="text-[#b3493a]">*</span>
                                </label>
                                <select
                                    value={form.data.category_id}
                                    onChange={(e) =>
                                        form.setData('category_id', e.target.value === '' ? '' : Number(e.target.value))
                                    }
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                >
                                    <option value="">Chọn danh mục</option>
                                    {categories.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.category_id && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.category_id}</p>
                                )}
                            </div>

                            {/* Service locations */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Vị trí phục vụ <span className="text-[#b3493a]">*</span>
                                </label>
                                {service_locations.length === 0 ? (
                                    <p className="text-[12.5px] text-moss">
                                        Chưa có vị trí phục vụ nào. Thêm ở mục <span className="font-semibold">Điểm cắm trại</span> trước.
                                    </p>
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {service_locations.map((l) => {
                                            const on = form.data.service_location_ids.includes(l.id);
                                            const coming = l.status === 'coming';
                                            return (
                                                <button
                                                    type="button"
                                                    key={l.id}
                                                    disabled={coming}
                                                    onClick={() => toggleLocation(l.id)}
                                                    className={`flex items-center gap-2 rounded-[11px] border px-3.5 py-2 text-[13px] font-semibold transition ${
                                                        coming
                                                            ? 'cursor-not-allowed border-cardBorder bg-[#f6f8f1] text-[#aab39a] opacity-70'
                                                            : on
                                                              ? 'border-grass bg-[#eef5e1] text-grass'
                                                              : 'border-cardBorder bg-white text-pine hover:border-grass'
                                                    }`}
                                                >
                                                    <span
                                                        className={`grid h-[17px] w-[17px] place-items-center rounded-[5px] border text-[11px] font-bold ${
                                                            on ? 'border-grass bg-grass text-white' : 'border-[#c4cca8] text-transparent'
                                                        }`}
                                                    >
                                                        ✓
                                                    </span>
                                                    <span className="text-left leading-tight">
                                                        {l.name}
                                                        {coming && <span className="ml-1 rounded-pill bg-[#eef2e3] px-1.5 py-0.5 text-[10px] font-semibold text-moss">Sắp mở</span>}
                                                        {l.area && <span className="block text-[10.5px] font-normal text-moss">{l.area}</span>}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}
                                {form.errors.service_location_ids && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.service_location_ids}</p>
                                )}
                            </div>

                            {/* Thường thuê cùng (US-08) — gợi ý hiện ở trang sản phẩm (Case 2) */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Thường thuê cùng
                                    <span className="ml-1 font-normal text-moss">(thứ tự chọn = thứ tự hiển thị)</span>
                                </label>

                                {/* Chip các món đã chọn, đúng thứ tự */}
                                {form.data.accessory_ids.length > 0 && (
                                    <div className="mb-2 flex flex-wrap gap-1.5">
                                        {form.data.accessory_ids.map((id, i) => (
                                            <span key={id} className="flex items-center gap-1.5 rounded-pill bg-[#eef5e1] py-1 pl-2.5 pr-1.5 text-[12px] font-semibold text-grass">
                                                <span className="font-mono text-[10.5px] text-moss">{i + 1}.</span>
                                                {accessoryName(id)}
                                                <button
                                                    type="button"
                                                    onClick={() => toggleAccessory(id)}
                                                    className="grid h-4 w-4 place-items-center rounded-full bg-[#c4cfae] text-[10px] font-bold text-white hover:bg-[#b3493a]"
                                                    title="Bỏ khỏi gợi ý"
                                                >
                                                    ×
                                                </button>
                                            </span>
                                        ))}
                                    </div>
                                )}

                                <input
                                    type="text"
                                    value={accSearch}
                                    onChange={(e) => setAccSearch(e.target.value)}
                                    placeholder="Tìm sản phẩm để thêm gợi ý…"
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2 text-[13px] outline-none transition focus:border-grass"
                                />
                                <div className="mt-1.5 max-h-[160px] overflow-y-auto rounded-[10px] border border-cardBorder">
                                    {accessory_options
                                        .filter((o) => o.id !== editing?.id)
                                        .filter((o) => o.name.toLowerCase().includes(accSearch.toLowerCase()))
                                        .map((o) => {
                                            const on = form.data.accessory_ids.includes(o.id);
                                            return (
                                                <button
                                                    type="button"
                                                    key={o.id}
                                                    onClick={() => toggleAccessory(o.id)}
                                                    className={`flex w-full items-center gap-2 border-b border-[#f1f4ea] px-3 py-2 text-left text-[12.5px] transition last:border-b-0 ${
                                                        on ? 'bg-[#f4f8ec] font-semibold text-grass' : 'text-pine hover:bg-[#fafcf7]'
                                                    }`}
                                                >
                                                    <span
                                                        className={`grid h-[15px] w-[15px] flex-none place-items-center rounded-[4px] border text-[10px] font-bold ${
                                                            on ? 'border-grass bg-grass text-white' : 'border-[#c4cca8] text-transparent'
                                                        }`}
                                                    >
                                                        ✓
                                                    </span>
                                                    <span className="flex-1">{o.name}</span>
                                                    {o.status === 'hidden' && (
                                                        <span className="rounded-pill bg-[#f1f4ea] px-1.5 py-0.5 text-[10px] font-semibold text-moss">Đang ẩn</span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                </div>
                                {Object.entries(form.errors)
                                    .filter(([k]) => k.startsWith('accessory_ids'))
                                    .map(([k, v]) => (
                                        <p key={k} className="mt-1 text-[12px] text-[#b3493a]">{v}</p>
                                    ))}
                            </div>

                            {/* Price / Qty / Deposit row */}
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Giá/ngày (₫) <span className="text-[#b3493a]">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.price_per_day}
                                        onChange={(e) =>
                                            form.setData('price_per_day', e.target.value === '' ? '' : Number(e.target.value))
                                        }
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                        placeholder="50000"
                                    />
                                    {form.errors.price_per_day && (
                                        <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.price_per_day}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Số lượng <span className="text-[#b3493a]">*</span>
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.quantity}
                                        onChange={(e) =>
                                            form.setData('quantity', e.target.value === '' ? '' : Number(e.target.value))
                                        }
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                        placeholder="5"
                                    />
                                    {form.errors.quantity && (
                                        <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.quantity}</p>
                                    )}
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Tiền cọc (₫)
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.deposit ?? ''}
                                        onChange={(e) =>
                                            form.setData('deposit', e.target.value === '' ? null : Number(e.target.value))
                                        }
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                        placeholder="200000"
                                    />
                                </div>
                            </div>

                            {/* Status */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">Trạng thái</label>
                                <div className="flex gap-3">
                                    {(['active', 'hidden'] as const).map((s) => (
                                        <label key={s} className="flex cursor-pointer items-center gap-2">
                                            <input
                                                type="radio"
                                                name="status"
                                                value={s}
                                                checked={form.data.status === s}
                                                onChange={() => form.setData('status', s)}
                                                className="accent-grass"
                                            />
                                            <span className="text-[13px] text-pine">
                                                {s === 'active' ? 'Đang bán' : 'Ẩn'}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                {/* US-07: ẩn sản phẩm đang thuộc combo → combo tự ẩn theo */}
                                {form.data.status === 'hidden' && editing && editing.combo_names.length > 0 && (
                                    <p className="mt-2 rounded-[9px] bg-[#fdf3f1] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#b3493a]">
                                        ⚠ Sản phẩm này thuộc combo: {editing.combo_names.join(', ')}. Ẩn sản phẩm sẽ tự
                                        ẩn các combo đó khỏi trang bán.
                                    </p>
                                )}
                            </div>

                            {/* Description */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">Mô tả</label>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) => form.setData('description', e.target.value)}
                                    rows={3}
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="Mô tả sản phẩm, tính năng nổi bật..."
                                />
                            </div>

                            {/* Thumbnail */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Ảnh đại diện
                                    {modalMode === 'edit' && editing?.thumbnail && (
                                        <span className="ml-1 font-normal text-moss"> (để trống = giữ ảnh cũ)</span>
                                    )}
                                </label>
                                {modalMode === 'edit' && editing?.thumbnail && (
                                    <img
                                        src={editing.thumbnail}
                                        alt=""
                                        className="mb-2 h-16 w-16 rounded-[9px] border border-cardBorder object-cover"
                                    />
                                )}
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => form.setData('thumbnail', e.target.files?.[0] ?? null)}
                                    className="w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] file:mr-3 file:rounded-[7px] file:border-0 file:bg-[#f1f4ea] file:px-3 file:py-1 file:text-[12px] file:font-semibold file:text-pine"
                                />
                                {form.errors.thumbnail && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.thumbnail}</p>
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
