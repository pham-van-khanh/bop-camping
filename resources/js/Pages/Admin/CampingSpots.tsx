import ServiceLocationsManager, {
    type ServiceLocation,
} from '@/Components/admin/ServiceLocationsManager';
import AdminLayout from '@/Layouts/AdminLayout';
import type { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Fragment, ReactNode, useEffect, useRef, useState } from 'react';

type Media = { id: number; type: 'image' | 'video'; url: string };
type Spot = {
    id: number;
    name: string;
    province: string;
    district: string | null;
    terrain_tag: string;
    description: string | null;
    map_url: string | null;
    best_season_from: number | null;
    best_season_to: number | null;
    season_label: string;
    nearest_service_location_id: number | null;
    nearest_name: string | null;
    is_suggested: boolean;
    sort_order: number;
    media: Media[];
};

type FormData = {
    name: string;
    province: string;
    district: string;
    terrain_tag: string;
    description: string;
    map_url: string;
    best_season_from: number | '';
    best_season_to: number | '';
    nearest_service_location_id: number | '';
    is_suggested: boolean;
    sort_order: number | '';
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
};

const TERRAIN_TAGS = [
    'Đồi cỏ',
    'Ven hồ',
    'Rừng núi',
    'Bãi biển',
    'Đồi cát',
    'Hồ trên núi',
    'Đỉnh núi',
    'Đồi view sông',
    'Rừng thông',
];
const MONTHS = Array.from({ length: 12 }, (_, i) => i + 1);

