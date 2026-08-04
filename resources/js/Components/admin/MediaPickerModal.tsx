import { useEffect, useMemo, useState } from 'react';

export type LibraryImage = {
    id: number;
    path: string;
    type: 'image' | 'video';
};
export type LibraryGroup = {
    type: 'product' | 'combo';
    id: number;
    name: string;
    images: LibraryImage[];
};
export type PickerSource = { type: 'product' | 'combo'; id: number };

/** Khoá duy nhất cho 1 ảnh trong kho: id ảnh chỉ unique trong bảng của nó nên phải kèm loại nhóm. */
const keyOf = (groupType: string, imageId: number) => `${groupType}:${imageId}`;

/**
 * Modal "chọn ảnh có sẵn" — liệt kê kho ảnh nhóm theo product/combo, multi-select.
 * Dùng chung cho trang Sản phẩm & Combo (picker chọn chéo product ↔ combo).
 */
export default function MediaPickerModal({
    open,
    loading,
    library,
    onClose,
    onConfirm,
    submitting,
}: {
    open: boolean;
    loading: boolean;
    library: LibraryGroup[];
    onClose: () => void;
    onConfirm: (sources: PickerSource[]) => void;
    submitting: boolean;
}) {
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [q, setQ] = useState('');

    const groups = useMemo(() => {
        const needle = q.trim().toLowerCase();
        if (!needle) return library;
        return library.filter((g) => g.name.toLowerCase().includes(needle));
    }, [library, q]);

    // Reset lựa chọn + ô tìm mỗi khi picker đóng (kể cả khi cha đóng qua prop `open`
    // sau lúc "Thêm ảnh" thành công — không đi qua close()). Tránh giữ tick cũ lần mở sau.
    useEffect(() => {
        if (!open) {
            setSelected(new Set());
            setQ('');
        }
    }, [open]);

    if (!open) return null;

    const toggle = (groupType: string, imageId: number) => {
        setSelected((prev) => {
            const next = new Set(prev);
            const k = keyOf(groupType, imageId);
            next.has(k) ? next.delete(k) : next.add(k);
            return next;
        });
    };

    const confirm = () => {
        const sources: PickerSource[] = [];
        for (const g of library) {
            for (const img of g.images) {
                if (selected.has(keyOf(g.type, img.id))) {
                    sources.push({ type: g.type, id: img.id });
                }
            }
        }
        onConfirm(sources);
    };

    // Reset do useEffect([open]) lo — ở đây chỉ cần báo cha đóng.
    const close = () => onClose();

    return (
        <div
            className="fixed inset-0 z-[60] flex items-start justify-center overflow-y-auto bg-black/40 px-4 py-8"
            onClick={close}
        >
            <div
                className="w-full max-w-3xl rounded-[16px] bg-white shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between border-b border-[#f1f4ea] px-5 py-4">
                    <h3 className="text-[15px] font-bold text-pine">
                        Chọn ảnh có sẵn
                    </h3>
                    <button
                        type="button"
                        onClick={close}
                        className="text-[20px] leading-none text-moss hover:text-pine"
                    >
                        ×
                    </button>
                </div>

                <div className="border-b border-[#f1f4ea] px-5 py-3">
                    <input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        placeholder="Tìm theo tên sản phẩm / combo…"
                        className="w-full rounded-[10px] border border-cardBorder px-3 py-2 text-[13px] outline-none focus:border-grass"
                    />
                </div>

                <div className="max-h-[60vh] overflow-y-auto px-5 py-4">
                    {loading ? (
                        <div className="py-10 text-center text-[13px] text-moss">
                            Đang tải kho ảnh…
                        </div>
                    ) : groups.length === 0 ? (
                        <div className="py-10 text-center text-[13px] text-moss">
                            Không có ảnh nào.
                        </div>
                    ) : (
                        <div className="space-y-5">
                            {groups.map((g) => (
                                <div key={`${g.type}-${g.id}`}>
                                    <div className="mb-2 flex items-center gap-2">
                                        <span
                                            className={`rounded-[6px] px-1.5 py-0.5 text-[10px] font-bold ${
                                                g.type === 'combo'
                                                    ? 'bg-[#e6dcc4] text-[#7a5a1f]'
                                                    : 'bg-[#dcebc4] text-[#3a5a1f]'
                                            }`}
                                        >
                                            {g.type === 'combo'
                                                ? 'COMBO'
                                                : 'SP'}
                                        </span>
                                        <span className="text-[12.5px] font-semibold text-moss">
                                            {g.name}
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap gap-2.5">
                                        {g.images.map((img) => {
                                            const active = selected.has(
                                                keyOf(g.type, img.id),
                                            );
                                            return (
                                                <button
                                                    key={img.id}
                                                    type="button"
                                                    onClick={() =>
                                                        toggle(g.type, img.id)
                                                    }
                                                    className={`group relative h-[70px] w-[70px] overflow-hidden rounded-[10px] border-2 transition ${
                                                        active
                                                            ? 'border-grass'
                                                            : 'border-transparent hover:border-cardBorder'
                                                    }`}
                                                    title={
                                                        active
                                                            ? 'Bỏ chọn'
                                                            : 'Chọn'
                                                    }
                                                >
                                                    {img.type === 'video' ? (
                                                        <video
                                                            src={img.path}
                                                            className="h-full w-full object-cover"
                                                            muted
                                                        />
                                                    ) : (
                                                        <img
                                                            src={img.path}
                                                            alt=""
                                                            className="h-full w-full object-cover"
                                                        />
                                                    )}
                                                    {img.type === 'video' && (
                                                        <span className="pointer-events-none absolute inset-0 grid place-items-center text-white">
                                                            ▶
                                                        </span>
                                                    )}
                                                    {active && (
                                                        <span className="absolute right-1 top-1 grid h-4 w-4 place-items-center rounded-full bg-grass text-[10px] font-bold text-white">
                                                            ✓
                                                        </span>
                                                    )}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                <div className="flex items-center justify-between border-t border-[#f1f4ea] px-5 py-4">
                    <span className="text-[12.5px] text-moss">
                        Đã chọn {selected.size} ảnh
                    </span>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={close}
                            className="rounded-[10px] border border-cardBorder px-4 py-2 text-[13px] font-semibold text-moss hover:border-pine hover:text-pine"
                        >
                            Huỷ
                        </button>
                        <button
                            type="button"
                            onClick={confirm}
                            disabled={selected.size === 0 || submitting}
                            className="rounded-[10px] bg-grass px-4 py-2 text-[13px] font-semibold text-white transition hover:brightness-95 disabled:opacity-50"
                        >
                            {submitting
                                ? 'Đang thêm…'
                                : `Thêm ${selected.size || ''} ảnh`}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
