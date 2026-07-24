import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import MediaGallery from '@/Components/admin/MediaGallery';
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
    thumbnail: string | null;
    status: 'active' | 'hidden';
    category: { id: number; name: string } | null;
    service_location_ids: number[];
    stocks: Record<number, number>;
    buffers: Record<number, number>;
    accessory_ids: number[];
    related_ids: number[];
    combo_names: string[];
    images: ProductImage[];
};

type ProductFormData = {
    name: string;
    category_id: number | '';
    description: string;
    specs: SpecRow[];
    price_per_day: number | '';
    deposit: number | null;
    status: 'active' | 'hidden';
    thumbnail: File | null;
    service_location_ids: number[];
    stocks: Record<number, number | ''>;
    // Đệm giặt/phơi theo kho (adr_turnaround_buffer): service_location_id -> số ngày
    buffers: Record<number, number | ''>;
    accessory_ids: number[];
    related_ids: number[];
};

/**
 * Màn THÊM / SỬA sản phẩm — trang riêng (thay cho popup cũ, vốn quá bé).
 * Cùng 1 form dùng cho create (không có `product`) lẫn edit (có `product`).
 * Ảnh phụ (MediaGallery) chỉ hiện ở chế độ sửa vì cần product đã tồn tại.
 */
export default function AdminProductForm({
    product,
    categories,
    service_locations,
    accessory_options,
}: {
    product?: Product;
    categories: CategoryOption[];
    service_locations: ServiceLocationOption[];
    accessory_options: AccessoryOption[];
}) {
    const { flash } = usePage<PageProps>().props;
    const isEdit = !!product;

    const [toastMsg, setToastMsg] = useState('');
    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const openLocationIds = service_locations.filter((l) => l.status === 'open').map((l) => l.id);

    const form = useForm<ProductFormData>(
        product
            ? {
                  name: product.name,
                  category_id: product.category?.id ?? '',
                  description: product.description ?? '',
                  specs: product.specs ?? [],
                  price_per_day: product.price_per_day,
                  deposit: product.deposit ?? null,
                  status: product.status,
                  thumbnail: null,
                  service_location_ids: product.service_location_ids ?? [],
                  stocks: { ...(product.stocks ?? {}) },
                  buffers: { ...(product.buffers ?? {}) },
                  accessory_ids: product.accessory_ids ?? [],
                  related_ids: product.related_ids ?? [],
              }
            : {
                  name: '',
                  category_id: categories[0]?.id ?? '',
                  description: '',
                  specs: [],
                  price_per_day: '',
                  deposit: null,
                  status: 'active',
                  thumbnail: null,
                  // Mặc định gắn tất cả vị trí đang mở khi thêm mới.
                  service_location_ids: [...openLocationIds],
                  stocks: {},
                  buffers: {},
                  accessory_ids: [],
                  related_ids: [],
              },
    );

    const toggleLocation = (id: number) => {
        const cur = form.data.service_location_ids;
        form.setData('service_location_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    const setStock = (id: number, value: string) => {
        form.setData('stocks', { ...form.data.stocks, [id]: value === '' ? '' : Math.max(0, Number(value)) });
    };

    const setBuffer = (id: number, value: string) => {
        form.setData('buffers', { ...form.data.buffers, [id]: value === '' ? '' : Math.min(30, Math.max(0, Number(value))) });
    };

    const toggleAccessory = (id: number) => {
        const cur = form.data.accessory_ids;
        form.setData('accessory_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    const toggleRelated = (id: number) => {
        const cur = form.data.related_ids;
        form.setData('related_ids', cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]);
    };

    const setSpec = (i: number, field: keyof SpecRow, value: string) => {
        form.setData('specs', form.data.specs.map((row, idx) => (idx === i ? { ...row, [field]: value } : row)));
    };
    const addSpecRow = () => form.setData('specs', [...form.data.specs, { key: '', value: '' }]);
    const removeSpecRow = (i: number) => form.setData('specs', form.data.specs.filter((_, idx) => idx !== i));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Transform: chuẩn hoá kiểu trước khi gửi (chỉ 1 callback — gom hết một chỗ).
        // FormData không gửi được mảng rỗng → gửi '' để backend hiểu "xoá hết".
        form.transform((data) => ({
            ...data,
            category_id: data.category_id === '' ? '' : Number(data.category_id),
            accessory_ids: data.accessory_ids.length ? data.accessory_ids : '',
            related_ids: data.related_ids.length ? data.related_ids : '',
            specs: data.specs.length ? data.specs : '',
            // Tồn kho: chỉ gửi số của store đã tick.
            stocks: Object.fromEntries(
                data.service_location_ids.map((id) => [id, data.stocks[id] === '' || data.stocks[id] == null ? 0 : data.stocks[id]]),
            ),
            // Đệm giặt/phơi theo kho — cùng quy tắc: chỉ gửi số của store đã tick.
            buffers: Object.fromEntries(
                data.service_location_ids.map((id) => [id, data.buffers[id] === '' || data.buffers[id] == null ? 0 : data.buffers[id]]),
            ),
            // PHP không nạp $_FILES cho PUT → POST kèm _method spoofing khi sửa.
            ...(isEdit ? { _method: 'put' } : {}),
        }));

        if (isEdit && product) {
            form.post(route('admin.products.update', product.id), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => form.setData('thumbnail', null),
            });
        } else {
            // Tạo xong controller redirect sang màn sửa (để thêm ảnh phụ).
            form.post(route('admin.products.store'), { forceFormData: true });
        }
    };

    const cardCls = 'rounded-[16px] border border-cardBorder bg-white p-5';
    const sectionTitle = 'mb-4 text-[14px] font-extrabold text-pine';

    return (
        <div className="p-6">
            <Head title={isEdit ? `Sửa — ${product!.name}` : 'Thêm sản phẩm'} />

            <div className="mx-auto max-w-[960px]">
                {/* Breadcrumb + header */}
                <div className="mb-1 text-[12.5px] text-moss">
                    <Link href={route('admin.products')} className="font-semibold text-grass hover:underline">
                        Sản phẩm
                    </Link>
                    {' / '}
                    <span>{isEdit ? product!.name : 'Thêm mới'}</span>
                </div>
                <div className="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <h1 className="text-[22px] font-extrabold tracking-tight text-pine">
                        {isEdit ? 'Sửa sản phẩm' : 'Thêm sản phẩm'}
                    </h1>
                    {isEdit && (
                        <div className="flex gap-2">
                            <button
                                onClick={() => router.get(route('admin.products.content.edit', product!.id))}
                                className="relative rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                            >
                                Nội dung chi tiết
                                {product!.has_setup_content && (
                                    <span className="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-grass ring-2 ring-white" title="Đã có nội dung" />
                                )}
                            </button>
                            <a
                                href={route('products.show', product!.slug)}
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                            >
                                Xem trang khách ↗
                            </a>
                        </div>
                    )}
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid gap-5 lg:grid-cols-2">
                        {/* Thông tin cơ bản */}
                        <div className={cardCls}>
                            <h2 className={sectionTitle}>Thông tin cơ bản</h2>
                            <div className="space-y-4">
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
                                    {form.errors.name && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.name}</p>}
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Danh mục <span className="text-[#b3493a]">*</span>
                                    </label>
                                    <select
                                        value={form.data.category_id}
                                        onChange={(e) => form.setData('category_id', e.target.value === '' ? '' : Number(e.target.value))}
                                        className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    >
                                        <option value="">Chọn danh mục</option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                    {form.errors.category_id && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.category_id}</p>}
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">Mô tả</label>
                                    <textarea
                                        value={form.data.description}
                                        onChange={(e) => form.setData('description', e.target.value)}
                                        rows={4}
                                        className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                        placeholder="Mô tả sản phẩm, tính năng nổi bật..."
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Giá, trạng thái, ảnh đại diện */}
                        <div className={cardCls}>
                            <h2 className={sectionTitle}>Giá & hiển thị</h2>
                            <div className="space-y-4">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                            Giá/ngày (₫) <span className="text-[#b3493a]">*</span>
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            value={form.data.price_per_day}
                                            onChange={(e) => form.setData('price_per_day', e.target.value === '' ? '' : Number(e.target.value))}
                                            className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                            placeholder="50000"
                                        />
                                        {form.errors.price_per_day && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.price_per_day}</p>}
                                    </div>
                                    <div>
                                        <label className="mb-1.5 block text-[13px] font-semibold text-pine">Tiền cọc (₫)</label>
                                        <input
                                            type="number"
                                            min="0"
                                            value={form.data.deposit ?? ''}
                                            onChange={(e) => form.setData('deposit', e.target.value === '' ? null : Number(e.target.value))}
                                            className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                            placeholder="200000"
                                        />
                                    </div>
                                </div>

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
                                                <span className="text-[13px] text-pine">{s === 'active' ? 'Đang bán' : 'Ẩn'}</span>
                                            </label>
                                        ))}
                                    </div>
                                    {/* US-07: ẩn sản phẩm đang thuộc combo → combo tự ẩn theo */}
                                    {form.data.status === 'hidden' && product && product.combo_names.length > 0 && (
                                        <p className="mt-2 rounded-[9px] bg-[#fdf3f1] px-3.5 py-2.5 text-[12.5px] font-semibold text-[#b3493a]">
                                            ⚠ Sản phẩm này thuộc combo: {product.combo_names.join(', ')}. Ẩn sản phẩm sẽ tự ẩn các combo đó khỏi trang bán.
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Ảnh đại diện
                                        {isEdit && product!.thumbnail && <span className="ml-1 font-normal text-moss">(để trống = giữ ảnh cũ)</span>}
                                    </label>
                                    {isEdit && product!.thumbnail && (
                                        <img src={product!.thumbnail} alt="" className="mb-2 h-16 w-16 rounded-[9px] border border-cardBorder object-cover" />
                                    )}
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={(e) => form.setData('thumbnail', e.target.files?.[0] ?? null)}
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] file:mr-3 file:rounded-[7px] file:border-0 file:bg-[#f1f4ea] file:px-3 file:py-1 file:text-[12px] file:font-semibold file:text-pine"
                                    />
                                    {form.errors.thumbnail && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.thumbnail}</p>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Vị trí phục vụ + tồn kho theo cơ sở */}
                    <div className={cardCls}>
                        <h2 className={sectionTitle}>
                            Vị trí phục vụ & tồn kho <span className="text-[#b3493a]">*</span>
                        </h2>
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
                        {form.errors.service_location_ids && <p className="mt-1 text-[12px] text-[#b3493a]">{form.errors.service_location_ids}</p>}

                        {form.data.service_location_ids.length > 0 && (
                            <div className="mt-3 rounded-[11px] border border-cardBorder bg-[#f8faf4] p-3">
                                <div className="mb-2 text-[12px] font-semibold text-pine">
                                    Số lượng & ngày giặt/phơi tại mỗi cơ sở
                                    <span className="ml-1 font-normal text-moss">(ngày phơi = số ngày chừa sau khi trả trước khi cho thuê lại; 0 = cho thuê ngay hôm sau)</span>
                                </div>
                                <div className="flex flex-wrap gap-x-6 gap-y-3">
                                    {form.data.service_location_ids.map((id) => {
                                        const loc = service_locations.find((l) => l.id === id);
                                        if (!loc) return null;
                                        return (
                                            <div key={id} className="flex items-center gap-2 text-[13px]">
                                                <span className="font-semibold text-pine">{loc.name}</span>
                                                <label className="flex items-center gap-1 text-moss">
                                                    <span className="text-[11.5px]">SL</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        value={form.data.stocks[id] ?? ''}
                                                        onChange={(e) => setStock(id, e.target.value)}
                                                        placeholder="0"
                                                        className="w-16 rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] text-ink outline-none transition focus:border-grass"
                                                    />
                                                </label>
                                                <label className="flex items-center gap-1 text-moss">
                                                    <span className="text-[11.5px]">ngày phơi</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        max="30"
                                                        value={form.data.buffers[id] ?? ''}
                                                        onChange={(e) => setBuffer(id, e.target.value)}
                                                        placeholder="0"
                                                        className="w-16 rounded-[9px] border border-cardBorder px-2.5 py-1.5 text-[13px] text-ink outline-none transition focus:border-grass"
                                                    />
                                                </label>
                                            </div>
                                        );
                                    })}
                                </div>
                                {Object.entries(form.errors)
                                    .filter(([k]) => k.startsWith('stocks') || k.startsWith('buffers'))
                                    .slice(0, 1)
                                    .map(([k, v]) => (
                                        <p key={k} className="mt-1.5 text-[12px] text-[#b3493a]">{v}</p>
                                    ))}
                            </div>
                        )}
                    </div>

                    {/* Gợi ý sản phẩm */}
                    <div className={cardCls}>
                        <h2 className={sectionTitle}>Gợi ý liên quan</h2>
                        <div className="grid gap-5 md:grid-cols-2">
                            <SortedProductPicker
                                label="Thường thuê cùng"
                                options={accessory_options}
                                selectedIds={form.data.accessory_ids}
                                excludeId={product?.id}
                                onToggle={toggleAccessory}
                                errors={form.errors}
                                errorPrefix="accessory_ids"
                            />
                            <SortedProductPicker
                                label="Có thể bạn cũng thích"
                                options={accessory_options}
                                selectedIds={form.data.related_ids}
                                excludeId={product?.id}
                                onToggle={toggleRelated}
                                errors={form.errors}
                                errorPrefix="related_ids"
                            />
                        </div>
                    </div>

                    {/* Thông số kỹ thuật */}
                    <div className={cardCls}>
                        <h2 className={sectionTitle}>
                            Thông số <span className="ml-1 text-[12px] font-normal text-moss">(hiện thành bảng dưới ảnh sản phẩm)</span>
                        </h2>
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

                    {/* Ảnh phụ — chỉ ở chế độ sửa (gallery cần product đã tồn tại) */}
                    {isEdit && product && (
                        <div className={cardCls}>
                            <h2 className={sectionTitle}>Ảnh phụ (gallery)</h2>
                            <MediaGallery kind="product" itemId={product.id} images={product.images} label="Ảnh phụ" reloadOnly={['product', 'flash']} />
                        </div>
                    )}

                    {/* Actions */}
                    <div className="sticky bottom-0 -mx-6 flex items-center justify-end gap-3 border-t border-cardBorder bg-[#faf9f4]/95 px-6 py-4 backdrop-blur">
                        <Link
                            href={route('admin.products')}
                            className="rounded-[10px] border border-cardBorder px-5 py-2 text-[13px] font-semibold text-pine transition hover:bg-[#f1f4ea]"
                        >
                            {isEdit ? 'Quay lại' : 'Huỷ'}
                        </Link>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-[10px] bg-grass px-6 py-2 text-[13px] font-bold text-white transition hover:bg-pine disabled:opacity-60"
                        >
                            {form.processing ? 'Đang lưu…' : isEdit ? 'Lưu thay đổi' : 'Tạo sản phẩm'}
                        </button>
                    </div>
                </form>
            </div>

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">
                    ✓ {toastMsg}
                </div>
            )}
        </div>
    );
}

/**
 * Picker chọn sản phẩm có thứ tự ("Thường thuê cùng" + "Có thể bạn cũng thích"):
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

AdminProductForm.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
