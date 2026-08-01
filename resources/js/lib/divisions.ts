// Gọi trực tiếp API địa giới hành chính https://provinces.open-api.vn (xem
// artifacts/plan_address_picker.md mục T2). Không import vào DB — chỉ gọi từ FE lúc
// khách chọn địa chỉ. Mọi hàm throw Error rõ ràng khi lỗi để AddressPicker bắt và
// fallback về ô text tự do (KHÔNG bao giờ được chặn đặt hàng).

const BASE = 'https://provinces.open-api.vn/api';

/** Tỉnh/thành — dùng chung cho v2 (34 tỉnh, sau sát nhập) và v1 (63 tỉnh, trước sát nhập). */
export type Province = {
    name: string;
    code: number;
    division_type: string;
    codename: string;
    phone_code: number;
};

/** Xã/phường sau sát nhập (v2), lấy từ `wards` của response v2/p/{code}?depth=2. */
export type Ward = {
    name: string;
    code: number;
    codename: string;
    division_type: string;
    short_codename?: string;
};

const SESSION_CACHE_KEYS = {
    provinces: 'bopcamping.divisions.provinces.v2',
    legacyProvinces: 'bopcamping.divisions.provinces.v1',
} as const;

/** true nếu đang chạy trong trình duyệt và sessionStorage khả dụng (SSR-safe). */
function hasSessionStorage(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.sessionStorage !== 'undefined'
    );
}

function readSessionCache<T>(key: string): T | null {
    if (!hasSessionStorage()) {
        return null;
    }
    try {
        const raw = window.sessionStorage.getItem(key);
        if (!raw) {
            return null;
        }
        return JSON.parse(raw) as T;
    } catch {
        // JSON hỏng hoặc getItem lỗi (Safari private) — coi như không có cache.
        return null;
    }
}

function writeSessionCache<T>(key: string, data: T): void {
    if (!hasSessionStorage()) {
        return;
    }
    try {
        window.sessionStorage.setItem(key, JSON.stringify(data));
    } catch {
        // Safari private mode / vượt quota — bỏ qua, dữ liệu vẫn trả về bình thường,
        // chỉ là lần gọi sau sẽ fetch lại thay vì lấy từ cache.
    }
}

/** fetch + parse JSON, throw Error rõ ràng khi HTTP lỗi hoặc JSON không parse được. */
async function fetchJson(url: string): Promise<unknown> {
    const res = await fetch(url);
    if (!res.ok) {
        throw new Error(
            `API địa giới hành chính trả lỗi ${res.status}: ${url}`,
        );
    }
    try {
        return await res.json();
    } catch {
        throw new Error(
            `API địa giới hành chính trả JSON không hợp lệ: ${url}`,
        );
    }
}

/** fetchJson + ép kiểu mảng, throw nếu response không phải mảng (JSON rác). */
async function fetchArray<T>(url: string): Promise<T[]> {
    const data = await fetchJson(url);
    if (!Array.isArray(data)) {
        throw new Error(
            `API địa giới hành chính trả dữ liệu không đúng định dạng mảng: ${url}`,
        );
    }
    return data as T[];
}

/** fetchJson + ép kiểu object, throw nếu response không phải object (JSON rác). */
async function fetchObject<T extends Record<string, unknown>>(
    url: string,
): Promise<T> {
    const data = await fetchJson(url);
    if (typeof data !== 'object' || data === null || Array.isArray(data)) {
        throw new Error(
            `API địa giới hành chính trả dữ liệu không đúng định dạng object: ${url}`,
        );
    }
    return data as T;
}

/** Lấy field mảng con (vd `wards`, `districts`) từ object cha, throw nếu thiếu/sai kiểu. */
function requireArrayField<T>(
    obj: Record<string, unknown>,
    field: string,
    url: string,
): T[] {
    const value = obj[field];
    if (!Array.isArray(value)) {
        throw new Error(
            `API địa giới hành chính thiếu "${field}" hợp lệ trong response: ${url}`,
        );
    }
    return value as T[];
}

async function getCachedProvinceList(
    cacheKey: string,
    url: string,
): Promise<Province[]> {
    const cached = readSessionCache<Province[]>(cacheKey);
    if (cached) {
        return cached;
    }
    const data = await fetchArray<Province>(url);
    writeSessionCache(cacheKey, data);
    return data;
}

/** 34 tỉnh/thành sau sát nhập (v2/?depth=1). Cache sessionStorage — danh sách gần như tĩnh. */
export async function getProvinces(): Promise<Province[]> {
    return getCachedProvinceList(
        SESSION_CACHE_KEYS.provinces,
        `${BASE}/v2/?depth=1`,
    );
}

/** Các xã/phường mới thuộc 1 tỉnh sau sát nhập (v2/p/{code}?depth=2). Không cache. */
export async function getWards(provinceCode: number): Promise<Ward[]> {
    const url = `${BASE}/v2/p/${provinceCode}?depth=2`;
    const data = await fetchObject<Record<string, unknown>>(url);
    return requireArrayField<Ward>(data, 'wards', url);
}
