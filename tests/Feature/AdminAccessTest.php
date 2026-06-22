<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-8kk — chặn truy cập /admin (guest + non-admin) + đăng nhập khách SĐT+tên.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    private array $adminRoutes = ['admin.orders', 'admin.products', 'admin.categories', 'admin.users'];

    /** @test */
    public function guest_cannot_access_admin_pages(): void
    {
        foreach ($this->adminRoutes as $name) {
            $this->get(route($name))->assertRedirect(route('admin.login'));
        }
    }

    /** @test */
    public function non_admin_customer_cannot_access_admin_pages(): void
    {
        $customer = User::factory()->create(['is_admin' => false, 'phone' => '0911111111']);

        foreach ($this->adminRoutes as $name) {
            $this->actingAs($customer)->get(route($name))->assertRedirect(route('admin.login'));
        }
    }

    /** @test */
    public function admin_can_access_admin_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'phone' => '0900000001']);

        foreach ($this->adminRoutes as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
    }

    /** @test */
    public function guest_login_creates_account_and_authenticates(): void
    {
        $this->post(route('guest.login'), ['name' => 'Khách Mới', 'phone' => '0912345678'])
            ->assertRedirect();

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'phone' => '0912345678',
            'name' => 'Khách Mới',
            'is_admin' => false,
        ]);
    }

    /** @test */
    public function guest_login_matches_existing_phone_with_same_name(): void
    {
        User::factory()->create(['name' => 'Khách Cũ', 'phone' => '0912345678', 'is_admin' => false]);

        $this->post(route('guest.login'), ['name' => 'Khách Cũ', 'phone' => '0912345678'])
            ->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame(1, User::where('phone', '0912345678')->count());
    }

    /** @test */
    public function guest_login_rejects_existing_phone_with_different_name(): void
    {
        User::factory()->create(['name' => 'Tên A', 'phone' => '0912345678', 'is_admin' => false]);

        $this->post(route('guest.login'), ['name' => 'Tên B', 'phone' => '0912345678'])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }
}
