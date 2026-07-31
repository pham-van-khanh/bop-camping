import MediaGallery from '@/Components/admin/MediaGallery';
import AdminLayout from '@/Layouts/AdminLayout';
import ComboLocationPicker, {
    type PickerLocation,
} from '@/Pages/Admin/combo/ComboLocationPicker';
import { money } from '@/lib/format';
import type { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useEffect, useMemo, useState } from 'react';

type ComboImage = {
    id: number;
    path: string;
    sort_order: number;
    type: 'image' | 'video';
};
type ProductOption = {
    id: number;
    name: string;
    price_per_day: number;
    quantity: number;
    status: 'active' | 'hidden';
    // Cơ sở mà sản phẩm phục vụ — picker tính ngay được cơ sở nào gán được cho combo.
    service_location_ids: number[];
};
type ComboItem = {
    product_id: number;
    quantity: number;
    product_name: string | null;
    price_per_day: number | null;
    product_status: 'active' | 'hidden' | null;
};
type Combo = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    combo_price: number;
    deposit: number | null;
    suitable_for: number | null;
    is_active: boolean;
    sort_order: number;
    sum_individual: number;
    savings_amount: number;
    savings_percent: number;
    items: ComboItem[];
    images: ComboImage[];
    service_location_ids: number[];
};

type ComboFormData = {
    name: string;
    description: string;
    combo_price: number | '';
    deposit: number | null;
    suitable_for: number | null;
    is_active: boolean;
    sort_order: number;
    items: { product_id: number; quantity: number }[];
    service_location_ids: number[];
    confirm_over_price: boolean;
};

