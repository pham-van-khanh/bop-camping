import {
    getLegacyDistricts,
    getLegacyProvinces,
    getLegacyWards,
    getProvinces,
    getWards,
    inferNewWards,
    type LegacyDistrict,
    type LegacyWard,
    type Province,
    type Ward,
} from '@/lib/divisions';
import { useEffect, useState } from 'react';

/**
 * bopcamping-vj4x — chọn địa chỉ theo cấu trúc SAU sát nhập 07/2025 (34 tỉnh, 2 cấp),
 * kèm đường "tôi chỉ biết địa chỉ cũ" (63 tỉnh, 3 cấp) rồi suy ra địa chỉ mới.
 *
 * Vì sao đường cũ phải là SELECT chứ không phải ô chữ: endpoint from-legacy khớp theo TÊN
 * và bỏ qua tỉnh, nên "Phường 1" trả 51 kết quả của 51 tỉnh. Chọn bằng select mới có mã xã
 * cũ để lọc source_code.
 *
 * Map là quan hệ NHIỀU-NHIỀU: một xã cũ có thể bị CHIA cho nhiều xã mới (đo thật: Phường
 * Điện Biên -> 4 phường mới). Nên phải xử lý cả 3 ca 1 / N / 0, không giả định luôn ra 1.
 *
 * FALLBACK: mọi lỗi mạng -> về đúng ô text tự do như trước khi có tính năng này.
 * Không bao giờ chặn khách đặt hàng vì API địa chỉ.
 */

export type AddressValue = {
    /** Chuỗi hoàn chỉnh — NGUỒN CHÂN LÝ cho giao nhận, lưu vào orders.customer_address. */
    address: string;
    street: string;
    province_code: number | null;
    ward_code: number | null;
    legacy_ward_code: number | null;
};

const selectCls =
    'h-[46px] w-full rounded-[11px] border border-cardBorder bg-white px-3 text-[14px] text-ink outline-none focus:border-grass disabled:bg-[#f6f8f1] disabled:text-[#aab39a]';
const inputCls =
    'h-[46px] w-full rounded-[11px] border bg-white px-3.5 text-[14px] text-ink outline-none focus:border-grass';

