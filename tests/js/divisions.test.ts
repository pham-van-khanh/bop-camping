import {
    getLegacyDistricts,
    getLegacyProvinces,
    getLegacyWards,
    getProvinces,
    getWards,
    inferNewWards,
} from '@/lib/divisions';
import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * lib/divisions gọi trực tiếp https://provinces.open-api.vn/api từ FE (không import vào DB —
 * xem artifacts/plan_address_picker.md mục T2). Test mock hết `fetch`, KHÔNG gọi API thật.
 */

const BASE = 'https://provinces.open-api.vn/api';

function jsonResponse(data: unknown, ok = true, status = 200) {
    return {
        ok,
        status,
        json: async () => data,
    } as unknown as Response;
}

function brokenJsonResponse(status = 200) {
    return {
        ok: true,
        status,
        json: async () => {
            throw new Error('invalid json');
        },
    } as unknown as Response;
}

const sampleProvince = {
    name: 'Thành phố Hà Nội',
    code: 1,
    division_type: 'thành phố trung ương',
    codename: 'thanh_pho_ha_noi',
    phone_code: 24,
};

const sampleWard = {
    name: 'Phường Ba Đình',
    code: 101,
    codename: 'phuong_ba_dinh',
    division_type: 'phường',
};

const sampleLegacyDistrict = {
    name: 'Quận Ba Đình',
    code: 1,
    division_type: 'quận',
    codename: 'quan_ba_dinh',
    province_code: 1,
};

const sampleLegacyWard = {
    name: 'Phường Điện Biên',
    code: 19,
    codename: 'phuong_dien_bien',
    division_type: 'phường',
    district_code: 1,
};

beforeEach(() => {
    global.fetch = vi.fn();
    window.sessionStorage.clear();
});

describe('ghép URL đúng cho từng hàm', () => {
    it('getProvinces gọi v2/?depth=1', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([sampleProvince]));

        await getProvinces();

        expect(fetch).toHaveBeenCalledWith(`${BASE}/v2/?depth=1`);
    });

    it('getWards gọi v2/p/{code}?depth=2', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse({ ...sampleProvince, wards: [sampleWard] }),
        );

        await getWards(1);

        expect(fetch).toHaveBeenCalledWith(`${BASE}/v2/p/1?depth=2`);
    });

    it('getLegacyProvinces gọi v1/?depth=1', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([sampleProvince]));

        await getLegacyProvinces();

        expect(fetch).toHaveBeenCalledWith(`${BASE}/v1/?depth=1`);
    });

    it('getLegacyDistricts gọi v1/p/{code}?depth=2', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse({
                ...sampleProvince,
                districts: [sampleLegacyDistrict],
            }),
        );

        await getLegacyDistricts(1);

        expect(fetch).toHaveBeenCalledWith(`${BASE}/v1/p/1?depth=2`);
    });

    it('getLegacyWards gọi v1/d/{code}?depth=2', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse({
                ...sampleLegacyDistrict,
                wards: [sampleLegacyWard],
            }),
        );

        await getLegacyWards(1);

        expect(fetch).toHaveBeenCalledWith(`${BASE}/v1/d/1?depth=2`);
    });

    it('inferNewWards gọi v2/w/from-legacy/?legacy_name=<tên> có encode', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([]));

        await inferNewWards('Phường Điện Biên', 19);

        expect(fetch).toHaveBeenCalledWith(
            `${BASE}/v2/w/from-legacy/?legacy_name=${encodeURIComponent('Phường Điện Biên')}`,
        );
    });
});

describe('inferNewWards — lọc đúng theo source_code (map nhiều-nhiều)', () => {
    it('nhiều source_code khác nhau cùng tên -> chỉ trả đúng nhóm của mã đã chọn', async () => {
        const wardA1 = {
            ...sampleWard,
            code: 201,
            name: 'Phường Điện Biên A1',
        };
        const wardOther = {
            ...sampleWard,
            code: 999,
            name: 'Phường 1 của tỉnh khác',
        };

        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse([
                { source_code: 19, ward: wardA1 },
                { source_code: 55, ward: wardOther },
                { source_code: 55, ward: { ...wardOther, code: 998 } },
            ]),
        );

        const result = await inferNewWards('Phường Điện Biên', 19);

        expect(result.wards).toEqual([wardA1]);
        expect(result.exact).toBe(true);
    });

    it('ca xã cũ bị chia: 1 legacyWardCode ứng với 4 ward mới -> trả 4, exact=false', async () => {
        const wards = [201, 202, 203, 204].map((code) => ({
            ...sampleWard,
            code,
            name: `Phường ${code}`,
        }));

        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse([
                ...wards.map((ward) => ({ source_code: 19, ward })),
                { source_code: 20, ward: { ...sampleWard, code: 999 } },
            ]),
        );

        const result = await inferNewWards('Phường Điện Biên', 19);

        expect(result.wards).toHaveLength(4);
        expect(result.wards.map((w) => w.code)).toEqual([201, 202, 203, 204]);
        expect(result.exact).toBe(false);
    });

    it('ca đúng 1 ward -> exact=true', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse([{ source_code: 19, ward: sampleWard }]),
        );

        const result = await inferNewWards('Phường Điện Biên', 19);

        expect(result.wards).toEqual([sampleWard]);
        expect(result.exact).toBe(true);
    });

    it('source_code không khớp gì -> trả rỗng, exact=false', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse([{ source_code: 55, ward: sampleWard }]),
        );

        const result = await inferNewWards('Phường Điện Biên', 19);

        expect(result.wards).toEqual([]);
        expect(result.exact).toBe(false);
    });
});

describe('cache sessionStorage cho danh sách tỉnh', () => {
    it('getProvinces gọi 2 lần chỉ fetch 1 lần', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([sampleProvince]));

        const first = await getProvinces();
        const second = await getProvinces();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(second).toEqual(first);
    });

    it('getLegacyProvinces gọi 2 lần chỉ fetch 1 lần', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([sampleProvince]));

        await getLegacyProvinces();
        await getLegacyProvinces();

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('sessionStorage.setItem throw (Safari private) -> vẫn trả dữ liệu đúng', async () => {
        vi.spyOn(window.sessionStorage, 'setItem').mockImplementation(() => {
            throw new Error('QuotaExceededError');
        });
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([sampleProvince]));

        const result = await getProvinces();

        expect(result).toEqual([sampleProvince]);
    });

    it('sessionStorage.getItem throw -> vẫn fetch lại và trả dữ liệu đúng', async () => {
        vi.spyOn(window.sessionStorage, 'getItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });
        vi.mocked(fetch).mockResolvedValue(jsonResponse([sampleProvince]));

        const result = await getProvinces();

        expect(result).toEqual([sampleProvince]);
    });
});

describe('xử lý lỗi -> throw để component fallback về ô text', () => {
    it('fetch trả 500 -> throw', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse({ message: 'error' }, false, 500),
        );

        await expect(getProvinces()).rejects.toThrow();
    });

    it('JSON rác (không parse được) -> throw', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(brokenJsonResponse());

        await expect(getProvinces()).rejects.toThrow();
    });

    it('JSON hợp lệ nhưng sai định dạng mảng -> throw', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse({ not: 'an array' }),
        );

        await expect(getProvinces()).rejects.toThrow();
    });

    it('getWards thiếu field "wards" trong object -> throw', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(
            jsonResponse({ ...sampleProvince }),
        );

        await expect(getWards(1)).rejects.toThrow();
    });

    it('getWards trả JSON là mảng thay vì object -> throw', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([sampleWard]));

        await expect(getWards(1)).rejects.toThrow();
    });
});
