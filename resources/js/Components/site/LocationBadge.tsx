export type CardLocation = { slug: string; name: string };

/** Badge vị trí phục vụ — pill trắng có icon ghim, nổi trên ảnh của thẻ. */
export default function LocationBadge({ label }: { label: string }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-pill bg-white/90 px-2 py-1 text-[10.5px] font-bold text-pine shadow-[0_2px_6px_rgba(24,35,15,.18)] backdrop-blur-sm">
            <svg
                width="9"
                height="9"
                viewBox="0 0 24 24"
                fill="none"
                className="flex-none"
            >
                <path
                    d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"
                    fill="#C97B36"
                    stroke="#C97B36"
                    strokeWidth="1.5"
                    strokeLinejoin="round"
                />
                <circle cx="12" cy="10" r="2.4" fill="#fff" />
            </svg>
            {label}
        </span>
    );
}

/**
 * Cụm badge vị trí cho thẻ sản phẩm/combo — dùng CHUNG để hai thẻ không lệch nhau.
 *
 * Gộp thành "Toàn hệ thống" chỉ khi phục vụ đủ MỌI cơ sở đang mở VÀ có ≥2 cơ sở;
 * 1 cơ sở thì hiện thẳng tên nơi đó (nói "toàn hệ thống" khi hệ thống có 1 nơi là vô nghĩa).
 * Không có cơ sở nào → không render gì (thẻ không có chỗ trống lửng lơ).
 */
export function LocationBadges({
    locations,
    allLocations,
}: {
    locations?: CardLocation[];
    allLocations?: boolean;
}) {
    const list = locations ?? [];
    if (list.length === 0) {
        return null;
    }

    return (
        <div className="absolute bottom-2.5 right-2.5 flex max-w-[70%] flex-wrap justify-end gap-1">
            {allLocations && list.length > 1 ? (
                <LocationBadge label="Toàn hệ thống" />
            ) : (
                list.map((l) => <LocationBadge key={l.slug} label={l.name} />)
            )}
        </div>
    );
}
