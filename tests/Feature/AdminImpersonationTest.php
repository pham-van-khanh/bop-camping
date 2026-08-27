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

    /**
     * Thoát-khi-mất-quyền KHÔNG được đụng tới credential của khách.
     *
     * Nhánh đó gọi Auth::logout(), mà lúc ấy người đang đăng nhập là KHÁCH — nên
     * SessionGuard cycle `remember_token` của khách, đá một người vô can ra khỏi mọi thiết bị
     * của họ. Với tài khoản chỉ-có-SĐT thì cookie 400 ngày đó là credential DUY NHẤT: mất luôn
     * tài khoản. Chính docblock của GuestAuthController::destroy() nói admin không có quyền đá
     * khách ra khỏi phiên của họ — nhánh này từng để hở đúng điều đó.
     *
     * @test
     */
    public function stopping_after_the_admin_role_was_revoked_leaves_the_customer_credential_alone(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();
        $customer->forceFill(['remember_token' => 'token-cua-khach'])->save();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer->id));
        $admin->forceFill(['is_admin' => false])->save();

        $this->post(route('impersonate.stop'))->assertRedirect(route('admin.login'));

        $this->assertGuest();
        // Cookie nhớ trên máy của khách vẫn phải dùng được.
        $this->assertSame('token-cua-khach', $customer->fresh()->remember_token);
    }

    /**
     * Đăng nhập admin lại giữa chừng phải dọn cờ mạo danh.
     *
     * session()->regenerate() chỉ đổi id phiên chứ GIỮ NGUYÊN dữ liệu, nên `impersonator_id`
     * sống sót: phiên vừa là admin vừa còn cờ. Hậu quả là nút "Đăng xuất" phía khách rơi vào
     * nhánh thoát-mạo-danh và ĐĂNG NHẬP LẠI admin thay vì đăng xuất — bấm xong tưởng đã ra,
     * thực tế vẫn vào đầy đủ. Máy dùng chung là người sau thừa hưởng phiên admin.
     *
     * @test
     */
    public function logging_in_again_clears_a_stale_impersonation_flag(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer->id));
        $this->assertNotNull(session('impersonator_id'));

        // Admin bị đẩy về trang đăng nhập admin rồi đăng nhập lại giữa lúc đang xem hộ.
        $this->post(route('admin.login.store'), [
            'phone' => $admin->phone,
            'password' => 'password', // mặc định của UserFactory
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertNull(session('impersonator_id'));
        $this->assertAuthenticatedAs($admin);

        // Và "Đăng xuất" giờ đăng xuất thật, không quay lại thành admin.
        $this->post(route('guest.logout'));
        $this->assertGuest();
    }

    /** Shipper cũng không được mạo danh: giao diện ẩn nút, nhưng route vẫn bind theo id. @test */
    public function an_admin_cannot_impersonate_a_shipper(): void
    {
        $admin = $this->admin();
        $shipper = User::factory()->create([
            'phone' => '0988000111', 'is_admin' => false, 'is_shipper' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.impersonate', $shipper->id))
            ->assertForbidden();

        $this->assertAuthenticatedAs($admin);
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

    /**
     * Đang xem hộ mà bấm "Đăng xuất" ở header KHÁCH → phải quay về admin, KHÔNG đăng xuất sạch.
     *
     * Thiếu chốt này thì `session()->invalidate()` trong destroy() xoá luôn `impersonator_id`:
     * admin mất cả phiên quản trị chỉ vì bấm nhầm nút quen tay, phải đăng nhập lại từ đầu.
     * Header khách không phân biệt được hai nút đó nên phải xử ở server.
     *
     * @test
     */
    public function logging_out_while_impersonating_returns_to_the_admin(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin)->post(route('admin.users.impersonate', $customer->id));
        $this->assertAuthenticatedAs($customer);

        $this->post(route('guest.logout'))->assertRedirect(route('admin.users'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session('impersonator_id'));
    }

    /** Khách bình thường bấm đăng xuất thì vẫn đăng xuất thật. @test */
    public function a_normal_customer_logging_out_is_still_logged_out(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('guest.logout'))->assertRedirect('/');

        $this->assertGuest();
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
