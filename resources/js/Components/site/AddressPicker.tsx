import {
    getProvinces,
    getWards,
    type Province,
    type Ward,
} from '@/lib/divisions';
import { useEffect, useState } from 'react';

/**
 * Chọn địa chỉ theo cấu trúc SAU sát nhập 07/2025: Tỉnh/Thành (34) -> Xã/Phường, 2 cấp.
 * Dữ liệu gọi thẳng provinces.open-api.vn (xem artifacts/plan_address_picker.md).
 *
 * CỐ Ý không có phần "địa chỉ cũ" nữa: bản trước có, nhưng một xã mới thường gộp từ
 * nhiều xã cũ (đo thật: Phường Bồ Đề <- 8 xã cũ) nên luôn phải hỏi khách thêm một lần
 * nữa -> form rối. Chủ shop chốt bỏ, chỉ giữ địa chỉ mới + ô khối tuỳ chọn.
 *
 * FALLBACK: mọi lỗi mạng -> về đúng ô text tự do như trước khi có tính năng này.
 * Không bao giờ chặn khách đặt hàng vì API địa chỉ của bên thứ ba.
 */

export type AddressValue = {
    /** Chuỗi hoàn chỉnh — NGUỒN CHÂN LÝ cho giao nhận, lưu vào orders.customer_address. */
    address: string;
    street: string;
    province_code: number | null;
    ward_code: number | null;
};

/** Select có chevron riêng (appearance-none) để đồng bộ với input, không dùng mũi mặc định của OS. */
const selectCls =
    'h-[48px] w-full cursor-pointer appearance-none truncate rounded-[11px] border border-cardBorder bg-white py-0 pl-3.5 pr-9 text-[14px] font-medium text-ink outline-none transition focus:border-grass focus:ring-2 focus:ring-grass/20 disabled:cursor-not-allowed disabled:bg-[#f4f6ee] disabled:text-[#aab39a]';
const chevron =
    "url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23557A2B' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.8' d='M6 8l4 4 4-4'/%3e%3c/svg%3e\")";
const selectStyle = {
    backgroundImage: chevron,
    backgroundRepeat: 'no-repeat',
    backgroundPosition: 'right 12px center',
    backgroundSize: '18px',
};
const inputCls =
    'h-[48px] w-full rounded-[11px] border bg-white px-3.5 text-[14px] text-ink outline-none transition focus:border-grass focus:ring-2 focus:ring-grass/20';

/**
 * Chuỗi địa chỉ: "Số 5 Trần Phú, Khối 3, Phường Hưng Bình, Tỉnh Nghệ An".
 * Khối/xóm/thôn nằm giữa số nhà và phường — đúng thứ tự người Việt đọc địa chỉ.
 */
function compose(
    street: string,
    block: string,
    ward?: Ward | null,
    province?: Province | null,
): string {
    return [street.trim(), block.trim(), ward?.name, province?.name]
        .filter(Boolean)
        .join(', ');
}

export default function AddressPicker({
    value,
    onChange,
    error,
}: {
    value: AddressValue;
    onChange: (v: AddressValue) => void;
    error?: string;
}) {
    // Không tải được danh sách -> về ô text tự do. Đây là fallback, không phải lỗi chặn.
    const [failed, setFailed] = useState(false);

    const [provinces, setProvinces] = useState<Province[]>([]);
    const [wards, setWards] = useState<Ward[]>([]);
    const [province, setProvince] = useState<Province | null>(null);
    const [ward, setWard] = useState<Ward | null>(null);
    /** Khối / xóm / thôn — không phải nơi nào cũng có nên tuỳ chọn, không lưu cột riêng. */
    const [block, setBlock] = useState('');

    useEffect(() => {
        getProvinces()
            .then(setProvinces)
            .catch(() => setFailed(true));
    }, []);

    const emit = (patch: {
        street?: string;
        block?: string;
        ward?: Ward | null;
        province?: Province | null;
    }) => {
        const street = patch.street ?? value.street;
        const b = patch.block ?? block;
        const w = patch.ward !== undefined ? patch.ward : ward;
        const p = patch.province !== undefined ? patch.province : province;

        onChange({
            address: compose(street, b, w, p),
            street,
            province_code: p?.code ?? null,
            ward_code: w?.code ?? null,
        });
    };

    const pickProvince = async (code: number) => {
        const p = provinces.find((x) => x.code === code) ?? null;
        setProvince(p);
        setWard(null);
        emit({ province: p, ward: null });

        if (!p) {
            setWards([]);

            return;
        }

        try {
            setWards(await getWards(p.code));
        } catch {
            setFailed(true);
        }
    };

    const pickWard = (code: number) => {
        const w = wards.find((x) => x.code === code) ?? null;
        setWard(w);
        emit({ ward: w });
    };

    // ---- Fallback: đúng ô text như trước khi có tính năng này ----
    if (failed) {
        return (
            <div>
                <input
                    value={value.address}
                    onChange={(e) =>
                        onChange({
                            address: e.target.value,
                            street: e.target.value,
                            province_code: null,
                            ward_code: null,
                        })
                    }
                    placeholder="Địa chỉ giao nhận"
                    aria-label="Địa chỉ giao nhận"
                    className={`${inputCls} ${error ? 'border-red-400' : 'border-cardBorder'}`}
                />
                <p className="mt-1 text-[12px] text-moss">
                    Không tải được danh sách địa chỉ, bạn nhập tay giúp nhé.
                </p>
                {error && (
                    <p className="mt-1 text-[12px] text-red-500">{error}</p>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            {/* Tỉnh + Xã lên TRƯỚC, chi tiết xuống dưới — chọn vùng rồi mới ghi số nhà. */}
            <div className="grid gap-2 sm:grid-cols-2">
                <select
                    value={province?.code ?? ''}
                    onChange={(e) => pickProvince(Number(e.target.value))}
                    aria-label="Tỉnh / Thành phố"
                    className={selectCls}
                    style={selectStyle}
                >
                    <option value="">Tỉnh / Thành phố</option>
                    {provinces.map((p) => (
                        <option key={p.code} value={p.code}>
                            {p.name}
                        </option>
                    ))}
                </select>

                <select
                    value={ward?.code ?? ''}
                    onChange={(e) => pickWard(Number(e.target.value))}
                    disabled={!province}
                    aria-label="Xã / Phường"
                    className={selectCls}
                    style={selectStyle}
                >
                    <option value="">
                        {province ? 'Xã / Phường' : 'Chọn tỉnh trước'}
                    </option>
                    {wards.map((w) => (
                        <option key={w.code} value={w.code}>
                            {w.name}
                        </option>
                    ))}
                </select>
            </div>

            <input
                value={block}
                onChange={(e) => {
                    setBlock(e.target.value);
                    emit({ block: e.target.value });
                }}
                placeholder="Khối / Xóm / Thôn (nếu có)"
                aria-label="Khối / Xóm / Thôn (nếu có)"
                className={`${inputCls} border-cardBorder`}
            />

            <input
                value={value.street}
                onChange={(e) => emit({ street: e.target.value })}
                placeholder="Số nhà, tên đường"
                aria-label="Số nhà, tên đường"
                className={`${inputCls} ${error ? 'border-red-400' : 'border-cardBorder'}`}
            />

            {error && <p className="text-[12px] text-red-500">{error}</p>}
        </div>
    );
}
