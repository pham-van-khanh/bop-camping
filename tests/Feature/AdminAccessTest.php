<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
    public function guest_login_new_account_sends_otp_and_does_not_authenticate_yet(): void
    {
        Mail::fake();

        $this->post(route('guest.login'), [
            'name' => 'Khách Mới', 'phone' => '0912345678', 'email' => 'moi@example.com',
        ])->assertRedirect()->assertSessionHas('otp_sent', true);

        $this->assertGuest(); // chưa đăng nhập — phải xác thực OTP trước
        Mail::assertSent(OtpMail::class);
    }

    /** @test */
    public function verified_user_logs_in_without_otp(): void
    {
        $user = User::factory()->create([
            'name' => 'Khách Cũ', 'phone' => '0912345678',
            'email' => 'cu@example.com', 'email_verified_at' => now(), 'is_admin' => false,
        ]);

        $this->post(route('guest.login'), [
            'name' => 'Khách Cũ', 'phone' => '0912345678', 'email' => 'cu@example.com',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::where('phone', '0912345678')->count());
    }

    /** @test */
    public function guest_login_rejects_existing_phone_with_different_name(): void
    {
        User::factory()->create(['name' => 'Tên A', 'phone' => '0912345678', 'is_admin' => false]);

        $this->post(route('guest.login'), ['name' => 'Tên B', 'phone' => '0912345678', 'email' => 'b@example.com'])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
    }
}
