// Định dạng dùng chung (RULES mục 6): tiền VND + ngày tháng.

export const money = (n: number) => Number(n).toLocaleString('vi-VN') + 'đ';

const pad = (n: number) => String(n).padStart(2, '0');

/** ISO yyyy-mm-dd (local, không lệch timezone) */
export const toISO = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export const fromISO = (s: string) => {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
};

/** dd/mm */
export const ddmm = (s: string) => {
    const d = fromISO(s);
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}`;
};

/** Số ngày thuê (bao gồm cả ngày nhận và trả) */
export const dayCount = (startISO: string, endISO: string) => {
    const a = fromISO(startISO).getTime();
    const b = fromISO(endISO).getTime();
    return Math.round((b - a) / 86400000) + 1;
};

export const rangeText = (startISO?: string | null, endISO?: string | null) => {
    if (!startISO) return 'Chưa chọn ngày';
    if (!endISO || endISO === startISO) return ddmm(startISO);
    return `${ddmm(startISO)} → ${ddmm(endISO)}`;
};

export const todayISO = () => {
    const d = new Date();
    return toISO(new Date(d.getFullYear(), d.getMonth(), d.getDate()));
};
