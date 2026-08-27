<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * bopcamping-bqsv — admin đăng nhập THAY khách để hỗ trợ (nút "Đăng nhập" ở /admin/users).
 *
 * Đây là một đường đi TẮT có chủ ý qua hàng rào đăng nhập, nên phần đáng kiểm nhất không phải
 * "nó chạy được", mà là những chỗ nó PHẢI từ chối: mạo danh admin khác, người không phải admin
 * gọi vào, và thoát ra khi quyền admin đã bị gỡ giữa chừng.
 */
class AdminImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $phone = '0900000001'): User
    {
        return User::factory()->create(['phone' => $phone, 'is_admin' => true]);
    }

    private function customer(string $phone = '0912345678'): User
    {
        return User::factory()->create([
            'name' => 'Chị Ngọc', 'phone' => $phone, 'is_admin' => false,
        ]);
    }

    /** @test */
    public function admin_can_log_in_as_a_customer_without_otp(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $customer->id))
            ->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($customer);
        $this->assertSame($admin->id, session('impersonator_id'));
    }

    /**
     * Phiên mạo danh KHÔNG được để lại cookie nhớ đăng nhập 60 ngày của tài khoản khách trên
     * máy admin — nếu không, đóng trình duyệt xong mở lại vẫn là khách, rất dễ thao tác nhầm.
     *
     * @test
     */
    public function impersonation_does_not_leave_a_remember_cookie(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $tokenTruoc = $customer->remember_token;

        $response = $this->actingAs($admin)->post(route('admin.users.impersonate', $customer->id));

        // `Auth::login(..., remember: true)` sẽ ĐỔI remember_token; giữ nguyên = không remember.
        // (Không assertNull được: UserFactory vốn đã điền sẵn một token ngẫu nhiên.)
        $this->assertSame($tokenTruoc, $customer->fresh()->remember_token);

        $this->assertNull($response->getCookie('remember_web_'.sha1(SessionGuard::class)));
    }

    /** @test */
    public function an_admin_cannot_impersonate_another_admin(): void
    {
        $admin = $this->admin();
        $other = $this->admin('0900000002');

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $other->id))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
    }

    /** @test */
    public function a_normal_customer_cannot_reach_the_impersonate_route(): void
    {
        $customer = $this->customer();
        $victim = $this->customer('0933333333');

        $this->actingAs($customer)
            ->post(route('admin.users.impersonate', $victim->id))
            ->assertRedirect(route('admin.login'));

        $this->assertAuthenticatedAs($customer);
    }

    /** @test */
    public function a_guest_cannot_reach_the_impersonate_route(): void
    {
        $victim = $this->customer();

        $this->post(route('admin.users.impersonate', $victim->id))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    /** @test */
    public function stopping_returns_to_the_original_admin(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer->id));
        $this->assertAuthenticatedAs($customer);

        $this->post(route('impersonate.stop'))->assertRedirect(route('admin.users'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    /**
     * Quyền admin bị gỡ TRONG LÚC đang mạo danh → thoát ra phải là đăng xuất hẳn, không được
     * tin `impersonator_id` trong session mà cho quay lại panel.
     *
     * @test
     */
    public function stopping_logs_out_entirely_if_the_admin_role_was_revoked_meanwhile(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer->id));

        $admin->forceFill(['is_admin' => false])->save();

        $this->post(route('impersonate.stop'))->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    /** Khách gọi nhầm route thoát thì KHÔNG được đăng xuất họ — chỉ đưa về trang chủ. @test */
    public function stopping_without_an_active_impersonation_does_not_log_the_customer_out(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->post(route('impersonate.stop'))
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($customer);
    }

    /** Thanh nhắc "đang xem với tư cách…" phải hiện ở trang khách. @test */
    public function the_customer_pages_expose_the_impersonation_banner_prop(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer->id));

        $this->get(route('account'))
            ->assertInertia(fn ($page) => $page->where('impersonating.name', 'Chị Ngọc'));
    }

    /** Khách tự đăng nhập bình thường thì KHÔNG có thanh nhắc. @test */
    public function a_normal_session_has_no_impersonation_banner(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)
            ->get(route('account'))
            ->assertInertia(fn ($page) => $page->where('impersonating', null));
    }
}
