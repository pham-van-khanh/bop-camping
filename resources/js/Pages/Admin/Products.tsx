import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useEffect, useRef, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import ProductStatusPill from '@/Components/ProductStatusPill';
import MediaGallery from '@/Components/admin/MediaGallery';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';

type ProductImage = { id: number; path: string; sort_order: number; type: 'image' | 'video' };
type CategoryOption = { id: number; name: string };
type ServiceLocationOption = { id: number; name: string; area: string | null; status: 'open' | 'coming' };
type AccessoryOption = { id: number; name: string; status: 'active' | 'hidden' };
type SpecRow = { key: string; value: string };
type Product = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    specs: SpecRow[];
    has_setup_content: boolean;
    price_per_day: number;
    quantity: number;
    deposit: number | null;
    pickup_hour: number | null;
    return_hour: number | null;
    thumbnail: string | null;
    status: 'active' | 'hidden';
    category: { id: number; name: string } | null;
    service_location_ids: number[];
    stocks: Record<number, number>;
    accessory_ids: number[];
    related_ids: number[];
    combo_names: string[];
    images: ProductImage[];
};

type ProductFormData = {
    name: string;
    category_id: number | '';
    description: string;
    // Thông số key–value (Epic 1, 1.2) — thứ tự dòng = thứ tự hiển thị
    specs: SpecRow[];
    price_per_day: number | '';
    deposit: number | null;   // null (không phải '') để JSON request xử lý đúng nullable
    // Khung giờ nhận/trả riêng ('' = theo shop) — bopcamping-n6mr
    pickup_hour: number | '';
    return_hour: number | '';
    status: 'active' | 'hidden';
    thumbnail: File | null;
    service_location_ids: number[];
    // Tồn kho theo cửa hàng (per-store): service_location_id -> số lượng
    stocks: Record<number, number | ''>;
    // "Thường thuê cùng" (US-08) — thứ tự trong mảng = thứ tự hiển thị ở trang sản phẩm
    accessory_ids: number[];
    // "Có thể bạn cũng thích" (Epic 1, 1.6) — admin tự chọn, tối đa 12
    related_ids: number[];
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
        specs: [],
        price_per_day: '',
        deposit: null,
        pickup_hour: '',
        return_hour: '',
        status: 'active',
        thumbnail: null,
        service_location_ids: [],
        stocks: {},
        accessory_ids: [],
        related_ids: [],
    });

    const blank = (): ProductFormData => ({
        name: '',
        category_id: categories[0]?.id ?? '',
        description: '',
        specs: [],
        price_per_day: '',
        deposit: null,
        pickup_hour: '',
        return_hour: '',
        status: 'active',
        thumbnail: null,
        // Mặc định gắn tất cả vị trí đang mở khi thêm mới.
        service_location_ids: [...openLocationIds],
        stocks: {},
        accessory_ids: [],
        related_ids: [],
    });

    const toggleLocation = (id: number) => {
        const cur = form.data.service_location_ids;
        form.setData('service_location_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    const setStock = (id: number, value: string) => {
        form.setData('stocks', { ...form.data.stocks, [id]: value === '' ? '' : Math.max(0, Number(value)) });
    };

    /* --- "Thường thuê cùng" (US-08) + "Có thể bạn cũng thích" (Epic 1):
           thứ tự click = thứ tự hiển thị, dùng chung SortedProductPicker --- */
    const toggleAccessory = (id: number) => {
        const cur = form.data.accessory_ids;
        form.setData('accessory_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    const toggleRelated = (id: number) => {
        const cur = form.data.related_ids;
        form.setData('related_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    /* --- Thông số key–value (Epic 1, 1.2) --- */
    const setSpec = (i: number, field: keyof SpecRow, value: string) => {
        const next = form.data.specs.map((row, idx) => (idx === i ? { ...row, [field]: value } : row));
        form.setData('specs', next);
    };
    const addSpecRow = () => form.setData('specs', [...form.data.specs, { key: '', value: '' }]);
    const removeSpecRow = (i: number) => form.setData('specs', form.data.specs.filter((_, idx) => idx !== i));

    const openCreate = () => {
        form.setData(blank());
        form.clearErrors();
        setEditing(null);
        setModalMode('create');
    };

    const openEdit = (p: Product) => {
        form.setData({
            name: p.name,
            category_id: p.category?.id ?? '',
            description: p.description ?? '',
            specs: p.specs ?? [],
            price_per_day: p.price_per_day,
            deposit: p.deposit ?? null,
            pickup_hour: p.pickup_hour ?? '',
            return_hour: p.return_hour ?? '',
            status: p.status,
            thumbnail: null,
            service_location_ids: p.service_location_ids ?? [],
            stocks: { ...(p.stocks ?? {}) },
            accessory_ids: p.accessory_ids ?? [],
            related_ids: p.related_ids ?? [],
        });
        form.clearErrors();
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

        // Transform: đảm bảo kiểu đúng trước khi gửi (form.transform chỉ giữ 1 callback
        // — gom hết vào một chỗ, đừng gọi transform 2 lần kẻo cái sau đè cái trước).
        // deposit null → gửi null (JSON) hoặc '' (FormData, sau đó ConvertEmptyStringsToNull → null).
        // price_per_day / quantity là '' nghĩa là user chưa điền → backend trả required error đúng.
        // FormData không gửi được mảng rỗng → gửi '' để backend hiểu là "xoá hết"
        // (khác với không gửi key = giữ nguyên).
        form.transform((data) => ({
            ...data,
            // Đảm bảo category_id luôn là số (không phải string rỗng)
            category_id: data.category_id === '' ? '' : Number(data.category_id),
            accessory_ids: data.accessory_ids.length ? data.accessory_ids : '',
            related_ids: data.related_ids.length ? data.related_ids : '',
            specs: data.specs.length ? data.specs : '',
            // Tồn kho: chỉ gửi số của store đã tick (store bỏ tick không gửi → không tạo pivot).
            stocks: Object.fromEntries(
                data.service_location_ids.map((id) => [id, data.stocks[id] === '' || data.stocks[id] == null ? 0 : data.stocks[id]]),
            ),
            // PHP không nạp $_FILES cho PUT → POST kèm _method spoofing khi sửa.
            ...(modalMode === 'edit' ? { _method: 'put' } : {}),
        }));

        const opts = { forceFormData: true, onSuccess: closeModal };
        if (modalMode === 'create') {
            form.post(route('admin.products.store'), opts);
        } else if (editing) {
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
                                                        <MediaGallery
                                                            kind="product"
                                                            itemId={p.id}
                                                            images={p.images}
                                                            label="Ảnh phụ"
                                                        />
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

                                {/* Tồn kho theo cửa hàng (per-store) — mỗi store đã chọn 1 ô số lượng */}
                                {form.data.service_location_ids.length > 0 && (
                                    <div className="mt-3 rounded-[11px] border border-cardBorder bg-[#f8faf4] p-3">
                                        <div className="mb-2 text-[12px] font-semibold text-pine">Số lượng tại mỗi cơ sở</div>
                                        <div className="flex flex-wrap gap-3">
                                            {form.data.service_location_ids.map((id) => {
                                                const loc = service_locations.find((l) => l.id === id);
                                                if (!loc) return null;
                                                return (
                                                    <label key={id} className="flex items-center gap-2 text-[13px]">
                                                        <span className="font-semibold text-pine">{loc.name}</span>
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            value={form.data.stocks[id] ?? ''}
                                                            onChange={(e) => setStock(id, e.target.value)}
                                                            placeholder="0"
                                                            className="w-20 rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] outline-none transition focus:border-grass"
                                                        />
                                                    </label>
                                                );
                                            })}
                                        </div>
                                        {Object.entries(form.errors)
                                            .filter(([k]) => k.startsWith('stocks'))
                                            .slice(0, 1)
                                            .map(([k, v]) => (
                                                <p key={k} className="mt-1.5 text-[12px] text-[#b3493a]">{v}</p>
                                            ))}
                                    </div>
                                )}
                            </div>

                            {/* Thường thuê cùng (US-08) — gợi ý hiện ở trang sản phẩm (Case 2) */}
                            <SortedProductPicker
                                label="Thường thuê cùng"
                                options={accessory_options}
                                selectedIds={form.data.accessory_ids}
                                excludeId={editing?.id}
                                onToggle={toggleAccessory}
                                errors={form.errors}
                                errorPrefix="accessory_ids"
                            />

                            {/* Có thể bạn cũng thích (Epic 1, 1.6) — section cuối trang sản phẩm */}
                            <SortedProductPicker
                                label="Có thể bạn cũng thích"
                                options={accessory_options}
                                selectedIds={form.data.related_ids}
                                excludeId={editing?.id}
                                onToggle={toggleRelated}
                                errors={form.errors}
                                errorPrefix="related_ids"
                            />

                            {/* Price / Qty / Deposit row */}
                            <div className="grid grid-cols-2 gap-3">
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

                            {/* Khung giờ nhận/trả riêng theo sản phẩm (bopcamping-n6mr) */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Khung giờ nhận/trả riêng
                                    <span className="ml-1 font-normal text-moss">(để trống = theo khung giờ chung của shop)</span>
                                </label>
                                <div className="flex flex-wrap gap-4">
                                    <label className="flex items-center gap-2 text-[13px] text-moss">
                                        <span>Nhận</span>
                                        <input
                                            type="number" min="0" max="23"
                                            value={form.data.pickup_hour}
                                            onChange={(e) => form.setData('pickup_hour', e.target.value === '' ? '' : Number(e.target.value))}
                                            placeholder="8"
                                            className="w-20 rounded-[10px] border border-cardBorder px-3 py-2 text-[13.5px] text-ink outline-none transition focus:border-grass"
                                        />
                                        <span className="text-[12px]">giờ</span>
                                    </label>
                                    <label className="flex items-center gap-2 text-[13px] text-moss">
                                        <span>Trả</span>
                                        <input
                                            type="number" min="0" max="23"
                                            value={form.data.return_hour}
                                            onChange={(e) => form.setData('return_hour', e.target.value === '' ? '' : Number(e.target.value))}
                                            placeholder="20"
                                            className="w-20 rounded-[10px] border border-cardBorder px-3 py-2 text-[13.5px] text-ink outline-none transition focus:border-grass"
                                        />
                                        <span className="text-[12px]">giờ</span>
                                    </label>
                                </div>
                                {(form.errors.pickup_hour || form.errors.return_hour) && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.pickup_hour || form.errors.return_hour}</p>
                                )}
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

                            {/* Thông số key–value (Epic 1, 1.2) — card "THÔNG SỐ" dưới ảnh ở trang sản phẩm */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Thông số
                                    <span className="ml-1 font-normal text-moss">(hiện thành bảng dưới ảnh sản phẩm)</span>
                                </label>
                                {form.data.specs.length > 0 && (
                                    <div className="mb-2 space-y-1.5">
                                        {form.data.specs.map((row, i) => (
                                            <div key={i} className="flex items-center gap-1.5">
                                                <input
                                                    type="text"
                                                    value={row.key}
                                                    onChange={(e) => setSpec(i, 'key', e.target.value)}
                                                    placeholder="VD: Sức chứa"
                                                    className="w-[38%] rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] outline-none transition focus:border-grass"
                                                />
                                                <input
                                                    type="text"
                                                    value={row.value}
                                                    onChange={(e) => setSpec(i, 'value', e.target.value)}
                                                    placeholder="VD: 4 người"
                                                    className="flex-1 rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] outline-none transition focus:border-grass"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => removeSpecRow(i)}
                                                    title="Xoá dòng"
                                                    className="grid h-8 w-8 flex-none place-items-center rounded-[8px] border border-[#f6ddd6] text-[13px] font-bold text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                                >
                                                    ×
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <button
                                    type="button"
                                    onClick={addSpecRow}
                                    className="rounded-[8px] border border-dashed border-cardBorder px-3 py-1.5 text-[12.5px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                >
                                    + Thêm dòng thông số
                                </button>
                                {Object.entries(form.errors)
                                    .filter(([k]) => k.startsWith('specs'))
                                    .slice(0, 1)
                                    .map(([k, v]) => (
                                        <p key={k} className="mt-1 text-[12px] text-[#b3493a]">{v}</p>
                                    ))}
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

/**
 * Picker chọn sản phẩm có thứ tự (dùng cho "Thường thuê cùng" + "Có thể bạn cũng thích"):
 * search → click chọn, thứ tự click = thứ tự hiển thị, chip đánh số kèm nút bỏ chọn.
 */
function SortedProductPicker({
    label,
    options,
    selectedIds,
    excludeId,
    onToggle,
    errors,
    errorPrefix,
}: {
    label: string;
    options: AccessoryOption[];
    selectedIds: number[];
    excludeId?: number;
    onToggle: (id: number) => void;
    errors: Record<string, string>;
    errorPrefix: string;
}) {
    const [search, setSearch] = useState('');
    const nameOf = (id: number) => options.find((o) => o.id === id)?.name ?? `#${id}`;

    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                {label}
                <span className="ml-1 font-normal text-moss">(thứ tự chọn = thứ tự hiển thị)</span>
            </label>

            {/* Chip các món đã chọn, đúng thứ tự */}
            {selectedIds.length > 0 && (
                <div className="mb-2 flex flex-wrap gap-1.5">
                    {selectedIds.map((id, i) => (
                        <span key={id} className="flex items-center gap-1.5 rounded-pill bg-[#eef5e1] py-1 pl-2.5 pr-1.5 text-[12px] font-semibold text-grass">
                            <span className="font-mono text-[10.5px] text-moss">{i + 1}.</span>
                            {nameOf(id)}
                            <button
                                type="button"
                                onClick={() => onToggle(id)}
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
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Tìm sản phẩm để thêm gợi ý…"
                className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2 text-[13px] outline-none transition focus:border-grass"
            />
            <div className="mt-1.5 max-h-[160px] overflow-y-auto rounded-[10px] border border-cardBorder">
                {options
                    .filter((o) => o.id !== excludeId)
                    .filter((o) => o.name.toLowerCase().includes(search.toLowerCase()))
                    .map((o) => {
                        const on = selectedIds.includes(o.id);
                        return (
                            <button
                                type="button"
                                key={o.id}
                                onClick={() => onToggle(o.id)}
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
            {Object.entries(errors)
                .filter(([k]) => k.startsWith(errorPrefix))
                .map(([k, v]) => (
                    <p key={k} className="mt-1 text-[12px] text-[#b3493a]">{v}</p>
                ))}
        </div>
    );
}

AdminProducts.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
