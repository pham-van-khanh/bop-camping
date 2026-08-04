type Option = { value: string; label: string };

/**
 * Select gọn, có mũi tên chỉ xuống + style đồng nhất (đẹp + rõ ràng hơn native select).
 * Dùng chung ở các trang admin khuyến mãi.
 */
export default function SelectInput({
    value,
    onChange,
    options,
    placeholder,
    className = '',
    ariaLabel,
}: {
    value: string;
    onChange: (v: string) => void;
    options: Option[];
    placeholder?: string;
    className?: string;
    ariaLabel?: string;
}) {
    return (
        <div className={`relative ${className}`}>
            <select
                value={value}
                aria-label={ariaLabel}
                onChange={(e) => onChange(e.target.value)}
                className="h-11 w-full cursor-pointer appearance-none rounded-[10px] border border-cardBorder bg-white pl-3.5 pr-10 text-[14px] font-medium text-ink outline-none transition hover:border-grass/60 focus:border-grass"
            >
                {placeholder !== undefined && (
                    <option value="">{placeholder}</option>
                )}
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
            <svg
                className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-moss"
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
            >
                <path
                    d="m6 8 4 4 4-4"
                    stroke="currentColor"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
            </svg>
        </div>
    );
}
