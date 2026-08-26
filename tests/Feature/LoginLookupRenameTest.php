<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-8pe — đăng nhập: tra SĐT tự điền email + bỏ ràng buộc tên (đổi tên tuỳ ý).
 */
class LoginLookupRenameTest extends TestCase
{
    use RefreshDatabase;

    // ── Lookup tự điền email ──────────────────────────────────────────────────

    /** @test */
    public function lookup_returns_masked_email_not_the_real_one(): void
    {
        User::factory()->create([
            'name' => 'Khách Quen', 'phone' => '0912345678',
            'email' => 'quen@example.com', 'email_verified_at' => now(),
        ]);

        $res = $this->getJson(route('guest.lookup', ['phone' => '0912345678']))
            ->assertOk()
            ->assertJson(['exists' => true, 'name' => 'Khách Quen', 'email_mask' => 'qu**@example.com']);

        // KHÔNG được lộ email thật ra client.
        $this->assertStringNotContainsString('quen@example.com', $res->getContent());
    }

    /** @test */
    public function lookup_returns_not_found_for_unknown_or_invalid_phone(): void
    {
        $this->getJson(route('guest.lookup', ['phone' => '0900000000']))
            ->assertOk()->assertJson(['exists' => false]);

        $this->getJson(route('guest.lookup', ['phone' => 'abc']))
            ->assertOk()->assertJson(['exists' => false]);
    }

    /** @test */
    public function lookup_masks_nothing_for_placeholder_email_and_hides_admins(): void
    {
        // User tạo nhanh chỉ bằng SĐT → email tạm .local, không cho đăng nhập nhanh.
        User::create(['name' => 'Khách Cũ', 'phone' => '0911111111']);
        $this->getJson(route('guest.lookup', ['phone' => '0911111111']))
            ->assertOk()->assertJson(['exists' => true, 'email_mask' => null]);

        // Admin không lộ qua cổng khách.
        User::factory()->create(['phone' => '0922222222', 'email' => 'ad@x.com', 'is_admin' => true]);
        $this->getJson(route('guest.lookup', ['phone' => '0922222222']))
            ->assertOk()->assertJson(['exists' => false]);
    }

    /** @test */
    public function phone_only_never_logs_into_an_existing_account(): void
    {
        User::factory()->create([
            'name' => 'Khách Quen', 'phone' => '0912345678',
            'email' => 'quen@example.com', 'email_verified_at' => now(),
        ]);

        // Trước bopcamping-bqsv: bỏ trống email thì server TỰ ĐIỀN email đã lưu rồi cho vào
        // thẳng — nghĩa là ai biết SĐT là vào được tài khoản người ta. Nay chỉ gửi OTP tới hộp
        // thư đã lưu; người gõ SĐT phải mở được hộp thư đó mới vào được.
        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertSessionHas('otp_sent', true);

        $this->assertGuest();
        $this->assertSame('quen@example.com', session('otp_pending')['email']);
    }

    /**
     * SĐT của ADMIN không bao giờ phục vụ ở luồng khách (bopcamping-bqsv). Số này in công khai
     * ở footer dưới dạng hotline, nên thiếu chốt chặn là ai cũng vào được panel quản trị.
     *
     * @test
     */
    public function an_admin_phone_can_never_be_used_on_the_customer_login(): void
    {
        User::factory()->create([
            'name' => 'Chủ shop', 'phone' => '0976544370',
            'email' => 'chushop@example.com', 'email_verified_at' => now(), 'is_admin' => true,
        ]);

        $this->post(route('guest.login'), ['phone' => '0976544370', 'name' => 'Kẻ Lạ'])
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
        // Cũng không được đẻ ra tài khoản khách trùng SĐT với admin.
        $this->assertSame(1, User::where('phone', '0976544370')->count());
    }

    /** @test Email không bắt buộc (bopcamping-3xr): SĐT mới bỏ trống email → vào thẳng, không OTP. */
    public function new_phone_without_email_logs_in_directly(): void
    {
        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    // ── Đổi tên tuỳ ý (SĐT là khoá) ───────────────────────────────────────────

    /** @test Tên mới nằm chờ ở otp_pending, chỉ ghi vào DB sau khi xác thực OTP. */
    public function returning_user_can_change_name_through_the_otp_flow(): void
    {
        $user = User::factory()->create([
            'name' => 'Tên Cũ', 'phone' => '0912345678',
            'email' => 'quen@example.com', 'email_verified_at' => now(),
        ]);

        $this->post(route('guest.login'), [
            'name' => 'Tên Mới', 'phone' => '0912345678', 'email' => 'quen@example.com',
        ])->assertSessionHas('otp_sent', true);

        $this->assertSame('Tên Mới', session('otp_pending')['name']);
        // Chưa xác thực thì tên trong DB PHẢI giữ nguyên — nếu không, người lạ gõ SĐT là đổi
        // được tên hiển thị của khách mà không cần vào nổi tài khoản.
        $this->assertSame('Tên Cũ', $user->fresh()->name);
    }

    /** @test */
    public function name_is_optional_and_keeps_existing_when_blank(): void
    {
        User::factory()->create([
            'name' => 'Giữ Tên', 'phone' => '0912345678',
            'email' => 'quen@example.com', 'email_verified_at' => now(),
        ]);

        $this->post(route('guest.login'), [
            'phone' => '0912345678', 'email' => 'quen@example.com',
        ])->assertSessionHas('otp_sent', true);

        $this->assertSame('Giữ Tên', session('otp_pending')['name']); // bỏ trống → giữ tên cũ
    }

    /** @test */
    public function new_user_without_name_falls_back_to_phone(): void
    {
        Mail::fake();

        $this->post(route('guest.login'), [
            'phone' => '0912345678', 'email' => 'moi@example.com',
        ])->assertSessionHas('otp_sent', true);

        // Tên hiển thị tạm = SĐT cho tới khi khách đặt tên khác.
        $this->assertSame('0912345678', session('otp_pending')['name']);
    }
}
