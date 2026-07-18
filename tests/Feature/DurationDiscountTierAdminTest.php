<?php

namespace Tests\Feature;

use App\Models\DurationDiscountTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-e36e — admin CRUD bậc giảm giá thuê dài ngày (sync toàn bảng).
 */
class DurationDiscountTierAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function putTiers(User $user, array $tiers)
    {
        return $this->actingAs($user)->put(route('admin.promotion.duration-tiers'), ['tiers' => $tiers]);
    }

    /** @test */
    public function admin_can_replace_the_whole_tier_set(): void
    {
        DurationDiscountTier::create(['min_days' => 3, 'discount_percent' => 5, 'is_active' => true]); // sẽ bị thay

        $this->putTiers($this->admin(), [
            ['min_days' => 5, 'discount_percent' => 20, 'is_active' => true],
            ['min_days' => 10, 'discount_percent' => 30, 'is_active' => false],
        ])->assertSessionHas('success');

        $this->assertSame(2, DurationDiscountTier::count());
        $this->assertDatabaseHas('duration_discount_tiers', ['min_days' => 5, 'discount_percent' => 20.00, 'is_active' => true]);
        $this->assertDatabaseHas('duration_discount_tiers', ['min_days' => 10, 'is_active' => false]);
        $this->assertDatabaseMissing('duration_discount_tiers', ['min_days' => 3]);
    }

    /** @test */
    public function duplicate_min_days_is_rejected(): void
    {
        $this->putTiers($this->admin(), [
            ['min_days' => 5, 'discount_percent' => 20, 'is_active' => true],
            ['min_days' => 5, 'discount_percent' => 30, 'is_active' => true],
        ])->assertSessionHasErrors('tiers.0.min_days');
    }

    /** @test */
    public function percent_over_100_is_rejected(): void
    {
        $this->putTiers($this->admin(), [['min_days' => 5, 'discount_percent' => 150, 'is_active' => true]])
            ->assertSessionHasErrors('tiers.0.discount_percent');
    }

    /** @test */
    public function empty_payload_clears_all_tiers(): void
    {
        DurationDiscountTier::create(['min_days' => 5, 'discount_percent' => 20, 'is_active' => true]);
        $this->putTiers($this->admin(), [])->assertSessionHas('success');
        $this->assertSame(0, DurationDiscountTier::count());
    }

    /** @test */
    public function non_admin_cannot_update_tiers(): void
    {
        $this->putTiers(User::factory()->create(['is_admin' => false]), [])->assertRedirect(route('admin.login'));
    }
}
