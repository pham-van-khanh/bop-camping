import {
    getLegaciesOfWard,
    getLegacyDistrictName,
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
 * kèm ô "địa chỉ cũ" tự tra và đường "tôi chỉ biết địa chỉ cũ".
 *
 * Vì sao đường cũ phải là SELECT chứ không phải ô chữ: endpoint from-legacy khớp theo TÊN
 * và bỏ qua tỉnh, nên "Phường 1" trả 51 kết quả của 51 tỉnh. Chọn bằng select mới có mã xã
 * cũ để lọc source_code.
 *
 * Map là quan hệ NHIỀU-NHIỀU theo CẢ HAI CHIỀU:
 *  - 1 xã cũ có thể bị CHIA cho nhiều xã mới (Phường Điện Biên -> 4 phường mới)
 *  - 1 xã mới thường GỘP từ nhiều xã cũ (Phường Ba Đình <- 10 xã cũ)
 * Nên cả hai chiều đều phải xử lý ca nhiều kết quả, không bao giờ điền bừa cái đầu tiên.
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

/** Select có mũi chevron riêng (appearance-none) để trông đồng bộ với input, không dùng mũi mặc định của OS. */
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

/** Ghép chuỗi địa chỉ khách nhìn thấy. Phần "(trước sát nhập: ...)" giúp shipper quen tên cũ. */
function compose(
    street: string,
    ward?: Ward | null,
    province?: Province | null,
    oldText?: string,
): string {
    const main = [street.trim(), ward?.name, province?.name]
        .filter(Boolean)
        .join(', ');
    const old = (oldText ?? '').trim();

    return old ? `${main} (trước sát nhập: ${old})` : main;
}

/** "Phường Trúc Bạch, Quận Ba Đình" — thêm tên huyện cho đủ nghĩa; lỗi thì chỉ lấy tên xã. */
async function describeLegacy(w: LegacyWard): Promise<string> {
    try {
        return `${w.name}, ${await getLegacyDistrictName(w.district_code)}`;
    } catch {
        return w.name;
    }
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

    /** Ô "địa chỉ cũ" — chuỗi tự do, tự điền nhưng khách sửa/xoá được. */
    const [oldText, setOldText] = useState('');
    /** Nhiều xã cũ gộp vào xã mới đang chọn -> cho khách chọn nhanh thay vì điền bừa. */
    const [legacyChoices, setLegacyChoices] = useState<LegacyWard[]>([]);

    // Đường "tôi chỉ biết địa chỉ cũ"
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

    /** null = chưa suy ra. Có giá trị = kết quả suy ra xã MỚI từ xã cũ đang chọn. */
    const [inferred, setInferred] = useState<{
        count: number;
        exact: boolean;
    } | null>(null);
    const [narrowed, setNarrowed] = useState(false);

    useEffect(() => {
        getProvinces()
            .then(setProvinces)
            .catch(() => setFailed(true));
    }, []);

    const emit = (patch: {
        street?: string;
        ward?: Ward | null;
        province?: Province | null;
        oldText?: string;
        legacyWardCode?: number | null;
    }) => {
        const street = patch.street ?? value.street;
        const w = patch.ward !== undefined ? patch.ward : ward;
        const p = patch.province !== undefined ? patch.province : province;
        const old = patch.oldText !== undefined ? patch.oldText : oldText;
        const legacyCode =
            patch.legacyWardCode !== undefined
                ? patch.legacyWardCode
                : value.legacy_ward_code;

        onChange({
            address: compose(street, w, p, old),
            street,
            province_code: p?.code ?? null,
            ward_code: w?.code ?? null,
            legacy_ward_code: legacyCode,
        });
    };

    /** Chọn xã MỚI -> tự tra địa chỉ cũ tương ứng để điền vào ô "địa chỉ cũ". */
    const fillOldFromNewWard = async (w: Ward) => {
        try {
            const legacies = await getLegaciesOfWard(w.code);

            if (legacies.length === 1) {
                const text = await describeLegacy(legacies[0]);
                setLegacyChoices([]);
                setOldText(text);
                emit({
                    ward: w,
                    oldText: text,
                    legacyWardCode: legacies[0].code,
                });

                return;
            }
            // Nhiều xã cũ gộp lại -> KHÔNG điền bừa, cho khách chọn.
            setLegacyChoices(legacies);
            setOldText('');
            emit({ ward: w, oldText: '', legacyWardCode: null });
        } catch {
            // Tra thất bại chỉ mất tiện lợi — khách vẫn tự gõ được.
            setLegacyChoices([]);
            emit({ ward: w });
        }
    };

    const pickProvince = async (code: number) => {
        const p = provinces.find((x) => x.code === code) ?? null;
        setProvince(p);
        setWard(null);
        setNarrowed(false);
        setInferred(null);
        setLegacyChoices([]);
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
        if (!w) {
            setLegacyChoices([]);
            emit({ ward: null });

            return;
        }
        emit({ ward: w });
        void fillOldFromNewWard(w);
    };

    /** Khách chọn 1 trong nhiều xã cũ đã gộp vào xã mới. */
    const pickLegacyChoice = async (code: number) => {
        const w = legacyChoices.find((x) => x.code === code) ?? null;
        if (!w) {
            setOldText('');
            emit({ oldText: '', legacyWardCode: null });

            return;
        }
        const text = await describeLegacy(w);
        setOldText(text);
        emit({ oldText: text, legacyWardCode: w.code });
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
            setOldText('');
            emit({ oldText: '', legacyWardCode: null });

            return;
        }

        // Khách tự khai địa chỉ cũ -> điền luôn vào ô "địa chỉ cũ", không cần tra ngược.
        const text = [lw.name, legacyDistrict?.name].filter(Boolean).join(', ');
        setOldText(text);
        setLegacyChoices([]);

        try {
            const { wards: candidates, exact } = await inferNewWards(
                lw.name,
                lw.code,
            );
            setInferred({ count: candidates.length, exact });

            if (candidates.length === 0) {
                setNarrowed(false);
                emit({ oldText: text, legacyWardCode: lw.code });

                return;
            }

            const p =
                provinces.find((x) => x.code === candidates[0].province_code) ??
                null;
            setProvince(p);
            setWards(candidates);
            setNarrowed(true);

            const w = exact ? candidates[0] : null;
            setWard(w);
            emit({
                province: p,
                ward: w,
                oldText: text,
                legacyWardCode: lw.code,
            });
        } catch {
            // Suy ra thất bại KHÔNG được chặn khách — vẫn chọn tay được ở phần trên.
            setInferred({ count: 0, exact: false });
            emit({ oldText: text, legacyWardCode: lw.code });
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
            {/* Tỉnh + Xã lên TRƯỚC, số nhà xuống dưới — chọn vùng rồi mới ghi chi tiết. */}
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
                value={value.street}
                onChange={(e) => emit({ street: e.target.value })}
                placeholder="Số nhà, tên đường"
                aria-label="Số nhà, tên đường"
                className={`${inputCls} ${error ? 'border-red-400' : 'border-cardBorder'}`}
            />

            {/* Kết quả suy ra xã MỚI từ xã cũ */}
            {inferred?.exact && (
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
            {inferred?.count === 0 && (
                <p className="text-[12px] text-[#8a6d1f]">
                    Không tra được tự động, bạn chọn giúp tỉnh và xã ở ô trên.
                </p>
            )}
            {narrowed && inferred?.exact && (
                <button
                    type="button"
                    onClick={showAllWards}
                    className="text-[12px] font-semibold text-grass underline"
                >
                    Xem tất cả xã của tỉnh
                </button>
            )}

            {error && <p className="text-[12px] text-red-500">{error}</p>}

            {/* Ô địa chỉ cũ — chỉ hiện SAU khi đã chọn xong xã mới. */}
            {ward && (
                <div className="rounded-[11px] border border-cardBorder bg-[#fbfcf7] p-3">
                    <label
                        htmlFor="address-old"
                        className="mb-1.5 block text-[12.5px] font-semibold text-pine"
                    >
                        Địa chỉ cũ (trước sát nhập){' '}
                        <span className="font-normal text-moss">
                            — tụi mình tự tra, giúp shipper quen tên cũ dễ tìm
                        </span>
                    </label>

                    {legacyChoices.length > 1 && (
                        <select
                            value=""
                            onChange={(e) =>
                                pickLegacyChoice(Number(e.target.value))
                            }
                            aria-label="Chọn địa chỉ cũ tương ứng"
                            className={`${selectCls} mb-2`}
                            style={selectStyle}
                        >
                            <option value="">
                                {ward.name} gộp từ {legacyChoices.length}{' '}
                                xã/phường cũ — chọn nơi của bạn
                            </option>
                            {legacyChoices.map((w) => (
                                <option key={w.code} value={w.code}>
                                    {w.name}
                                </option>
                            ))}
                        </select>
                    )}

                    <input
                        id="address-old"
                        value={oldText}
                        onChange={(e) => {
                            setOldText(e.target.value);
                            emit({ oldText: e.target.value });
                        }}
                        placeholder="Ví dụ: Phường Điện Biên, Quận Ba Đình"
                        aria-label="Địa chỉ cũ (trước sát nhập)"
                        className={`${inputCls} border-cardBorder`}
                    />
                </div>
            )}

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
                        Tra từ địa chỉ cũ (trước sát nhập 07/2025)
                    </div>
                    <div className="grid gap-2">
                        <select
                            value={legacyProvince?.code ?? ''}
                            onChange={(e) =>
                                pickLegacyProvince(Number(e.target.value))
                            }
                            aria-label="Tỉnh cũ"
                            className={selectCls}
                            style={selectStyle}
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
                            style={selectStyle}
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
                            style={selectStyle}
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
