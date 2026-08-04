import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import MediaPickerModal, {
    LibraryGroup,
    PickerSource,
} from './MediaPickerModal';

/** Ảnh đã chọn từ kho, kèm URL để xem trước (phân giải từ prop `mediaLibrary`). */
type PickedImage = PickerSource & { url: string; kind: 'image' | 'video' };

export type DraftMedia = {
    /** File vừa chọn từ máy — chưa upload, gửi kèm request tạo sản phẩm. */
    files: File[];
    /** Ảnh tái sử dụng từ kho — chỉ gửi {type,id}, server tự tra lại path. */
    sources: PickerSource[];
};

/**
 * Gallery ảnh phụ cho form THÊM MỚI, khi chưa có product id (bopcamping-7czf).
 *
 * Khác `MediaGallery` (dùng ở form sửa) ở chỗ: các route ảnh đều cần
 * `/admin/products/{product}/images`, nên lúc tạo mới KHÔNG thể gọi server ngay.
 * Component này giữ lựa chọn ở state cha rồi submit cùng request tạo — nhờ vậy
 * admin thêm được nhiều ảnh + chọn ảnh có sẵn ngay khi tạo, thay vì phải lưu
 * sản phẩm trước rồi mới quay lại thêm ảnh.
 *
 * KHÔNG kéo-thả sắp xếp ở đây: thứ tự = thứ tự thêm vào. Sắp xếp làm ở form sửa
 * (nơi ảnh đã có id thật để lưu sort_order).
 */
