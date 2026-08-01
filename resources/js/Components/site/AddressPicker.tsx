import DivisionCombobox from '@/Components/site/DivisionCombobox';
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
    /**
     * Khoá ô xã trong lúc gọi API. Không có cờ này thì khách bấm vào ô ngay sau khi chọn
     * tỉnh sẽ mở ra một danh sách RỖNG rồi nó tự đóng — trông như hỏng, phải bấm lại.
     */
    const [dangTaiXa, setDangTaiXa] = useState(false);

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

    const pickProvince = async (p: Province | null) => {
        setProvince(p);
        setWard(null);
        emit({ province: p, ward: null });

        if (!p) {
            setWards([]);

            return;
        }

        setWards([]);
        setDangTaiXa(true);
        try {
            setWards(await getWards(p.code));
        } catch {
            setFailed(true);
        } finally {
            setDangTaiXa(false);
        }
    };

    const pickWard = (w: Ward | null) => {
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
                <DivisionCombobox
                    label="Tỉnh / Thành phố"
                    placeholder="Tỉnh / Thành phố"
                    items={provinces}
                    value={province}
                    onChange={pickProvince}
                />
                <DivisionCombobox
                    label="Xã / Phường"
                    placeholder={
                        dangTaiXa
                            ? 'Đang tải xã/phường…'
                            : province
                              ? 'Xã / Phường'
                              : 'Chọn tỉnh trước'
                    }
                    items={wards}
                    value={ward}
                    onChange={pickWard}
                    disabled={!province || dangTaiXa}
                />
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
