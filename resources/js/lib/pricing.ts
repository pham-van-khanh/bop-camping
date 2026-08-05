// Giảm giá thuê dài ngày — mirror server RentalPricingService (bopcamping-e36e).
// Bậc lấy từ Inertia shared prop `durationTiers` (KHÔNG hardcode %).
// Bản chất: điều chỉnh GIÁ THUÊ, ngoài trần voucher; voucher áp thêm trên net.

export type DurationTier = { minDays: number; percent: number };

/** % giảm của bậc áp cho số ngày (bậc có minDays lớn nhất ≤ days); 0 nếu không bậc nào. */
export function durationTierPercent(
    days: number,
    tiers: DurationTier[],
): number {
    if (days < 1 || !tiers?.length) return 0;
    // tiers đã sort minDays giảm dần từ server; vẫn tự sort để an toàn.
    const sorted = [...tiers].sort((a, b) => b.minDays - a.minDays);
    const tier = sorted.find((t) => days >= t.minDays);
    return tier ? tier.percent : 0;
}

/** Giá net sau bậc, khớp server: round(gross × (1 − %/100)). */
export function netFromGross(
    gross: number,
    days: number,
    tiers: DurationTier[],
): number {
    const percent = durationTierPercent(days, tiers);
    return Math.round(gross * (1 - percent / 100));
}
