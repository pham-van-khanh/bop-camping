<?php

namespace App\Services;

use App\Models\DurationDiscountTier;
use Illuminate\Support\Collection;

/**
 * Nguồn CHÂN LÝ tính giá thuê có áp bậc giảm dài ngày (bopcamping-e36e).
 * Mọi nơi (checkout, giỏ, combo, đổi lịch) gọi service này — KHÔNG lặp công thức.
 *
 * Layering (xem artifacts/adr_duration_discount.md):
 *   gross = perDay × qty × days
 *   net   = round(gross × (1 − tier%/100))      ← lưu order_items.subtotal
 * Giảm dài ngày là tầng GIÁ, ngoài trần voucher 25% (voucher áp thêm trên net).
 */
class RentalPricingService
{
    /** @var Collection<int, DurationDiscountTier>|null Cache theo request (bậc active, min_days giảm dần). */
    private ?Collection $tiers = null;

    /** % giảm của bậc áp cho số ngày (bậc cao nhất có min_days ≤ days); 0 nếu không bậc nào. */
    public function tierPercentForDays(int $days): float
    {
        if ($days < 1) {
            return 0.0;
        }

        $tier = $this->activeTiers()->firstWhere(fn (DurationDiscountTier $t) => $days >= $t->min_days);

        return $tier ? (float) $tier->discount_percent : 0.0;
    }

    /**
     * Tính 1 dòng thuê: giá gốc, % bậc, giá sau giảm.
     *
     * @return array{gross:int, percent:float, net:int}
     */
    public function priceLine(int $perDay, int $qty, int $days): array
    {
        $gross = max(0, $perDay) * max(0, $qty) * max(0, $days);
        $percent = $this->tierPercentForDays($days);
        $net = (int) round($gross * (1 - $percent / 100));

        return ['gross' => $gross, 'percent' => $percent, 'net' => $net];
    }

    /** @return Collection<int, DurationDiscountTier> */
    private function activeTiers(): Collection
    {
        return $this->tiers ??= DurationDiscountTier::activeDescending();
    }
}
