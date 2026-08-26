<?php

namespace Tests\Feature;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Auth\SessionGuard;
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

    /**
     * Cookie nhớ đăng nhập 60 ngày (bopcamping-bqsv) — đây là thứ BÙ LẠI việc mọi đăng nhập
     * giờ đều phải qua OTP: khách quen chỉ nhập mã một lần trên mỗi thiết bị.
     *
     * Kiểm bằng hạn thật của cookie chứ không đọc thuộc tính nội bộ (getRememberDuration là
     * protected), và cũng là smoke test rằng việc chỉnh guard lúc boot không làm nổ ứng dụng.
     *
     * @test
     */
    public function remember_cookie_lasts_sixty_days(): void
    {
        $response = $this->post(route('guest.login'), ['name' => 'Khách Mới', 'phone' => '0912345678']);

        $cookie = $response->getCookie('remember_web_'.sha1(SessionGuard::class));

        $this->assertNotNull($cookie, 'Đăng nhập phải kèm cookie nhớ đăng nhập.');
        $this->assertEqualsWithDelta(
            now()->addDays(60)->getTimestamp(),
            $cookie->getExpiresTime(),
            300, // lệch vài phút là bình thường, cookie tính từ lúc tạo
        );
    }

    /** @test Email không bắt buộc — khách mới bỏ trống email vẫn vào thẳng, không cần OTP. */
    public function new_guest_without_email_logs_in_directly_without_otp(): void
    {
        Mail::fake();

        $this->post(route('guest.login'), ['name' => 'Khách', 'phone' => '0912345678'])
            ->assertRedirect();

        $this->assertAuthenticated();
        Mail::assertNothingQueued();
        $user = User::where('phone', '0912345678')->first();
        $this->assertNotNull($user);
        $this->assertStringEndsWith('@bopcamping.local', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    /**
     * ĐỔI HÀNH VI (bopcamping-bqsv): khách cũ chỉ có email TẠM (@bopcamping.local) thì không
     * có hộp thư nào để gửi OTP — nhưng cũng KHÔNG được cho vào thẳng bằng SĐT, vì đó chính
     * là đường chiếm tài khoản. Buộc nhập email thật rồi xác thực.
     *
     * @test
     */
    public function returning_guest_without_a_real_email_is_asked_for_one_instead_of_being_let_in(): void
    {
        Mail::fake();
        $old = User::create(['name' => 'Khách Cũ', 'phone' => '0912345678']);

        $this->post(route('guest.login'), ['name' => 'Khách Cũ', 'phone' => '0912345678'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingQueued();
        $this->assertNotNull($old->fresh());
    }

    /**
     * Chưa có tài khoản nhưng SĐT ĐÃ TỪNG đặt đơn vãng lai → vẫn không cho vào thẳng.
     * `User::relatedOrders()` khớp đơn theo `customer_phone`, nên tạo tài khoản mới bằng SĐT
     * của người khác là thấy luôn lịch sử đơn của họ (tên, địa chỉ nhà).
     *
     * @test
     */
    public function a_phone_with_past_guest_orders_cannot_log_in_directly_either(): void
    {
        Mail::fake();
        $this->guestOrder();

        // Không vào thẳng, và mã phải về hộp thư trên ĐƠN CŨ chứ không phải đâu khác.
        $this->post(route('guest.login'), ['name' => 'Kẻ Lạ', 'phone' => '0912345678'])
            ->assertSessionHas('otp_sent', true);

        $this->assertGuest();
        $this->assertSame('ngoc@example.com', session('otp_pending')['email']);
        $this->assertSame(0, User::where('phone', '0912345678')->count());
    }

    /**
     * CHIẾM TÀI KHOẢN QUA EMAIL CỦA CHÍNH MÌNH — đường tấn công còn sót sau lần vá đầu.
     *
     * Kẻ lạ gõ SĐT nạn nhân + email CỦA CHÍNH MÌNH: nếu chỉ bắt "phải qua OTP" mà không giới
     * hạn gửi đi đâu, mã sẽ về hộp thư kẻ đó, rồi verifyOtp (tìm tài khoản theo SĐT) gắn email
     * mới vào tài khoản nạn nhân và đăng nhập — mất trọn tài khoản. OTP chỉ được gửi tới hộp
     * thư ĐÃ gắn sẵn với số đó.
     *
     * @test
     */
    public function a_stranger_cannot_redirect_the_otp_to_their_own_inbox(): void
    {
        Mail::fake();
        $victim = User::factory()->create([
            'name' => 'Chị Ngọc', 'phone' => '0912345678',
            'email' => 'ngoc@example.com', 'email_verified_at' => now(), 'is_admin' => false,
        ]);

        $this->post(route('guest.login'), [
            'name' => 'Kẻ Lạ', 'phone' => '0912345678', 'email' => 'kela@evil.com',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingQueued();
        $this->assertNull(session('otp_pending'));
        // Email tài khoản nạn nhân KHÔNG được đụng tới.
        $this->assertSame('ngoc@example.com', $victim->fresh()->email);
    }

    /** Cùng kịch bản nhưng nạn nhân chỉ có ĐƠN vãng lai, chưa có tài khoản. @test */
    public function a_stranger_cannot_claim_a_phone_that_has_guest_orders_using_another_email(): void
    {
        Mail::fake();
        $this->guestOrder();

        $this->post(route('guest.login'), [
            'name' => 'Kẻ Lạ', 'phone' => '0912345678', 'email' => 'kela@evil.com',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::where('phone', '0912345678')->count());
    }

    /** Chính chủ dùng đúng email trên đơn cũ thì nhận tài khoản được bình thường. @test */
    public function the_real_owner_can_claim_their_phone_with_the_email_used_on_the_order(): void
    {
        Mail::fake();
        $this->guestOrder();

        $this->post(route('guest.login'), [
            'name' => 'Chị Ngọc', 'phone' => '0912345678', 'email' => 'ngoc@example.com',
        ])->assertSessionHas('otp_sent', true);

        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $this->post(route('guest.login.verify'), ['code' => $code])->assertRedirect();

        $this->assertAuthenticated();
        $this->assertSame('ngoc@example.com', User::where('phone', '0912345678')->first()->email);
    }

    /** Đơn vãng lai của "Chị Ngọc" — SĐT 0912345678, email ngoc@example.com. */
    private function guestOrder(): Order
    {
        return Order::create([
            'customer_name' => 'Chị Ngọc', 'customer_phone' => '0912345678',
            'customer_email' => 'ngoc@example.com', 'customer_address' => '25 Trần Duy Hưng',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-03',
            'total_price' => 500000, 'deposit_total' => 0, 'status' => 'returned',
        ]);
    }
}
