import { useEffect, useMemo } from 'react';

/**
 * bopcamping-dwa5 (T3) — chọn cơ sở bán combo + xem món nào có ở cơ sở đó.
 *
 * Tách khỏi Admin/Combos.tsx (đã 615 dòng) để file đó không phình thêm.
 *
 * Hai khái niệm CỐ Ý tách rời (PRD mục 6, R2):
 *  - ĐƯỢC PHÉP bán ở cơ sở = mọi món con đều phục vụ ở đó (tư cách thành viên pivot).
 *    Đây là thứ enable/disable chip.
 *  - ĐANG CÒN HÀNG ở cơ sở = tồn > 0. CHỈ hiển thị, KHÔNG chặn — prod chỉ 3/11 sản phẩm
 *    còn tồn, chặn theo tồn thì admin không gán nổi cơ sở nào.
 */

export type PickerLocation = { id: number; name: string; slug: string };
export type PickerProduct = {
    id: number;
    name: string;
    service_location_ids: number[];
};

type Props = {
    locations: PickerLocation[];
    /** { locationId: { productId: qty } } — tồn cấu hình, chỉ để hiển thị. */
    locationStock: Record<number, Record<number, number>>;
    products: PickerProduct[];
    /** Món đang có trong combo (đang soạn). */
    items: { product_id: number; quantity: number }[];
    value: number[];
    onChange: (ids: number[]) => void;
    /** Báo khi có cơ sở bị tự bỏ tích vì thêm/bớt món. */
    onAutoDeselect?: (removed: PickerLocation[]) => void;
    error?: string;
};

export default function ComboLocationPicker({
    locations,
    locationStock,
    products,
    items,
    value,
    onChange,
    onAutoDeselect,
    error,
}: Props) {
    const productById = useMemo(() => {
        const map = new Map<number, PickerProduct>();
        for (const p of products) map.set(p.id, p);
        return map;
    }, [products]);

    const itemProducts = useMemo(
        () =>
            items
                .map((i) => productById.get(i.product_id))
                .filter((p): p is PickerProduct => !!p),
        [items, productById],
    );

    /** Món KHÔNG phục vụ ở cơ sở này → lý do chip bị chặn. */
    const notServedAt = useMemo(() => {
        const out: Record<number, PickerProduct[]> = {};
        for (const l of locations) {
            out[l.id] = itemProducts.filter(
                (p) => !p.service_location_ids.includes(l.id),
            );
        }
        return out;
    }, [locations, itemProducts]);

    const assignable = (locationId: number) =>
        itemProducts.length > 0 && notServedAt[locationId]?.length === 0;

    // Thêm/bớt món có thể làm cơ sở đang tích thành không hợp lệ → tự bỏ tích, đừng để
    // form gửi lên rồi mới 422.
    useEffect(() => {
        const invalid = value.filter((id) => !assignable(id));
        if (invalid.length === 0) return;

        onChange(value.filter((id) => assignable(id)));
        onAutoDeselect?.(locations.filter((l) => invalid.includes(l.id)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [items, products]);

    const toggle = (id: number) => {
        onChange(
            value.includes(id) ? value.filter((x) => x !== id) : [...value, id],
        );
    };

    if (locations.length === 0) {
        return (
            <p className="text-[12.5px] text-moss">
                Chưa có cơ sở nào đang mở. Thêm cơ sở trước khi gán cho combo.
            </p>
        );
    }

    return (
        <div>
            <div className="flex flex-wrap gap-2">
                {locations.map((l) => {
                    const on = value.includes(l.id);
                    const ok = assignable(l.id);
                    const missing = notServedAt[l.id] ?? [];

                    return (
                        <button
                            type="button"
                            key={l.id}
                            disabled={!ok}
                            onClick={() => toggle(l.id)}
                            aria-pressed={on}
                            title={
                                ok
                                    ? undefined
                                    : missing.length > 0
                                      ? `${missing.map((p) => p.name).join(', ')} không phục vụ tại ${l.name}`
                                      : 'Chọn sản phẩm cho combo trước'
                            }
                            className={`flex items-center gap-2 rounded-[11px] border px-3.5 py-2 text-[13px] font-semibold transition ${
                                !ok
                                    ? 'cursor-not-allowed border-cardBorder bg-[#f6f8f1] text-[#aab39a] opacity-70'
                                    : on
                                      ? 'border-grass bg-[#eef5e1] text-grass'
                                      : 'border-cardBorder bg-white text-pine hover:border-grass'
                            }`}
                        >
                            <span
                                className={`grid h-[17px] w-[17px] place-items-center rounded-[5px] border text-[10px] font-bold ${
                                    on
                                        ? 'border-grass bg-grass text-white'
                                        : 'border-[#c4cca8] text-transparent'
                                }`}
                            >
                                ✓
                            </span>
                            {l.name}
                        </button>
                    );
                })}
            </div>

            {/* Lý do từng cơ sở bị chặn — không có dòng này thì admin không hiểu vì sao disabled. */}
            {itemProducts.length > 0 &&
                locations
                    .filter((l) => (notServedAt[l.id] ?? []).length > 0)
                    .map((l) => (
                        <p
                            key={l.id}
                            className="mt-1.5 text-[12px] text-[#b3493a]"
                        >
                            <span className="font-semibold">{l.name}</span>:{' '}
                            {(notServedAt[l.id] ?? [])
                                .map((p) => p.name)
                                .join(', ')}{' '}
                            không phục vụ ở đây.
                        </p>
                    ))}

            {itemProducts.length === 0 && (
                <p className="mt-1.5 text-[12px] text-moss">
                    Chọn sản phẩm cho combo trước, rồi mới chọn được cơ sở.
                </p>
            )}

            {error && (
                <p className="mt-1.5 text-[12px] text-[#b3493a]">{error}</p>
            )}

            {/* Bảng "Món tại cơ sở này" cho từng cơ sở ĐÃ tích. */}
            {value.filter(assignable).map((locationId) => {
                const location = locations.find((l) => l.id === locationId);
                if (!location) return null;

                const stock = locationStock[locationId] ?? {};
                const inStock = itemProducts.filter(
                    (p) => (stock[p.id] ?? 0) > 0,
                );
                const outOfStock = itemProducts.filter(
                    (p) => (stock[p.id] ?? 0) <= 0,
                );

                return (
                    <div
                        key={locationId}
                        className="mt-3 rounded-[12px] border border-cardBorder bg-[#fbfcf7] p-3"
                    >
                        <div className="mb-2 text-[12.5px] font-bold text-ink">
                            Món tại {location.name}
                        </div>

                        {inStock.length > 0 ? (
                            <ul className="space-y-1">
                                {inStock.map((p) => (
                                    <li
                                        key={p.id}
                                        className="flex items-center justify-between text-[12.5px]"
                                    >
                                        <span className="text-pine">
                                            {p.name}
                                        </span>
                                        <span className="font-mono text-[12px] font-bold text-grass">
                                            còn {stock[p.id]}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-[12px] text-moss">
                                Chưa món nào còn hàng tại cơ sở này.
                            </p>
                        )}

                        {/* Tồn 0 chỉ là cảnh báo để đi nhập hàng — KHÔNG chặn lưu combo. */}
                        {outOfStock.length > 0 && (
                            <p className="mt-2 rounded-[9px] bg-[#fdf6e3] px-2.5 py-2 text-[12px] text-[#8a6d1f]">
                                {outOfStock.length} món đang hết hàng tại cơ sở
                                này: {outOfStock.map((p) => p.name).join(', ')}.
                                Vẫn lưu được — combo sẽ hiện hết hàng tới khi
                                nhập thêm.
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
