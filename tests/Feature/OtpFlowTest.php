<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * bopcamping-s2q / tb3 — luồng đăng nhập OTP qua email (KE_HOACH 8.1).
 */
class OtpFlowTest extends TestCase
{
    use RefreshDatabase;

    // ── Service ──────────────────────────────────────────────────────────────

    /** @test */
    public function service_send_stores_hashed_otp(): void
    {
        // GỬI mail (afterResponse) được phủ ở full_flow qua HTTP; ở đây chỉ kiểm tra lưu trữ.
        $code = app(OtpService::class)->send('a@x.com');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $otp = EmailOtp::where('email', 'a@x.com')->first();
        $this->assertNotNull($otp);
        $this->assertNotSame($code, $otp->code); // đã hash, không lưu thô
    }

    /** @test */
    public function service_verify_accepts_correct_code_once_then_marks_consumed(): void
    {
        Mail::fake();
        $svc = app(OtpService::class);
        $code = $svc->send('a@x.com');

        $this->assertTrue($svc->verify('a@x.com', $code));
        $this->assertNotNull(EmailOtp::where('email', 'a@x.com')->first()->consumed_at);
        $this->assertFalse($svc->verify('a@x.com', $code)); // dùng lại → fail
    }

    /** @test */
    public function service_verify_rejects_wrong_and_expired_code(): void
    {
        Mail::fake();
        $svc = app(OtpService::class);
        $svc->send('a@x.com');

        $this->assertFalse($svc->verify('a@x.com', '000000')); // sai

        EmailOtp::where('email', 'a@x.com')->update(['expires_at' => Carbon::now()->subMinute()]);
        $real = $svc->send('b@x.com'); // mã khác email
        $this->assertFalse($svc->verify('a@x.com', $real)); // không khớp email
    }

    /** @test */
    public function service_resend_invalidates_previous_unused_otp(): void
    {
        Mail::fake();
        $svc = app(OtpService::class);
        $first = $svc->send('a@x.com');
        $second = $svc->send('a@x.com');

        $this->assertFalse($svc->verify('a@x.com', $first));  // mã cũ bị vô hiệu
        $this->assertTrue($svc->verify('a@x.com', $second));  // mã mới hợp lệ
    }

    // ── HTTP 2 bước ────────────────────────────────────────────────────────────

    /** @test */
    public function full_flow_first_login_creates_verified_user_and_authenticates(): void
    {
        Mail::fake();

        $this->post(route('guest.login'), [
            'name' => 'Khách Mới', 'phone' => '0912345678', 'email' => 'moi@example.com',
        ])->assertSessionHas('otp_sent', true);
        $this->assertGuest();

        // Lấy mã thật từ mail đã gửi.
        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        $this->post(route('guest.login.verify'), ['code' => $code])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'phone' => '0912345678', 'name' => 'Khách Mới', 'email' => 'moi@example.com', 'is_admin' => false,
        ]);
        $this->assertNotNull(User::where('phone', '0912345678')->first()->email_verified_at);
    }

    /** @test */
    public function verify_rejects_wrong_code(): void
    {
        Mail::fake();
        $this->post(route('guest.login'), [
            'name' => 'Khách Mới', 'phone' => '0912345678', 'email' => 'moi@example.com',
        ]);

        $this->post(route('guest.login.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    /** @test */
    public function old_phone_account_can_add_email_via_otp(): void
    {
        Mail::fake();
        // User cũ: tạo bằng SĐT, email tạm .local, chưa verify.
        $old = User::create(['name' => 'Khách Cũ', 'phone' => '0912345678']);
        $this->assertStringEndsWith('@bopcamping.local', $old->email);

        $this->post(route('guest.login'), [
            'name' => 'Khách Cũ', 'phone' => '0912345678', 'email' => 'that@example.com',
        ])->assertSessionHas('otp_sent', true);

        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $this->post(route('guest.login.verify'), ['code' => $code])->assertRedirect();

        $this->assertAuthenticatedAs($old->fresh());
        $this->assertSame('that@example.com', $old->fresh()->email);
        $this->assertNotNull($old->fresh()->email_verified_at);
    }

    /** @test */
    public function email_used_by_another_account_is_rejected(): void
    {
        User::factory()->create([
            'phone' => '0900000001', 'email' => 'taken@example.com', 'email_verified_at' => now(),
        ]);

        $this->post(route('guest.login'), [
            'name' => 'Người khác', 'phone' => '0912345678', 'email' => 'taken@example.com',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** @test */
    public function email_is_required_at_login(): void
    {
        $this->post(route('guest.login'), ['name' => 'Khách', 'phone' => '0912345678'])
            ->assertSessionHasErrors('email');
    }
}