export default function AdminCombos({
    combos,
    products,
    service_locations,
    location_stock,
}: {
    combos: Combo[];
    products: ProductOption[];
    service_locations: PickerLocation[];
    location_stock: Record<number, Record<number, number>>;
}) {
    const { flash } = usePage<PageProps>().props;

    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<Combo | null>(null);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
    const [toastMsg, setToastMsg] = useState('');
    const [productSearch, setProductSearch] = useState('');

    useEffect(() => {
        if (flash.success) {
            setToastMsg(flash.success);
            const t = setTimeout(() => setToastMsg(''), 3500);
            return () => clearTimeout(t);
        }
    }, [flash.success]);

    const form = useForm<ComboFormData>({
        name: '',
        description: '',
        combo_price: '',
        deposit: null,
        suitable_for: null,
        is_active: true,
        sort_order: 0,
        items: [],
        service_location_ids: [],
        confirm_over_price: false,
    });

    const productById = useMemo(
        () => new Map(products.map((p) => [p.id, p])),
        [products],
    );

    // Preview live PRD 5.2: tổng giá lẻ + tiết kiệm tự tính, admin chỉ nhập giá combo
    const sumIndividual = form.data.items.reduce(
        (sum, item) =>
            sum +
            (productById.get(item.product_id)?.price_per_day ?? 0) *
                item.quantity,
        0,
    );
    const comboPrice = form.data.combo_price === '' ? 0 : form.data.combo_price;
    const savings = sumIndividual - comboPrice;
    const savingsPercent =
        sumIndividual > 0 ? Math.round((savings * 100) / sumIndividual) : 0;
    const overPriced =
        form.data.items.length > 0 &&
        form.data.combo_price !== '' &&
        comboPrice >= sumIndividual;

    const filteredProducts = products.filter(
        (p) =>
            !form.data.items.some((item) => item.product_id === p.id) &&
            p.name.toLowerCase().includes(productSearch.toLowerCase()),
    );

    const addItem = (productId: number) => {
        form.setData('items', [
            ...form.data.items,
            { product_id: productId, quantity: 1 },
        ]);
        setProductSearch('');
    };

    const removeItem = (productId: number) => {
        form.setData(
            'items',
            form.data.items.filter((item) => item.product_id !== productId),
        );
    };

    const setItemQuantity = (productId: number, quantity: number) => {
        form.setData(
            'items',
            form.data.items.map((item) =>
                item.product_id === productId ? { ...item, quantity } : item,
            ),
        );
    };

    const openCreate = () => {
        form.setData({
            name: '',
            description: '',
            combo_price: '',
            deposit: null,
            suitable_for: null,
            is_active: true,
            sort_order: 0,
            items: [],
            service_location_ids: [],
            confirm_over_price: false,
        });
        form.clearErrors();
        setProductSearch('');
        setEditing(null);
        setModalMode('create');
    };

    const openEdit = (c: Combo) => {
        form.setData({
            name: c.name,
            description: c.description ?? '',
            combo_price: c.combo_price,
            deposit: c.deposit ?? null,
            suitable_for: c.suitable_for ?? null,
            is_active: c.is_active,
            sort_order: c.sort_order,
            items: c.items.map((item) => ({
                product_id: item.product_id,
                quantity: item.quantity,
            })),
            service_location_ids: c.service_location_ids ?? [],
            confirm_over_price: false,
        });
        form.clearErrors();
        setProductSearch('');
        setEditing(c);
        setModalMode('edit');
    };

    const closeModal = () => {
        setModalMode(null);
        setEditing(null);
        form.reset();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { preserveScroll: true, onSuccess: closeModal };
        if (modalMode === 'create') {
            form.post(route('admin.combos.store'), opts);
        } else if (editing) {
            form.put(route('admin.combos.update', editing.id), opts);
        }
    };

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.combos.destroy', deleteId), {
            preserveScroll: true,
            onSuccess: () => setDeleteId(null),
        });
    };

    return (
        <>
            <Head title="Admin · Combo" />

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">
                    ✓ {toastMsg}
                </div>
            )}

            <div className="p-6">
                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">
                            Combo
                        </h1>
                        <p className="mt-0.5 text-[13px] text-moss">
                            <span className="font-mono">{combos.length}</span>{' '}
                            combo · click vào hàng để quản lý ảnh
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
                        Thêm combo
                    </button>
                </div>

                <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                    {combos.length === 0 ? (
                        <div className="py-16 text-center text-moss">
                            <div className="mb-2 text-[32px]">🎒</div>
                            <div className="text-[14px]">Chưa có combo nào</div>
                            <button
                                onClick={openCreate}
                                className="mt-3 text-[13px] font-semibold text-grass underline"
                            >
                                Tạo combo đầu tiên
                            </button>
                        </div>
                    ) : (
                        <table className="w-full text-[13px]">
                            <thead>
                                <tr
                                    className="border-b border-[#eef2e3]"
                                    style={{ background: '#f8faf4' }}
                                >
                                    <th className="px-4 py-3 text-left font-semibold text-moss">
                                        Combo
                                    </th>
                                    <th className="hidden px-4 py-3 text-center font-semibold text-moss sm:table-cell">
                                        Số món
                                    </th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">
                                        Giá combo/ngày
                                    </th>
                                    <th className="hidden px-4 py-3 text-right font-semibold text-moss md:table-cell">
                                        Tổng giá lẻ
                                    </th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">
                                        Tiết kiệm
                                    </th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">
                                        Trạng thái
                                    </th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {combos.map((c) => {
                                    const expanded = expandedId === c.id;
                                    return (
                                        <Fragment key={c.id}>
                                            <tr
                                                className="cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7]"
                                                onClick={() =>
                                                    setExpandedId(
                                                        expanded ? null : c.id,
                                                    )
                                                }
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-pine">
                                                        {c.name}
                                                    </div>
                                                    <div className="font-mono text-[11px] text-moss">
                                                        {c.slug}
                                                        {c.suitable_for
                                                            ? ` · ${c.suitable_for} người`
                                                            : ''}
                                                    </div>
                                                </td>
                                                <td className="hidden px-4 py-3 text-center font-mono font-bold text-pine sm:table-cell">
                                                    {c.items.length}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="font-mono font-bold text-pine">
                                                        {money(c.combo_price)}
                                                    </div>
                                                    {c.deposit ? (
                                                        <div className="font-mono text-[11px] text-campfire">
                                                            cọc{' '}
                                                            {money(c.deposit)}
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="hidden px-4 py-3 text-right font-mono text-moss line-through md:table-cell">
                                                    {money(c.sum_individual)}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    {c.savings_amount > 0 ? (
                                                        <span className="rounded-pill bg-[#eef5e1] px-2 py-1 font-mono text-[12px] font-bold text-grass">
                                                            −{c.savings_percent}
                                                            %
                                                        </span>
                                                    ) : (
                                                        <span className="rounded-pill bg-[#f6ddd6] px-2 py-1 font-mono text-[12px] font-bold text-[#b3493a]">
                                                            0%
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    <span
                                                        className={`rounded-pill px-2.5 py-1 text-[11.5px] font-bold ${
                                                            c.is_active
                                                                ? 'bg-[#eef5e1] text-grass'
                                                                : 'bg-[#f1f4ea] text-moss'
                                                        }`}
                                                    >
                                                        {c.is_active
                                                            ? 'Đang bán'
                                                            : 'Ẩn'}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div
                                                        className="flex items-center justify-end gap-2"
                                                        onClick={(e) =>
                                                            e.stopPropagation()
                                                        }
                                                    >
                                                        <button
                                                            onClick={() =>
                                                                openEdit(c)
                                                            }
                                                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                        >
                                                            Sửa
                                                        </button>
                                                        <button
                                                            onClick={() =>
                                                                setDeleteId(
                                                                    c.id,
                                                                )
                                                            }
                                                            className="rounded-[8px] border border-[#f6ddd6] px-3 py-1.5 text-[12px] font-semibold text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                                        >
                                                            Xoá
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>

                                            {expanded && (
                                                <tr className="border-b border-[#f1f4ea]">
                                                    <td
                                                        colSpan={7}
                                                        className="px-6 pb-5 pt-3"
                                                        style={{
                                                            background:
                                                                '#fafcf7',
                                                        }}
                                                    >
                                                        {/* Danh sách món trong combo */}
                                                        <div className="mb-4">
                                                            <div className="mb-2 text-[12.5px] font-semibold text-moss">
                                                                Món trong combo
                                                                (
                                                                {c.items.length}
                                                                )
                                                            </div>
                                                            <div className="flex flex-wrap gap-2">
                                                                {c.items.map(
                                                                    (item) => (
                                                                        <span
                                                                            key={
                                                                                item.product_id
                                                                            }
                                                                            className={`rounded-[9px] border px-3 py-1.5 text-[12.5px] font-semibold ${
                                                                                item.product_status ===
                                                                                'hidden'
                                                                                    ? 'border-[#f6ddd6] bg-[#fdf3f1] text-[#b3493a]'
                                                                                    : 'border-cardBorder bg-white text-pine'
                                                                            }`}
                                                                        >
                                                                            {
                                                                                item.quantity
                                                                            }
                                                                            ×{' '}
                                                                            {item.product_name ??
                                                                                '(đã xoá)'}
                                                                            {item.price_per_day !=
                                                                                null && (
                                                                                <span className="ml-1 font-mono text-[11px] font-normal text-moss">
                                                                                    {money(
                                                                                        item.price_per_day,
                                                                                    )}
                                                                                    /ngày
                                                                                </span>
                                                                            )}
                                                                            {item.product_status ===
                                                                                'hidden' &&
                                                                                ' · đang ẩn'}
                                                                        </span>
                                                                    ),
                                                                )}
                                                            </div>
                                                        </div>

                                                        {/* Ảnh combo */}
                                                        <MediaGallery
                                                            kind="combo"
                                                            itemId={c.id}
                                                            images={c.images}
                                                            label="Ảnh combo"
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
            </div>

            {/* Create / Edit Modal */}
            {modalMode && (
                <div
                    className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8"
                    onClick={closeModal}
                >
                    <div
                        className="w-full max-w-xl rounded-[18px] bg-white p-6 shadow-xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <h2 className="mb-5 text-[18px] font-extrabold text-pine">
                            {modalMode === 'create'
                                ? 'Thêm combo'
                                : 'Sửa combo'}
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Tên combo{' '}
                                    <span className="text-[#b3493a]">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="VD: Combo Gia Đình 4 người"
                                    autoFocus
                                />
                                {form.errors.name && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>

                            {/* Chọn món */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Sản phẩm trong combo{' '}
                                    <span className="text-[#b3493a]">*</span>
                                    <span className="ml-1 font-normal text-moss">
                                        (khuyến nghị ≤ 8 món)
                                    </span>
                                </label>

                                {form.data.items.length > 0 && (
                                    <div className="mb-2 space-y-1.5">
                                        {form.data.items.map((item) => {
                                            const p = productById.get(
                                                item.product_id,
                                            );
                                            return (
                                                <div
                                                    key={item.product_id}
                                                    className="flex items-center gap-2 rounded-[10px] border border-cardBorder bg-[#fafcf7] px-3 py-2"
                                                >
                                                    <span className="flex-1 text-[13px] font-semibold text-pine">
                                                        {p?.name ??
                                                            `#${item.product_id}`}
                                                        {p?.status ===
                                                            'hidden' && (
                                                            <span className="ml-1.5 rounded-pill bg-[#f6ddd6] px-1.5 py-0.5 text-[10px] font-bold text-[#b3493a]">
                                                                đang ẩn
                                                            </span>
                                                        )}
                                                        <span className="ml-1.5 font-mono text-[11px] font-normal text-moss">
                                                            {money(
                                                                p?.price_per_day ??
                                                                    0,
                                                            )}
                                                            /ngày · kho{' '}
                                                            {p?.quantity ?? '?'}
                                                        </span>
                                                    </span>
                                                    <input
                                                        type="number"
                                                        min={1}
                                                        max={100}
                                                        value={item.quantity}
                                                        onChange={(e) =>
                                                            setItemQuantity(
                                                                item.product_id,
                                                                Math.max(
                                                                    1,
                                                                    Number(
                                                                        e.target
                                                                            .value,
                                                                    ),
                                                                ),
                                                            )
                                                        }
                                                        className="w-16 rounded-[8px] border border-cardBorder px-2 py-1.5 text-center font-mono text-[13px] outline-none focus:border-grass"
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeItem(
                                                                item.product_id,
                                                            )
                                                        }
                                                        className="grid h-7 w-7 place-items-center rounded-[8px] text-[#b3493a] transition hover:bg-[#f6ddd6]"
                                                        title="Bỏ khỏi combo"
                                                    >
                                                        ×
                                                    </button>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}

                                <input
                                    type="text"
                                    value={productSearch}
                                    onChange={(e) =>
                                        setProductSearch(e.target.value)
                                    }
                                    className="w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    placeholder="Gõ tên sản phẩm để thêm vào combo…"
                                />
                                {productSearch && (
                                    <div className="mt-1 max-h-44 overflow-y-auto rounded-[10px] border border-cardBorder bg-white shadow-sm">
                                        {filteredProducts.length === 0 ? (
                                            <div className="px-3.5 py-2.5 text-[12.5px] text-moss">
                                                Không tìm thấy sản phẩm phù hợp
                                            </div>
                                        ) : (
                                            filteredProducts
                                                .slice(0, 8)
                                                .map((p) => (
                                                    <button
                                                        type="button"
                                                        key={p.id}
                                                        onClick={() =>
                                                            addItem(p.id)
                                                        }
                                                        className="flex w-full items-center justify-between px-3.5 py-2.5 text-left text-[13px] transition hover:bg-[#f1f4ea]"
                                                    >
                                                        <span className="font-semibold text-pine">
                                                            {p.name}
                                                            {p.status ===
                                                                'hidden' && (
                                                                <span className="ml-1.5 rounded-pill bg-[#f6ddd6] px-1.5 py-0.5 text-[10px] font-bold text-[#b3493a]">
                                                                    đang ẩn
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="font-mono text-[11.5px] text-moss">
                                                            {money(
                                                                p.price_per_day,
                                                            )}
                                                            /ngày
                                                        </span>
                                                    </button>
                                                ))
                                        )}
                                    </div>
                                )}
                                {form.errors.items && (
                                    <p className="mt-1 text-[12px] text-[#b3493a]">
                                        {form.errors.items}
                                    </p>
                                )}
                            </div>

                            {/* Cơ sở bán combo (bopcamping-dwa5) — đặt NGAY SAU phần chọn món vì
                                cơ sở gán được phụ thuộc vào món đang chọn. */}
                            <div>
                                <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                    Bán tại cơ sở{' '}
                                    <span className="text-[#b3493a]">*</span>
                                    <span className="ml-1 font-normal text-moss">
                                        (chỉ cơ sở phục vụ đủ mọi món)
                                    </span>
                                </label>
                                <ComboLocationPicker
                                    locations={service_locations}
                                    locationStock={location_stock}
                                    products={products}
                                    items={form.data.items}
                                    value={form.data.service_location_ids}
                                    onChange={(ids) =>
                                        form.setData(
                                            'service_location_ids',
                                            ids,
                                        )
                                    }
                                    onAutoDeselect={(removed) =>
                                        setToastMsg(
                                            `Đã bỏ chọn ${removed.map((l) => l.name).join(', ')} vì món vừa đổi không phục vụ ở đó.`,
                                        )
                                    }
                                    error={form.errors.service_location_ids}
                                />
                            </div>

                            {/* Giá + cọc + số người */}
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Giá combo/ngày (₫){' '}
                                        <span className="text-[#b3493a]">
                                            *
                                        </span>
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.combo_price}
                                        onChange={(e) =>
                                            form.setData(
                                                'combo_price',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                        placeholder="150000"
                                    />
                                    {form.errors.combo_price && (
                                        <p className="mt-1 text-[12px] text-[#b3493a]">
                                            {form.errors.combo_price}
                                        </p>
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
                                            form.setData(
                                                'deposit',
                                                e.target.value === ''
                                                    ? null
                                                    : Number(e.target.value),
                                            )
                                        }
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                        placeholder="500000"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Số người phù hợp
                                    </label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="100"
                                        value={form.data.suitable_for ?? ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'suitable_for',
                                                e.target.value === ''
                                                    ? null
                                                    : Number(e.target.value),
                                            )
                                        }
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                        placeholder="4"
                                    />
                                </div>
                            </div>

                            {/* Preview tiết kiệm (PRD 5.2 — tự tính, không nhập tay) */}
                            {form.data.items.length > 0 && (
                                <div
                                    className={`rounded-[12px] border px-4 py-3 text-[13px] ${
                                        overPriced
                                            ? 'border-[#f0c9c0] bg-[#fdf3f1]'
                                            : 'border-[#dcebc4] bg-[#f6faee]'
                                    }`}
                                >
                                    <div className="flex items-center justify-between">
                                        <span className="text-moss">
                                            Tổng giá thuê lẻ
                                        </span>
                                        <span className="font-mono font-bold text-pine">
                                            {money(sumIndividual)}/ngày
                                        </span>
                                    </div>
                                    {form.data.combo_price !== '' && (
                                        <div className="mt-1 flex items-center justify-between">
                                            <span className="text-moss">
                                                Khách tiết kiệm
                                            </span>
                                            {overPriced ? (
                                                <span className="font-mono font-bold text-[#b3493a]">
                                                    không tiết kiệm
                                                </span>
                                            ) : (
                                                <span className="font-mono font-bold text-grass">
                                                    {money(savings)}/ngày (−
                                                    {savingsPercent}%)
                                                </span>
                                            )}
                                        </div>
                                    )}
                                    {overPriced && (
                                        <label className="mt-2 flex cursor-pointer items-center gap-2 text-[12.5px] font-semibold text-[#b3493a]">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    form.data.confirm_over_price
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'confirm_over_price',
                                                        e.target.checked,
                                                    )
                                                }
                                                className="accent-[#b3493a]"
                                            />
                                            Giá combo không rẻ hơn thuê lẻ — vẫn
                                            lưu (chủ đích)
                                        </label>
                                    )}
                                </div>
                            )}

                            {/* Trạng thái + thứ tự */}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Trạng thái
                                    </label>
                                    <div className="flex gap-3 pt-1.5">
                                        {([true, false] as const).map((v) => (
                                            <label
                                                key={String(v)}
                                                className="flex cursor-pointer items-center gap-2"
                                            >
                                                <input
                                                    type="radio"
                                                    name="is_active"
                                                    checked={
                                                        form.data.is_active ===
                                                        v
                                                    }
                                                    onChange={() =>
                                                        form.setData(
                                                            'is_active',
                                                            v,
                                                        )
                                                    }
                                                    className="accent-grass"
                                                />
                                                <span className="text-[13px] text-pine">
                                                    {v ? 'Đang bán' : 'Ẩn'}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                </div>
                                <div>
                                    <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                                        Thứ tự hiển thị{' '}
                                        <span className="font-normal text-moss">
                                            (nhỏ = lên trước)
                                        </span>
                                    </label>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.sort_order}
                                        onChange={(e) =>
                                            form.setData(
                                                'sort_order',
                                                Math.max(
                                                    0,
                                                    Number(e.target.value),
                                                ),
                                            )
                                        }
                                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2.5 text-[13.5px] outline-none transition focus:border-grass"
                                    />
                                </div>
                            </div>

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
                                    placeholder="Combo dành cho ai, gồm những gì nổi bật…"
                                />
                            </div>

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
                            Xác nhận xoá combo
                        </h2>
                        <p className="mb-4 text-[13px] text-moss">
                            Ảnh của combo cũng sẽ bị xoá; đơn hàng cũ không bị
                            ảnh hưởng. Hành động không thể hoàn tác.
                        </p>
                        <div className="flex justify-end gap-3">
                            <button
                                onClick={() => setDeleteId(null)}
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

AdminCombos.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
