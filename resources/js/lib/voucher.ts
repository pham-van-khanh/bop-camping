// Định dạng + nhãn voucher (DRY — dùng ở Account + Cart).
import { money } from '@/lib/format';

export type VoucherType = 'fixed' | 'percent';

/** "Giảm 30.000đ" / "Giảm 10%". */
export function voucherValueText(type: VoucherType, value: number): string {
    return type === 'percent' ? `Giảm ${value}%` : `Giảm ${money(value)}`;
}

export const VOUCHER_SOURCE_LABEL: Record<string, string> = {
    referral_reward: 'Thưởng giới thiệu',
    first_order: 'Ưu đãi đơn đầu',
    manual_admin: 'Ưu đãi từ shop',
    campaign: 'Khuyến mãi',
};

export const VOUCHER_SOURCE_FALLBACK = 'Ưu đãi';

export type PromoInfo = {
    enabled: boolean;
    maxDiscountPercent: number;
    maxStack: number;
    minOrderAmount: number;
    refereeDiscountType: VoucherType;
    refereeDiscountValue: number;
};

export type AvailableVoucher = {
    code: string;
    type: VoucherType;
    value: number;
    source: string;
    expires_at: string | null;
};

const rawDiscount = (type: VoucherType, value: number, base: number): number =>
    type === 'percent' ? Math.floor((base * value) / 100) : Math.round(value);

/** Ưu đãi thêm email cho đơn đầu (EmailBonusService) — props từ CartController. */
export type EmailBonusInfo = {
    type: VoucherType;
    value: number;
    eligible: boolean; // đơn này sẽ được giảm (đơn đầu + email đã xác thực)
    canEarn: boolean; // còn suất đơn đầu nhưng tài khoản chưa có email xác thực
};

/**
 * Ước tính giảm giá ở client (tạm tính — server là nguồn chân lý khi đặt đơn).
 * Mirror VAN AN TOÀN: tổng giảm ≤ maxDiscountPercent% giá trị thuê.
 */
export function estimateDiscount(params: {
    rentalTotal: number;
    promo: PromoInfo;
    refereeValue: number | null; // giảm referee đơn đầu (đã tính), null nếu không áp
    emailBonusValue?: number | null; // giảm email-bonus đơn đầu (đã tính), null nếu không áp
    selectedVouchers: AvailableVoucher[];
}): {
    total: number;
    capped: boolean;
    lines: { label: string; amount: number }[];
} {
    const {
        rentalTotal,
        promo,
        refereeValue,
        emailBonusValue,
        selectedVouchers,
    } = params;
    const lines: { label: string; amount: number }[] = [];

    if (refereeValue && refereeValue > 0) {
        lines.push({
            label: 'Ưu đãi đơn đầu (mã giới thiệu)',
            amount: refereeValue,
        });
    }
    if (emailBonusValue && emailBonusValue > 0) {
        lines.push({
            label: 'Ưu đãi thêm email (đơn đầu)',
            amount: emailBonusValue,
        });
    }
    for (const v of selectedVouchers) {
        lines.push({
            label: `Voucher ${voucherValueText(v.type, v.value)}`,
            amount: rawDiscount(v.type, v.value, rentalTotal),
        });
    }

    const rawTotal = lines.reduce((s, l) => s + l.amount, 0);
    const cap = Math.floor((rentalTotal * promo.maxDiscountPercent) / 100);
    const total = Math.max(0, Math.min(rawTotal, cap, rentalTotal));

    return { total, capped: total < rawTotal, lines };
}