export default function AdminCampingSpots({
    spots,
    service_locations,
    provinces,
}: {
    spots: Paginator<Spot>;
    service_locations: ServiceLocation[];
    provinces: string[];
}) {
    const { flash } = usePage<PageProps>().props;

    const [modalMode, setModalMode] = useState<'create' | 'edit' | null>(null);
    const [editing, setEditing] = useState<Spot | null>(null);
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [deleteId, setDeleteId] = useState<number | null>(null);
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

    const blank = (): FormData => ({
        name: '',
        province: '',
        district: '',
        terrain_tag: '',
        description: '',
        map_url: '',
        best_season_from: '',
        best_season_to: '',
        nearest_service_location_id: '',
        is_suggested: false,
        sort_order: '',
    });

    const form = useForm<FormData>(blank());

    const openCreate = () => {
        form.setData(blank());
        form.clearErrors();
        setEditing(null);
        setModalMode('create');
    };

    const openEdit = (s: Spot) => {
        form.setData({
            name: s.name,
            province: s.province,
            district: s.district ?? '',
            terrain_tag: s.terrain_tag,
            description: s.description ?? '',
            map_url: s.map_url ?? '',
            best_season_from: s.best_season_from ?? '',
            best_season_to: s.best_season_to ?? '',
            nearest_service_location_id: s.nearest_service_location_id ?? '',
            is_suggested: s.is_suggested,
            sort_order: s.sort_order,
        });
        form.clearErrors();
        setEditing(s);
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
        if (modalMode === 'create')
            form.post(route('admin.camping-spots.store'), opts);
        else if (editing)
            form.put(route('admin.camping-spots.update', editing.id), opts);
    };

    const doDelete = () => {
        if (deleteId === null) return;
        router.delete(route('admin.camping-spots.destroy', deleteId), {
            preserveScroll: true,
            onSuccess: () => setDeleteId(null),
        });
    };

    // Lưu ở ref (không phải state) — set nó KHÔNG kích hoạt trạng thái "đang tải" trên
    // UI. Nếu setUploadingId ngay lúc bấm nút (trước khi biết có chọn file hay không),
    // khách Cancel hộp thoại chọn file thì onChange không bắn, không có gì reset lại
    // uploadingId → nút kẹt ở "Đang tải…" vĩnh viễn tới khi reload trang.
    const uploadTargetRef = useRef<number | null>(null);
    const triggerUpload = (id: number) => {
        uploadTargetRef.current = id;
        uploadRef.current?.click();
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const id = uploadTargetRef.current;
        if (!id || !e.target.files?.length) return;
        setUploadingId(id);
        const fd = new FormData();
        Array.from(e.target.files).forEach((f) => fd.append('media[]', f));
        router.post(route('admin.camping-spots.media.store', id), fd, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploadingId(null);
                e.target.value = '';
            },
        });
    };

    const deleteMedia = (spotId: number, mediaId: number) =>
        router.delete(
            route('admin.camping-spots.media.destroy', [spotId, mediaId]),
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Admin · Điểm cắm trại" />

            <input
                ref={uploadRef}
                type="file"
                accept="image/*,video/*"
                multiple
                className="hidden"
                onChange={handleFileChange}
            />

            {toastMsg && (
                <div className="fixed bottom-6 right-6 z-[100] rounded-[12px] bg-[#dcebc4] px-5 py-3 text-[13px] font-semibold text-[#3a5a1f] shadow-lg">
                    ✓ {toastMsg}
                </div>
            )}

            <div className="p-6">
                {/* Vị trí phục vụ — quản lý gộp ngay trong màn này */}
                <ServiceLocationsManager locations={service_locations} />

                <div className="mb-6 flex items-center justify-between">
                    <div>
                        <h1 className="text-[22px] font-extrabold text-pine">
                            Điểm cắm trại
                        </h1>
                        <p className="mt-0.5 text-[13px] text-moss">
                            <span className="font-mono">{spots.total}</span>{' '}
                            điểm · click vào hàng để quản lý ảnh/video
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
                        Thêm điểm
                    </button>
                </div>

                <div className="overflow-hidden rounded-[16px] border border-cardBorder bg-white">
                    {spots.data.length === 0 ? (
                        <div className="py-16 text-center text-moss">
                            <div className="mb-2 text-[32px]">⛺️</div>
                            <div className="text-[14px]">
                                Chưa có điểm cắm trại nào
                            </div>
                            <button
                                onClick={openCreate}
                                className="mt-3 text-[13px] font-semibold text-grass underline"
                            >
                                Thêm điểm đầu tiên
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
                                        Tên điểm
                                    </th>
                                    <th className="hidden px-4 py-3 text-left font-semibold text-moss md:table-cell">
                                        Tỉnh / Thành
                                    </th>
                                    <th className="hidden px-4 py-3 text-left font-semibold text-moss sm:table-cell">
                                        Địa hình
                                    </th>
                                    <th className="hidden px-4 py-3 text-center font-semibold text-moss lg:table-cell">
                                        Mùa đẹp
                                    </th>
                                    <th className="px-4 py-3 text-center font-semibold text-moss">
                                        Gợi ý
                                    </th>
                                    <th className="hidden px-4 py-3 text-center font-semibold text-moss lg:table-cell">
                                        Media
                                    </th>
                                    <th className="px-4 py-3 text-right font-semibold text-moss">
                                        Thao tác
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {spots.data.map((s) => {
                                    const expanded = expandedId === s.id;
                                    return (
                                        <Fragment key={s.id}>
                                            <tr
                                                className="cursor-pointer border-b border-[#f1f4ea] hover:bg-[#fafcf7]"
                                                onClick={() =>
                                                    setExpandedId(
                                                        expanded ? null : s.id,
                                                    )
                                                }
                                            >
                                                <td className="px-4 py-3">
                                                    <div className="font-semibold text-pine">
                                                        {s.name}
                                                    </div>
                                                    {s.district && (
                                                        <div className="text-[11px] text-moss">
                                                            {s.district}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="hidden px-4 py-3 text-moss md:table-cell">
                                                    {s.province}
                                                </td>
                                                <td className="hidden px-4 py-3 sm:table-cell">
                                                    <span className="rounded-full bg-[#eef2e3] px-2.5 py-1 text-[11.5px] font-semibold text-[#3a5a1f]">
                                                        {s.terrain_tag}
                                                    </span>
                                                </td>
                                                <td className="hidden px-4 py-3 text-center font-mono text-campfire lg:table-cell">
                                                    {s.season_label}
                                                </td>
                                                <td className="px-4 py-3 text-center">
                                                    {s.is_suggested ? (
                                                        <span
                                                            title={
                                                                s.nearest_name ??
                                                                ''
                                                            }
                                                            className="text-grass"
                                                        >
                                                            ★
                                                        </span>
                                                    ) : (
                                                        <span className="text-[#c4cca8]">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="hidden px-4 py-3 text-center font-mono text-moss lg:table-cell">
                                                    {s.media.length}
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
                                                                openEdit(s)
                                                            }
                                                            className="rounded-[8px] border border-cardBorder px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                                                        >
                                                            Sửa
                                                        </button>
                                                        <button
                                                            onClick={() =>
                                                                setDeleteId(
                                                                    s.id,
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
                                                        {s.description && (
                                                            <p className="mb-2 max-w-[640px] text-[12.5px] leading-[1.55] text-moss">
                                                                {s.description}
                                                            </p>
                                                        )}
                                                        {s.map_url && (
                                                            <a
                                                                href={s.map_url}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="mb-3 inline-flex items-center gap-1.5 text-[12.5px] font-semibold text-grass hover:text-pine"
                                                            >
                                                                <svg
                                                                    width="13"
                                                                    height="13"
                                                                    viewBox="0 0 24 24"
                                                                    fill="none"
                                                                >
                                                                    <path
                                                                        d="M12 21s7-6.3 7-11a7 7 0 1 0-14 0c0 4.7 7 11 7 11Z"
                                                                        stroke="currentColor"
                                                                        strokeWidth="1.7"
                                                                    />
                                                                    <circle
                                                                        cx="12"
                                                                        cy="10"
                                                                        r="2.4"
                                                                        stroke="currentColor"
                                                                        strokeWidth="1.7"
                                                                    />
                                                                </svg>
                                                                Mở bản đồ
                                                            </a>
                                                        )}
                                                        <div className="mb-2 flex items-center justify-between">
                                                            <span className="text-[12.5px] font-semibold text-moss">
                                                                Ảnh / Video (
                                                                {s.media.length}
                                                                )
                                                            </span>
                                                            <button
                                                                onClick={() =>
                                                                    triggerUpload(
                                                                        s.id,
                                                                    )
                                                                }
                                                                disabled={
                                                                    uploadingId ===
                                                                    s.id
                                                                }
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
                                                                {uploadingId ===
                                                                s.id
                                                                    ? 'Đang tải…'
                                                                    : 'Upload ảnh/video'}
                                                            </button>
                                                        </div>
                                                        {s.media.length ===
                                                        0 ? (
                                                            <div className="rounded-[10px] border border-dashed border-cardBorder py-6 text-center text-[12.5px] text-moss">
                                                                Chưa có media ·
                                                                click "Upload
                                                                ảnh/video" để
                                                                thêm
                                                            </div>
                                                        ) : (
                                                            <div className="flex flex-wrap gap-3">
                                                                {s.media.map(
                                                                    (m) => (
                                                                        <div
                                                                            key={
                                                                                m.id
                                                                            }
                                                                            className="group relative"
                                                                        >
                                                                            {m.type ===
                                                                            'video' ? (
                                                                                <video
                                                                                    src={
                                                                                        m.url
                                                                                    }
                                                                                    className="h-20 w-20 rounded-[10px] border border-cardBorder object-cover"
                                                                                    muted
                                                                                />
                                                                            ) : (
                                                                                <img
                                                                                    src={
                                                                                        m.url
                                                                                    }
                                                                                    alt=""
                                                                                    className="h-20 w-20 rounded-[10px] border border-cardBorder object-cover"
                                                                                />
                                                                            )}
                                                                            {m.type ===
                                                                                'video' && (
                                                                                <span className="pointer-events-none absolute inset-0 grid place-items-center text-white">
                                                                                    ▶
                                                                                </span>
                                                                            )}
                                                                            <button
                                                                                onClick={() =>
                                                                                    deleteMedia(
                                                                                        s.id,
                                                                                        m.id,
                                                                                    )
                                                                                }
                                                                                className="absolute -right-1.5 -top-1.5 hidden h-5 w-5 items-center justify-center rounded-full bg-[#b3493a] text-[10px] font-bold text-white shadow group-hover:flex"
                                                                                title="Xoá"
                                                                            >
                                                                                ×
                                                                            </button>
                                                                        </div>
                                                                    ),
                                                                )}
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

                {spots.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between text-[12.5px] text-moss">
                        <span className="font-mono">
                            {spots.from}–{spots.to} / {spots.total}
                        </span>
                        <div className="flex gap-2">
                            <button
                                disabled={spots.current_page <= 1}
                                onClick={() =>
                                    router.get(
                                        route('admin.camping-spots'),
                                        { page: spots.current_page - 1 },
                                        { preserveScroll: true },
                                    )
                                }
                                className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                            >
                                Trước
                            </button>
                            <button
                                disabled={spots.current_page >= spots.last_page}
                                onClick={() =>
                                    router.get(
                                        route('admin.camping-spots'),
                                        { page: spots.current_page + 1 },
                                        { preserveScroll: true },
                                    )
                                }
                                className="rounded-[8px] border border-cardBorder px-3 py-1.5 font-semibold text-pine transition hover:border-grass disabled:opacity-40"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                )}
            </div>

            {/* Create / Edit modal */}
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
                            {modalMode === 'create'
                                ? 'Thêm điểm cắm trại'
                                : 'Sửa điểm cắm trại'}
                        </h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <Field
                                label="Tên điểm"
                                required
                                error={form.errors.name}
                            >
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    className={inputCls}
                                    placeholder="VD: Hồ Tà Đùng"
                                    autoFocus
                                />
                            </Field>

                            <div className="grid grid-cols-2 gap-3">
                                <Field
                                    label="Tỉnh / Thành"
                                    required
                                    error={form.errors.province}
                                >
                                    <input
                                        list="provinces"
                                        value={form.data.province}
                                        onChange={(e) =>
                                            form.setData(
                                                'province',
                                                e.target.value,
                                            )
                                        }
                                        className={inputCls}
                                        placeholder="Gõ mới hoặc chọn đã có"
                                    />
                                    <datalist id="provinces">
                                        {provinces.map((p) => (
                                            <option key={p} value={p} />
                                        ))}
                                    </datalist>
                                </Field>
                                <Field
                                    label="Loại địa hình"
                                    required
                                    error={form.errors.terrain_tag}
                                >
                                    <input
                                        list="terrain-tags"
                                        value={form.data.terrain_tag}
                                        onChange={(e) =>
                                            form.setData(
                                                'terrain_tag',
                                                e.target.value,
                                            )
                                        }
                                        className={inputCls}
                                        placeholder="Ven hồ"
                                    />
                                    <datalist id="terrain-tags">
                                        {TERRAIN_TAGS.map((t) => (
                                            <option key={t} value={t} />
                                        ))}
                                    </datalist>
                                </Field>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <Field label="Khu vực (thành phố/huyện)">
                                    <input
                                        type="text"
                                        value={form.data.district}
                                        onChange={(e) =>
                                            form.setData(
                                                'district',
                                                e.target.value,
                                            )
                                        }
                                        className={inputCls}
                                        placeholder="Ba Vì"
                                    />
                                </Field>
                                <Field label="Gần vị trí phục vụ">
                                    <select
                                        value={
                                            form.data
                                                .nearest_service_location_id
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'nearest_service_location_id',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                        className={inputCls}
                                    >
                                        <option value="">— Không</option>
                                        {service_locations.map((l) => (
                                            <option key={l.id} value={l.id}>
                                                {l.name}
                                                {l.area ? ` (${l.area})` : ''}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>

                            <Field
                                label="Link bản đồ (Google Maps)"
                                error={form.errors.map_url}
                            >
                                <input
                                    type="url"
                                    value={form.data.map_url}
                                    onChange={(e) =>
                                        form.setData('map_url', e.target.value)
                                    }
                                    className={inputCls}
                                    placeholder="https://maps.google.com/..."
                                />
                            </Field>

                            <Field label="Mô tả">
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className={inputCls}
                                    placeholder="Vài dòng về điểm cắm trại này..."
                                />
                            </Field>

                            <div className="grid grid-cols-2 gap-3">
                                <Field
                                    label="Mùa đẹp — từ tháng"
                                    error={form.errors.best_season_from}
                                >
                                    <select
                                        value={form.data.best_season_from}
                                        onChange={(e) =>
                                            form.setData(
                                                'best_season_from',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                        className={inputCls}
                                    >
                                        <option value="">— (cả năm)</option>
                                        {MONTHS.map((m) => (
                                            <option key={m} value={m}>
                                                Tháng {m}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="đến tháng"
                                    error={form.errors.best_season_to}
                                >
                                    <select
                                        value={form.data.best_season_to}
                                        onChange={(e) =>
                                            form.setData(
                                                'best_season_to',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                        className={inputCls}
                                    >
                                        <option value="">—</option>
                                        {MONTHS.map((m) => (
                                            <option key={m} value={m}>
                                                Tháng {m}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            </div>

                            <div className="flex items-center justify-between gap-3">
                                <label className="flex cursor-pointer items-center gap-2 text-[13px] text-pine">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_suggested}
                                        onChange={(e) =>
                                            form.setData(
                                                'is_suggested',
                                                e.target.checked,
                                            )
                                        }
                                        className="accent-grass"
                                    />
                                    Hiện ở panel gợi ý trang chủ
                                </label>
                                <div className="flex items-center gap-2">
                                    <span className="text-[12.5px] text-moss">
                                        Thứ tự
                                    </span>
                                    <input
                                        type="number"
                                        min="0"
                                        value={form.data.sort_order}
                                        onChange={(e) =>
                                            form.setData(
                                                'sort_order',
                                                e.target.value === ''
                                                    ? ''
                                                    : Number(e.target.value),
                                            )
                                        }
                                        className="w-20 rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] outline-none focus:border-grass"
                                        placeholder="0"
                                    />
                                </div>
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

            {/* Delete confirm */}
            {deleteId !== null && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4">
                    <div className="w-full max-w-sm rounded-[18px] bg-white p-6 shadow-xl">
                        <h2 className="mb-2 text-[16px] font-extrabold text-pine">
                            Xác nhận xoá điểm
                        </h2>
                        <p className="mb-4 text-[13px] text-moss">
                            Tất cả ảnh/video của điểm cũng sẽ bị xoá. Hành động
                            không thể hoàn tác.
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

const inputCls =
    'w-full rounded-[10px] border border-cardBorder px-3.5 py-2.5 text-[13.5px] outline-none transition focus:border-grass';

function Field({
    label,
    required,
    error,
    children,
}: {
    label: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div>
            <label className="mb-1.5 block text-[13px] font-semibold text-pine">
                {label} {required && <span className="text-[#b3493a]">*</span>}
            </label>
            {children}
            {error && (
                <p className="mt-1 text-[12px] text-[#b3493a]">{error}</p>
            )}
        </div>
    );
}

AdminCampingSpots.layout = (page: ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);
