<?php

namespace Database\Seeders;

use App\Models\DurationDiscountTier;
use Illuminate\Database\Seeder;

/**
 * Bậc giảm giá thuê dài ngày mẫu (bopcamping-e36e): ≥5 ngày −20%, ≥10 ngày −30%.
 * Admin chỉnh ở /admin/promotion.
 */
class DurationDiscountTierSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['min_days' => 5, 'discount_percent' => 20], ['min_days' => 10, 'discount_percent' => 30]] as $tier) {
            DurationDiscountTier::updateOrCreate(['min_days' => $tier['min_days']], $tier);
        }
    }
}
