type Props = {
    status: string;
    /** Cho phép đổi nhãn (vd: "Đang cho thuê" ở tab Kho) — màu vẫn dùng chung. */
    label?: string;
};

/**
 * Pill trạng thái sản phẩm (active / hidden) — single source of truth cho màu pill,
 * dùng chung ở Admin/Products và tab Kho của Admin/Orders (DRY — RULES §5).
 */
export default function ProductStatusPill({ status, label }: Props) {
    const active = status === 'active';
    return (
        <span
            className={`rounded-pill px-2.5 py-1 text-[11.5px] font-bold ${
                active ? 'bg-[#dcebc4] text-[#3a5a1f]' : 'bg-[#f6ddd6] text-[#b3493a]'
            }`}
        >
            {label ?? (active ? 'Đang bán' : 'Đã ẩn')}
        </span>
    );
}