/** Ghép chuỗi địa chỉ khách nhìn thấy. Phần "(trước sát nhập: ...)" giúp shipper quen tên cũ. */
function compose(
    street: string,
    ward?: Ward | null,
    province?: Province | null,
    legacyWard?: LegacyWard | null,
    legacyDistrict?: LegacyDistrict | null,
): string {
    const main = [street.trim(), ward?.name, province?.name]
        .filter(Boolean)
        .join(', ');

    if (!legacyWard) {
        return main;
    }
    const old = [legacyWard.name, legacyDistrict?.name]
        .filter(Boolean)
        .join(', ');

    return old ? `${main} (trước sát nhập: ${old})` : main;
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

    // Đường "địa chỉ cũ"
    const [legacyOpen, setLegacyOpen] = useState(false);
    const [legacyProvinces, setLegacyProvinces] = useState<Province[]>([]);
    const [legacyDistricts, setLegacyDistricts] = useState<LegacyDistrict[]>(
        [],
    );
    const [legacyWards, setLegacyWards] = useState<LegacyWard[]>([]);
    const [legacyProvince, setLegacyProvince] = useState<Province | null>(null);
    const [legacyDistrict, setLegacyDistrict] = useState<LegacyDistrict | null>(
        null,
    );
    const [legacyWard, setLegacyWard] = useState<LegacyWard | null>(null);

    /** null = chưa suy ra. Có giá trị = kết quả suy ra cho xã cũ đang chọn. */
    const [inferred, setInferred] = useState<{
        count: number;
        exact: boolean;
    } | null>(null);
    /** true = đang thu hẹp danh sách xã mới theo kết quả suy ra (khách bấm được để mở full). */
    const [narrowed, setNarrowed] = useState(false);

    useEffect(() => {
        getProvinces()
            .then(setProvinces)
            .catch(() => setFailed(true));
    }, []);

    /** Gọi onChange với chuỗi ghép lại từ state hiện tại. */
    const emit = (patch: {
        street?: string;
        ward?: Ward | null;
        province?: Province | null;
        legacyWard?: LegacyWard | null;
        legacyDistrict?: LegacyDistrict | null;
    }) => {
        const street = patch.street ?? value.street;
        const w = patch.ward !== undefined ? patch.ward : ward;
        const p = patch.province !== undefined ? patch.province : province;
        const lw =
            patch.legacyWard !== undefined ? patch.legacyWard : legacyWard;
        const ld =
            patch.legacyDistrict !== undefined
                ? patch.legacyDistrict
                : legacyDistrict;

        onChange({
            address: compose(street, w, p, lw, ld),
            street,
            province_code: p?.code ?? null,
            ward_code: w?.code ?? null,
            legacy_ward_code: lw?.code ?? null,
        });
    };

    const pickProvince = async (code: number) => {
        const p = provinces.find((x) => x.code === code) ?? null;
        setProvince(p);
        setWard(null);
        setNarrowed(false);
        setInferred(null);
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

    const openLegacy = async () => {
        setLegacyOpen(true);
        if (legacyProvinces.length > 0) {
            return;
        }
        try {
            setLegacyProvinces(await getLegacyProvinces());
        } catch {
            setFailed(true);
        }
    };

    const pickLegacyProvince = async (code: number) => {
        const p = legacyProvinces.find((x) => x.code === code) ?? null;
        setLegacyProvince(p);
        setLegacyDistrict(null);
        setLegacyWard(null);
        setLegacyWards([]);
        setInferred(null);
        emit({ legacyWard: null, legacyDistrict: null });

        if (!p) {
            setLegacyDistricts([]);

            return;
        }
        try {
            setLegacyDistricts(await getLegacyDistricts(p.code));
        } catch {
            setFailed(true);
        }
    };

    const pickLegacyDistrict = async (code: number) => {
        const d = legacyDistricts.find((x) => x.code === code) ?? null;
        setLegacyDistrict(d);
        setLegacyWard(null);
        setInferred(null);
        emit({ legacyWard: null, legacyDistrict: d });

        if (!d) {
            setLegacyWards([]);

            return;
        }
        try {
            setLegacyWards(await getLegacyWards(d.code));
        } catch {
            setFailed(true);
        }
    };

    /** Chọn xã CŨ -> suy ra xã MỚI. Ba ca: 1 (tự điền) / N (thu hẹp) / 0 (giữ full list). */
    const pickLegacyWard = async (code: number) => {
        const lw = legacyWards.find((x) => x.code === code) ?? null;
        setLegacyWard(lw);

        if (!lw) {
            setInferred(null);
            emit({ legacyWard: null });

            return;
        }

        try {
            const { wards: candidates, exact } = await inferNewWards(
                lw.name,
                lw.code,
            );
            setInferred({ count: candidates.length, exact });

            if (candidates.length === 0) {
                setNarrowed(false);
                emit({ legacyWard: lw });

                return;
            }

            // Ứng viên mang province_code -> nạp đúng tỉnh mới rồi thu hẹp danh sách xã.
            const provCode = candidates[0].province_code;
            const p = provinces.find((x) => x.code === provCode) ?? null;
            setProvince(p);
            setWards(candidates);
            setNarrowed(true);

            const w = exact ? candidates[0] : null;
            setWard(w);
            emit({ legacyWard: lw, province: p, ward: w });
        } catch {
            // Suy ra thất bại KHÔNG được chặn khách — vẫn chọn tay được ở phần trên.
            setInferred({ count: 0, exact: false });
            emit({ legacyWard: lw });
        }
    };

    const showAllWards = async () => {
        setNarrowed(false);
        setInferred(null);
        if (!province) {
            return;
        }
        try {
            setWards(await getWards(province.code));
        } catch {
            setFailed(true);
        }
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
                            legacy_ward_code: null,
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
            <input
                value={value.street}
                onChange={(e) => emit({ street: e.target.value })}
                placeholder="Số nhà, tên đường"
                aria-label="Số nhà, tên đường"
                className={`${inputCls} ${error ? 'border-red-400' : 'border-cardBorder'}`}
            />

            <div className="grid gap-2 sm:grid-cols-2">
                <select
                    value={province?.code ?? ''}
                    onChange={(e) => pickProvince(Number(e.target.value))}
                    aria-label="Tỉnh / Thành phố"
                    className={selectCls}
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

            {/* Kết quả suy ra từ địa chỉ cũ */}
            {inferred && inferred.exact && (
                <p className="text-[12px] text-grass">
                    Đã suy ra: <b>{ward?.name}</b>. Sửa lại nếu chưa đúng.
                </p>
            )}
            {inferred && !inferred.exact && inferred.count > 1 && (
                <p className="text-[12px] text-[#8a6d1f]">
                    <b>{legacyWard?.name}</b> cũ nay thuộc {inferred.count}{' '}
                    xã/phường mới — chọn giúp nơi của bạn ở ô trên.{' '}
                    <button
                        type="button"
                        onClick={showAllWards}
                        className="font-semibold text-grass underline"
                    >
                        Xem tất cả xã của tỉnh
                    </button>
                </p>
            )}
            {inferred && inferred.count === 0 && (
                <p className="text-[12px] text-[#8a6d1f]">
                    Không tra được tự động, bạn chọn giúp tỉnh và xã ở ô trên.
                </p>
            )}
            {narrowed && inferred && inferred.exact && (
                <button
                    type="button"
                    onClick={showAllWards}
                    className="text-[12px] font-semibold text-grass underline"
                >
                    Xem tất cả xã của tỉnh
                </button>
            )}

            {error && <p className="text-[12px] text-red-500">{error}</p>}

            {!legacyOpen ? (
                <button
                    type="button"
                    onClick={openLegacy}
                    className="text-[12.5px] font-semibold text-grass underline"
                >
                    Tôi chỉ biết địa chỉ cũ (trước sát nhập)
                </button>
            ) : (
                <div className="rounded-[11px] border border-cardBorder bg-[#fbfcf7] p-3">
                    <div className="mb-2 text-[12.5px] font-bold text-ink">
                        Địa chỉ cũ (trước sát nhập 07/2025)
                    </div>
                    <div className="grid gap-2 sm:grid-cols-3">
                        <select
                            value={legacyProvince?.code ?? ''}
                            onChange={(e) =>
                                pickLegacyProvince(Number(e.target.value))
                            }
                            aria-label="Tỉnh cũ"
                            className={selectCls}
                        >
                            <option value="">Tỉnh cũ</option>
                            {legacyProvinces.map((p) => (
                                <option key={p.code} value={p.code}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={legacyDistrict?.code ?? ''}
                            onChange={(e) =>
                                pickLegacyDistrict(Number(e.target.value))
                            }
                            disabled={!legacyProvince}
                            aria-label="Quận / Huyện cũ"
                            className={selectCls}
                        >
                            <option value="">Quận / Huyện cũ</option>
                            {legacyDistricts.map((d) => (
                                <option key={d.code} value={d.code}>
                                    {d.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={legacyWard?.code ?? ''}
                            onChange={(e) =>
                                pickLegacyWard(Number(e.target.value))
                            }
                            disabled={!legacyDistrict}
                            aria-label="Xã / Phường cũ"
                            className={selectCls}
                        >
                            <option value="">Xã / Phường cũ</option>
                            {legacyWards.map((w) => (
                                <option key={w.code} value={w.code}>
                                    {w.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <p className="mt-2 text-[11.5px] text-moss">
                        Chọn xong, tụi mình tự tra địa chỉ mới tương ứng. Bạn
                        vẫn sửa được ở ô phía trên.
                    </p>
                </div>
            )}
        </div>
    );
}
