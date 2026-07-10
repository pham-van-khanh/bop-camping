<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-8pe — đăng nhập: SĐT là khoá định danh + bỏ ràng buộc tên (đổi tên tuỳ ý).
 * (Endpoint tra SĐT→tên đã bỏ — bopcamping-4bi chống dò danh bạ; server tự dùng email đã lưu.)
 */
class LoginLookupRenameTest extends TestCase
{
    use RefreshDatabase;

    // ── Đăng nhập nhanh bằng SĐT (email đã lưu dùng ở server) ─────────────────

    /** @test */
    public function returning_user_logs_in_with_phone_only_using_stored_email(): void
    {
        $user = User::factory()->create([
            'name' => 'Khách Quen', 'phone' => '0912345678',
            'email' => 'quen@example.com', 'email_verified_at' => now(),
        ]);

        // Email để trống → server tự dùng email đã xác thực đã lưu.
        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user->fresh());
    }

    /** @test Email không bắt buộc (bopcamping-3xr): SĐT mới bỏ trống email → vào thẳng, không OTP. */
    public function new_phone_without_email_logs_in_directly(): void
    {
        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    // ── Đổi tên tuỳ ý (SĐT là khoá) ───────────────────────────────────────────

    /** @test */
    public function returning_user_can_change_name_on_direct_login(): void
    {
        $user = User::factory()->create([
            'name' => 'Tên Cũ', 'phone' => '0912345678',
            'email' => 'quen@example.com', 'email_verified_at' => now(),
        ]);

        // Email đã verify → vào thẳng, đồng thời đổi tên hiển thị.
        $this->post(route('guest.login'), [
            'name' => 'Tên Mới', 'phone' => '0912345678', 'email' => 'quen@example.com',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('Tên Mới', $user->fresh()->name);
    }

    /** @test */
    public function name_is_optional_and_keeps_existing_when_blank(): void
    {
        $user = User::factory()->create([
            'name' => 'Giữ Tên', 'phone' => '0912345678',
            'email' => 'quen@example.com', 'email_verified_at' => now(),
        ]);

        $this->post(route('guest.login'), [
            'phone' => '0912345678', 'email' => 'quen@example.com',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('Giữ Tên', $user->fresh()->name); // bỏ trống → giữ tên cũ
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
