<?php

namespace Tests\Feature;

use App\Models\DurationDiscountTier;
use App\Services\RentalPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-e36e — nguồn chân lý giá thuê + bậc giảm dài ngày.
 * Bậc mẫu: ≥5 ngày −20%, ≥10 ngày −30%.
 */
class RentalPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private RentalPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        DurationDiscountTier::create(['min_days' => 5, 'discount_percent' => 20, 'is_active' => true]);
        DurationDiscountTier::create(['min_days' => 10, 'discount_percent' => 30, 'is_active' => true]);
        $this->pricing = new RentalPricingService;
    }

    /** @test */
    public function picks_highest_matching_tier_by_min_days(): void
    {
        $this->assertSame(0.0, $this->pricing->tierPercentForDays(4));   // dưới ngưỡng
        $this->assertSame(20.0, $this->pricing->tierPercentForDays(5));  // biên dưới bậc 1
        $this->assertSame(20.0, $this->pricing->tierPercentForDays(9));
        $this->assertSame(30.0, $this->pricing->tierPercentForDays(10)); // biên bậc 2 đè bậc 1
        $this->assertSame(30.0, $this->pricing->tierPercentForDays(20));
        $this->assertSame(30.0, $this->pricing->tierPercentForDays(21)); // trên bậc cao nhất giữ %
    }

    /** @test */
    public function inactive_tier_is_ignored(): void
    {
        DurationDiscountTier::where('min_days', 10)->update(['is_active' => false]);
        $pricing = new RentalPricingService;
        $this->assertSame(20.0, $pricing->tierPercentForDays(15)); // bậc 30% tắt → rơi về 20%
    }

    /** @test */
    public function no_tiers_means_zero_percent(): void
    {
        DurationDiscountTier::query()->delete();
        $pricing = new RentalPricingService;
        $this->assertSame(0.0, $pricing->tierPercentForDays(30));
        $this->assertSame(600000, $pricing->priceLine(120000, 1, 5)['net']); // gross == net
    }

    /** @test */
    public function price_line_applies_tier_and_quantity(): void
    {
        // 120k/ngày × 1 × 3 ngày = 360k, chưa đạt bậc → net = gross
        $this->assertSame(['gross' => 360000, 'percent' => 0.0, 'net' => 360000], $this->pricing->priceLine(120000, 1, 3));

        // 120k × 1 × 7 ngày = 840k, bậc −20% → 672k
        $this->assertSame(['gross' => 840000, 'percent' => 20.0, 'net' => 672000], $this->pricing->priceLine(120000, 1, 7));

        // 120k × 2 × 10 ngày = 2.4tr, bậc −30% → 1.68tr
        $this->assertSame(['gross' => 2400000, 'percent' => 30.0, 'net' => 1680000], $this->pricing->priceLine(120000, 2, 10));
    }

    /**
     * Ưu đãi trả sớm trong ngày (adr_pricing_models) — CHỈ áp đơn cùng ngày (days=1),
     * thay bậc dài ngày; đơn nhiều ngày bỏ qua.
     *
     * @test
     */
    public function early_return_discount_applies_only_to_same_day(): void
    {
        // 1 ngày, ưu đãi 10% → 100k × 0.9 = 90k
        $this->assertSame(['gross' => 100000, 'percent' => 10.0, 'net' => 90000], $this->pricing->priceLine(100000, 1, 1, 10));

        // 1 ngày, ưu đãi 0 → giữ nguyên (tương thích ngược)
        $this->assertSame(['gross' => 100000, 'percent' => 0.0, 'net' => 100000], $this->pricing->priceLine(100000, 1, 1, 0));

        // 3 ngày (nhiều ngày) — ưu đãi trả sớm BỎ QUA; chưa đạt bậc → net = gross
        $this->assertSame(['gross' => 300000, 'percent' => 0.0, 'net' => 300000], $this->pricing->priceLine(100000, 1, 3, 10));

        // 5 ngày — ưu đãi trả sớm BỎ QUA, dùng bậc dài ngày −20% → 400k
        $this->assertSame(['gross' => 500000, 'percent' => 20.0, 'net' => 400000], $this->pricing->priceLine(100000, 1, 5, 10));
    }

    /** @test */
    public function net_is_rounded_to_integer(): void
    {
        // gross = 100003 × 1 × 7 = 700021 (bậc −20%) → 560016.8 → round 560017
        $line = $this->pricing->priceLine(100003, 1, 7);
        $this->assertSame(700021, $line['gross']);
        $this->assertSame(560017, $line['net']);
    }
}