export default function DraftMediaGallery({
    label,
    value,
    onChange,
}: {
    label: string;
    value: DraftMedia;
    onChange: (next: DraftMedia) => void;
}) {
    const [pickerOpen, setPickerOpen] = useState(false);
    const [libLoading, setLibLoading] = useState(false);
    const uploadRef = useRef<HTMLInputElement>(null);

    const library =
        (usePage().props.mediaLibrary as LibraryGroup[] | undefined) ?? [];

    // URL xem trước cho file local. Tạo bằng createObjectURL nên PHẢI revoke,
    // không thì rò bộ nhớ mỗi lần admin thêm/bớt ảnh.
    const previews = useMemo(
        () => value.files.map((f) => ({ file: f, url: URL.createObjectURL(f) })),
        [value.files],
    );
    useEffect(
        () => () => previews.forEach((p) => URL.revokeObjectURL(p.url)),
        [previews],
    );

    // Ảnh đã chọn từ kho: phân giải URL từ library để xem trước. Nếu library chưa
    // nạp (admin chưa mở picker lần nào trong session này) thì bỏ qua phần preview,
    // nhưng {type,id} vẫn được gửi lên đúng.
    const picked: PickedImage[] = useMemo(() => {
        const byKey = new Map<string, { url: string; kind: 'image' | 'video' }>();
        library.forEach((g) =>
            g.images.forEach((img) =>
                byKey.set(`${g.type}:${img.id}`, {
                    url: img.path,
                    kind: img.type,
                }),
            ),
        );

        return value.sources.map((s) => ({
            ...s,
            url: byKey.get(`${s.type}:${s.id}`)?.url ?? '',
            kind: byKey.get(`${s.type}:${s.id}`)?.kind ?? 'image',
        }));
    }, [library, value.sources]);

    const total = value.files.length + value.sources.length;

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files?.length) return;
        onChange({
            ...value,
            files: [...value.files, ...Array.from(e.target.files)],
        });
        // Cho phép chọn lại đúng file đó lần sau (input giữ value cũ thì onChange không nổ).
        e.target.value = '';
    };

    const openPicker = () => {
        setPickerOpen(true);
        // Kho ảnh là prop optional (nạp lazy) — chỉ tải khi admin thật sự mở picker.
        setLibLoading(true);
        router.reload({
            only: ['mediaLibrary'],
            onFinish: () => setLibLoading(false),
        });
    };

    const confirmPick = (sources: PickerSource[]) => {
        const seen = new Set(value.sources.map((s) => `${s.type}:${s.id}`));
        onChange({
            ...value,
            sources: [
                ...value.sources,
                ...sources.filter((s) => !seen.has(`${s.type}:${s.id}`)),
            ],
        });
        setPickerOpen(false);
    };

    const removeFile = (idx: number) =>
        onChange({ ...value, files: value.files.filter((_, i) => i !== idx) });

    const removeSource = (idx: number) =>
        onChange({
            ...value,
            sources: value.sources.filter((_, i) => i !== idx),
        });

    const thumbCls =
        'h-20 w-20 rounded-[10px] border border-cardBorder object-cover';
    const removeBtnCls =
        'absolute -right-1.5 -top-1.5 hidden h-5 w-5 items-center justify-center rounded-full bg-[#b3493a] text-[10px] font-bold text-white shadow group-hover:flex';

    return (
        <div>
            <input
                ref={uploadRef}
                type="file"
                accept="image/*,video/*"
                multiple
                className="hidden"
                onChange={handleFileChange}
            />

            <div className="mb-2 flex items-center justify-between gap-2">
                <span className="text-[12.5px] font-semibold text-moss">
                    {label} ({total})
                </span>
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={openPicker}
                        className="flex items-center gap-1.5 rounded-[8px] border border-cardBorder bg-white px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                            <rect
                                x="3"
                                y="3"
                                width="18"
                                height="18"
                                rx="2"
                                stroke="currentColor"
                                strokeWidth="2"
                            />
                            <path
                                d="M3 15l5-4 4 3 3-2 6 5"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                            />
                        </svg>
                        Chọn ảnh có sẵn
                    </button>
                    <button
                        type="button"
                        onClick={() => uploadRef.current?.click()}
                        className="flex items-center gap-1.5 rounded-[8px] border border-cardBorder bg-white px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                    >
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M12 5v14M5 12h14"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                            />
                        </svg>
                        Upload ảnh/video
                    </button>
                </div>
            </div>

            {total === 0 ? (
                <div className="rounded-[10px] border border-dashed border-cardBorder py-6 text-center text-[12.5px] text-moss">
                    Chưa có ảnh · "Upload ảnh/video" hoặc "Chọn ảnh có sẵn" —
                    ảnh sẽ được lưu khi bấm Lưu sản phẩm
                </div>
            ) : (
                <div className="flex flex-nowrap gap-3 overflow-x-auto px-1 pb-1 pt-2.5">
                    {previews.map((p, i) => (
                        <div
                            key={`f-${i}-${p.file.name}`}
                            className="group relative shrink-0"
                        >
                            {p.file.type.startsWith('video/') ? (
                                <video src={p.url} className={thumbCls} muted />
                            ) : (
                                <img src={p.url} alt="" className={thumbCls} />
                            )}
                            <button
                                type="button"
                                onClick={() => removeFile(i)}
                                className={removeBtnCls}
                                title="Bỏ ảnh này"
                            >
                                ×
                            </button>
                        </div>
                    ))}
                    {picked.map((s, i) => (
                        <div
                            key={`s-${s.type}-${s.id}`}
                            className="group relative shrink-0"
                        >
                            {s.kind === 'video' ? (
                                <video src={s.url} className={thumbCls} muted />
                            ) : (
                                <img
                                    src={s.url}
                                    alt=""
                                    className={`${thumbCls} bg-[#f1f4ea]`}
                                />
                            )}
                            <span className="pointer-events-none absolute bottom-0 left-0 right-0 rounded-b-[10px] bg-black/45 py-0.5 text-center text-[9px] font-semibold text-white">
                                dùng chung
                            </span>
                            <button
                                type="button"
                                onClick={() => removeSource(i)}
                                className={removeBtnCls}
                                title="Bỏ ảnh này"
                            >
                                ×
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <MediaPickerModal
                open={pickerOpen}
                loading={libLoading && library.length === 0}
                library={library}
                submitting={false}
                onClose={() => setPickerOpen(false)}
                onConfirm={confirmPick}
            />
        </div>
    );
}
