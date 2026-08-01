import { getProvinces, getWards } from '@/lib/divisions';
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
});

describe('cache sessionStorage cho danh sách tỉnh', () => {
    it('getProvinces gọi 2 lần chỉ fetch 1 lần', async () => {
        vi.mocked(fetch).mockResolvedValueOnce(jsonResponse([sampleProvince]));

        const first = await getProvinces();
        const second = await getProvinces();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(second).toEqual(first);
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
