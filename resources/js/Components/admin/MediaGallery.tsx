import { router, usePage } from '@inertiajs/react';
import { Reorder } from 'framer-motion';
import { useEffect, useRef, useState } from 'react';
import MediaPickerModal, {
    LibraryGroup,
    PickerSource,
} from './MediaPickerModal';

export type GalleryImage = {
    id: number;
    path: string;
    sort_order: number;
    type: 'image' | 'video';
};

const ROUTES = {
    product: {
        store: 'admin.products.images.store',
        attach: 'admin.products.images.attach',
        reorder: 'admin.products.images.reorder',
        destroy: 'admin.products.images.destroy',
    },
    combo: {
        store: 'admin.combos.images.store',
        attach: 'admin.combos.images.attach',
        reorder: 'admin.combos.images.reorder',
        destroy: 'admin.combos.images.destroy',
    },
} as const;

/**
 * Gallery ảnh phụ dùng chung cho trang Sản phẩm & Combo (admin):
 *  - kéo-thả sắp xếp lại thứ tự (framer-motion Reorder), lưu khi thả;
 *  - upload file mới HOẶC chọn ảnh có sẵn (tái sử dụng, chia sẻ file);
 *  - xoá từng ảnh.
 * Kho ảnh cho picker là prop trang `mediaLibrary` (nạp lazy khi mở picker).
 */
export default function MediaGallery({
    kind,
    itemId,
    images,
    label,
}: {
    kind: 'product' | 'combo';
    itemId: number;
    images: GalleryImage[];
    label: string;
}) {
    const r = ROUTES[kind];
    const [order, setOrder] = useState<GalleryImage[]>(images);
    const orderRef = useRef<GalleryImage[]>(images);
    const [uploading, setUploading] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [attaching, setAttaching] = useState(false);
    const uploadRef = useRef<HTMLInputElement>(null);

    // Đồng bộ lại khi prop images đổi (sau upload/attach/xoá/reorder từ server).
    useEffect(() => {
        setOrder(images);
        orderRef.current = images;
    }, [images]);

    const library =
        (usePage().props.mediaLibrary as LibraryGroup[] | undefined) ?? [];
    const [libLoading, setLibLoading] = useState(false);

    const handleReorder = (next: GalleryImage[]) => {
        setOrder(next);
        orderRef.current = next;
    };

    const persistOrder = () => {
        const ids = orderRef.current.map((img) => img.id);
        // Không gửi nếu thứ tự không đổi so với server.
        if (ids.join(',') === images.map((i) => i.id).join(',')) return;
        router.post(
            route(r.reorder, itemId),
            { image_ids: ids },
            { preserveScroll: true, preserveState: true },
        );
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (!e.target.files?.length) return;
        setUploading(true);
        const formData = new FormData();
        Array.from(e.target.files).forEach((f) =>
            formData.append('images[]', f),
        );
        router.post(route(r.store, itemId), formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                e.target.value = '';
            },
        });
    };

    const deleteImage = (imageId: number) => {
        router.delete(route(r.destroy, [itemId, imageId]), {
            preserveScroll: true,
        });
    };

    const openPicker = () => {
        setPickerOpen(true);
        setLibLoading(true);
        router.reload({
            only: ['mediaLibrary'],
            onFinish: () => setLibLoading(false),
        });
    };

    const attach = (sources: PickerSource[]) => {
        setAttaching(true);
        router.post(
            route(r.attach, itemId),
            { sources },
            {
                preserveScroll: true,
                onFinish: () => {
                    setAttaching(false);
                    setPickerOpen(false);
                },
            },
        );
    };

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
                    {label} ({order.length})
                </span>
                <div className="flex gap-2">
                    <button
                        onClick={openPicker}
                        className="flex items-center gap-1.5 rounded-[8px] border border-cardBorder bg-white px-3 py-1.5 text-[12px] font-semibold text-pine transition hover:border-grass hover:text-grass"
                    >
                        <svg
                            width="13"
                            height="13"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
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
                        onClick={() => uploadRef.current?.click()}
                        disabled={uploading}
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
                        {uploading ? 'Đang tải…' : 'Upload ảnh/video'}
                    </button>
                </div>
            </div>

            {order.length === 0 ? (
                <div className="rounded-[10px] border border-dashed border-cardBorder py-6 text-center text-[12.5px] text-moss">
                    Chưa có ảnh · "Upload ảnh/video" hoặc "Chọn ảnh có sẵn"
                </div>
            ) : (
                <Reorder.Group
                    axis="x"
                    values={order}
                    onReorder={handleReorder}
                    className="flex flex-nowrap gap-3 overflow-x-auto px-1 pb-1 pt-2.5"
                >
                    {order.map((img) => (
                        <Reorder.Item
                            key={img.id}
                            value={img}
                            onDragEnd={persistOrder}
                            className="group relative shrink-0 cursor-grab active:cursor-grabbing"
                        >
                            {img.type === 'video' ? (
                                <video
                                    src={img.path}
                                    className="pointer-events-none h-20 w-20 rounded-[10px] border border-cardBorder object-cover"
                                    muted
                                />
                            ) : (
                                <img
                                    src={img.path}
                                    alt=""
                                    draggable={false}
                                    className="pointer-events-none h-20 w-20 rounded-[10px] border border-cardBorder object-cover"
                                />
                            )}
                            {img.type === 'video' && (
                                <span className="pointer-events-none absolute inset-0 grid place-items-center text-white">
                                    ▶
                                </span>
                            )}
                            <button
                                onClick={() => deleteImage(img.id)}
                                className="absolute -right-1.5 -top-1.5 hidden h-5 w-5 items-center justify-center rounded-full bg-[#b3493a] text-[10px] font-bold text-white shadow group-hover:flex"
                                title="Xoá ảnh"
                            >
                                ×
                            </button>
                        </Reorder.Item>
                    ))}
                </Reorder.Group>
            )}

            <MediaPickerModal
                open={pickerOpen}
                loading={libLoading && library.length === 0}
                library={library}
                submitting={attaching}
                onClose={() => setPickerOpen(false)}
                onConfirm={attach}
            />
        </div>
    );
}
