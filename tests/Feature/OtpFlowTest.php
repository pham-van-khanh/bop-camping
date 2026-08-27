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
use Illuminate\Testing\TestResponse;
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

    /**
     * Tài khoản chỉ có SĐT tự gắn email ở màn đăng nhập — ĐÁNH ĐỔI chủ shop chốt 26/08/2026.
     *
     * Bản trước chặn hẳn nhánh này (bắt nhắn Zalo) vì mã gửi tới hộp thư khách VỪA GÕ chỉ
     * chứng minh họ mở được hộp thư đó, không chứng minh họ là chủ số. Chủ shop thấy quá nặng
     * cho khách thật nên đổi: cho gắn email mới, Zalo hạ xuống thành lối phụ.
     *
     * Test này khoá hành vi ĐÃ CHỌN. Rủi ro còn lại ghi ở design_spec §5 — nếu sau này siết
     * lại thì đây là test phải sửa đầu tiên.
     *
     * @test
     */
    public function a_phone_only_account_can_attach_an_email_at_login(): void
    {
        Mail::fake();
        $old = User::create(['name' => 'Khách Cũ', 'phone' => '0912345678']);

        $this->post(route('guest.login'), [
            'name' => 'Khách Cũ', 'phone' => '0912345678', 'email' => 'that@example.com',
        ])->assertSessionHas('otp_sent', true)->assertSessionHasNoErrors();

        // Chưa xác thực thì CHƯA vào được và tài khoản chưa bị đổi email.
        $this->assertGuest();
        $this->assertTrue($old->fresh()->hasPlaceholderEmail());

        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });
        $this->post(route('guest.login.verify'), ['code' => $code])->assertRedirect();

        $this->assertAuthenticatedAs($old->fresh());
        $this->assertSame('that@example.com', $old->fresh()->email);
    }

    /**
     * Bỏ trống email cho số chưa gắn hộp thư → BẮT nhập email, kèm cờ để LoginModal hiện thêm
     * dòng liên hệ Zalo bên dưới ô email. Không cho vào thẳng bằng SĐT (đó là bopcamping-bqsv).
     *
     * @test
     */
    public function a_phone_only_account_must_type_an_email_and_is_offered_zalo(): void
    {
        Mail::fake();
        User::create(['name' => 'Khách Cũ', 'phone' => '0912345678']);

        $this->post(route('guest.login'), ['name' => 'Khách Cũ', 'phone' => '0912345678'])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('login_needs_support', true);

        $this->assertGuest();
        Mail::assertNothingQueued();
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
    public function remember_cookie_lasts_sixty_days_for_an_account_with_a_real_email(): void
    {
        Mail::fake();
        $response = $this->loginViaOtp('0912345678', 'ngoc@example.com');

        // decrypt: false — chỉ cần HẠN của cookie, không cần nội dung. Giải mã thêm là rước
        // một đường hỏng không liên quan (EncryptCookies + CookieValuePrefix) vào bài test hạn.
        $cookie = $response->getCookie('remember_web_'.sha1(SessionGuard::class), false);

        $this->assertNotNull($cookie, 'Đăng nhập phải kèm cookie nhớ đăng nhập.');
        $this->assertEqualsWithDelta(
            now()->addDays(60)->getTimestamp(),
            $cookie->getExpiresTime(),
            300, // lệch vài phút là bình thường, cookie tính từ lúc tạo
        );
    }

    /**
     * Tài khoản CHỈ CÓ SĐT được cookie 400 ngày thay vì 60 (bopcamping-kuhg).
     *
     * Không phải ưu ái mà là bắt buộc: loại tài khoản này không có hộp thư nào nhận mã, nên
     * hết cookie là mất hẳn quyền vào, chỉ còn đường nhắn Zalo. 400 ngày là trần cứng của
     * Chrome cho tuổi cookie — đặt cao hơn cũng bị trình duyệt cắt về đây.
     *
     * @test
     */
    public function remember_cookie_lasts_four_hundred_days_for_a_phone_only_account(): void
    {
        $response = $this->post(route('guest.login'), ['name' => 'Khách Mới', 'phone' => '0912345678']);

        $cookie = $response->getCookie('remember_web_'.sha1(SessionGuard::class), false);

        $this->assertNotNull($cookie, 'Đăng nhập phải kèm cookie nhớ đăng nhập.');
        $this->assertEqualsWithDelta(
            now()->addDays(400)->getTimestamp(),
            $cookie->getExpiresTime(),
            300,
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
     * Khách VÃNG LAI có đơn cũ nhưng đơn bỏ trống email (email checkout là TUỲ CHỌN).
     *
     * Bỏ trống email → vẫn KHÔNG cho vào thẳng bằng SĐT (chốt bopcamping-bqsv, không đụng tới),
     * mà bắt nhập email; gõ email vào thì gắn được như tài khoản email-tạm. Điều này CỐ Ý mở
     * theo quyết định 26/08/2026 — xem design_spec §5.
     *
     * @test
     */
    public function a_phone_with_guest_orders_that_carry_no_email_must_type_an_email(): void
    {
        Mail::fake();
        $order = $this->guestOrder();
        $order->forceFill(['customer_email' => null])->save();

        // Bỏ trống → không tạo tài khoản, không cho vào, đòi email.
        $this->post(route('guest.login'), ['name' => 'Khách', 'phone' => '0912345678'])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('login_needs_support', true);

        $this->assertGuest();
        Mail::assertNothingQueued();
        $this->assertSame(0, User::where('phone', '0912345678')->count());

        // Gõ email → gửi mã tới chính hộp thư đó, nhưng vẫn chưa được vào khi chưa xác thực.
        $this->post(route('guest.login'), [
            'name' => 'Khách', 'phone' => '0912345678', 'email' => 'khach@example.com',
        ])->assertSessionHas('otp_sent', true);

        $this->assertGuest();
        $this->assertSame(0, User::where('phone', '0912345678')->count());
    }

    /**
     * lookup() và store() phải đọc CÙNG một nguồn (allowedEmailsFor). Ca dễ lệch nhất: tài
     * khoản email tạm NHƯNG có đơn cũ kèm email thật — store() gửi mã được, nên lookup() không
     * được báo needs_support, nếu không khách bị đẩy sang Zalo trong khi hệ thống vẫn chạy tốt.
     *
     * @test
     */
    public function lookup_follows_the_same_rule_as_login_for_a_placeholder_email_account(): void
    {
        $user = User::create(['name' => 'Chị Ngọc', 'phone' => '0912345678']);
        $this->guestOrder(); // đơn cũ cùng SĐT, có email thật

        $this->getJson(route('guest.lookup', ['phone' => '0912345678']))
            ->assertJson([
                'exists' => true,
                'email_mask' => 'ng**@example.com',
                'needs_support' => false,
            ]);

        $this->assertTrue($user->fresh()->hasPlaceholderEmail());
    }

    /** @test Không hộp thư nào → needs_support để modal đổi ô email thành BẮT BUỘC + thêm dòng Zalo. */
    public function lookup_flags_an_account_with_no_reachable_mailbox(): void
    {
        User::create(['name' => 'Khách Cũ', 'phone' => '0912345678']);

        $this->getJson(route('guest.lookup', ['phone' => '0912345678']))
            ->assertJson([
                'exists' => true,
                'email_mask' => null,
                'needs_support' => true,
            ]);
    }

    /**
     * lookup() KHÔNG được lộ "số này từng đặt đơn" cho số chưa có tài khoản — đó là thông tin
     * của khách vãng lai, và lộ ra thì dò số là biết ai từng thuê đồ ở shop.
     *
     * @test
     */
    public function lookup_does_not_reveal_guest_orders_for_a_phone_without_an_account(): void
    {
        $this->guestOrder();

        $this->getJson(route('guest.lookup', ['phone' => '0912345678']))
            ->assertExactJson(['exists' => false]);
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

    /**
     * Cùng kịch bản nhưng nạn nhân chỉ có ĐƠN vãng lai, chưa có tài khoản.
     *
     * Đây là chốt chính còn lại sau quyết định 26/08/2026. Điều kiện trong code là
     * `$allowedEmails->isNotEmpty()`, KHÔNG phải `$phoneIsClaimed` — hai thứ đó nay khác nhau:
     * đổi nhầm sang `$phoneIsClaimed` là chặn luôn ca số chưa gắn hộp thư (trái ý chủ shop),
     * còn bỏ hẳn điều kiện là mở lại chiếm tài khoản của khách ĐÃ có email.
     *
     * @test
     */
    public function a_stranger_cannot_claim_a_phone_that_has_guest_orders_using_another_email(): void
    {
        Mail::fake();
        $this->guestOrder(); // đơn cũ CÓ email thật ngoc@example.com

        $this->post(route('guest.login'), [
            'name' => 'Kẻ Lạ', 'phone' => '0912345678', 'email' => 'kela@evil.com',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingQueued();
        $this->assertSame(0, User::where('phone', '0912345678')->count());
    }

    /**
     * KẺ LẠ TỰ ĐẶT ĐƠN ĐỂ TỰ CẤP CHO MÌNH MỘT EMAIL "ĐƯỢC PHÉP".
     *
     * Lỗ hổng đo được 27/08/2026, sót lại sau cả hai lần vá trước. Chốt "email phải khớp"
     * chỉ hỏi *email có nằm trong allowedEmailsFor() không* — mà danh sách đó gộp email trên
     * MỌI đơn mang số ấy. POST /dat-hang lại không cần đăng nhập và nhận `phone`+`email` tuỳ ý
     * (OrderController lưu thẳng vào customer_phone/customer_email). Nên kẻ lạ chỉ việc tự đặt
     * một đơn mang SĐT nạn nhân kèm email của mình là chốt tự mở, mã bay về hộp thư hắn, rồi
     * verifyOtp ghi đè email nạn nhân và đăng nhập. Chiếm được cả tài khoản ĐÃ có email —
     * tức phá đúng thứ bopcamping-bqsv sinh ra để chặn.
     *
     * Chặn bằng con người: đơn chỉ rời 'pending' sau khi shop GỌI vào chính số đó xác nhận.
     * Vì vậy điều kiện phải là OWNERSHIP_PROOF_STATUSES, KHÔNG phải "mọi đơn".
     *
     * @test
     */
    public function a_stranger_cannot_mint_an_allowed_email_by_placing_an_order(): void
    {
        Mail::fake();
        $victim = User::factory()->create([
            'name' => 'Chị Ngọc', 'phone' => '0912345678',
            'email' => 'ngoc@example.com', 'email_verified_at' => now(), 'is_admin' => false,
        ]);

        // Đơn do kẻ lạ tự đặt: mang SĐT nạn nhân, email của hắn, và CHƯA được shop xác nhận.
        Order::create([
            'customer_name' => 'Kẻ Lạ', 'customer_phone' => '0912345678',
            'customer_email' => 'kela@evil.com', 'customer_address' => 'x',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02',
            'total_price' => 100000, 'deposit_total' => 0, 'status' => 'pending',
        ]);

        $this->post(route('guest.login'), [
            'name' => 'Kẻ Lạ', 'phone' => '0912345678', 'email' => 'kela@evil.com',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingQueued();
        $this->assertSame('ngoc@example.com', $victim->fresh()->email);
    }

    /**
     * ĐẶT HỘ NGƯỜI THÂN KHÔNG ĐƯỢC CƯỚP MẤT TÀI KHOẢN CỦA MÌNH.
     *
     * Đo trên local 27/08 sau khi đã gỡ chức năng gắn email ở checkout — hoá ra gỡ vẫn CHƯA đủ,
     * nó chỉ dời vấn đề tới lúc đơn được xác nhận:
     *
     *   Bác khách chỉ-có-SĐT đăng nhập rồi đặt đồ hộ đứa cháu, điền email của cháu để nó nhận
     *   mail. Tài khoản bác không đổi (đúng). Nhưng shop gọi xác nhận đơn xong thì email của
     *   cháu thành "hộp thư đã gắn" với SĐT của bác — bác đăng nhập là mã bay vào hộp thư cháu,
     *   không có cháu thì không vào được tài khoản của chính mình.
     *
     * Chốt: chỉ đơn đặt lúc CHƯA đăng nhập (user_id NULL) mới là bằng chứng sở hữu. Mục đích của
     * việc tính email trên đơn là để khách VÃNG LAI sau này nhận lại số của mình; người đã đăng
     * nhập thì đã có tài khoản, đơn của họ không được định nghĩa lại danh tính của chính họ.
     *
     * @test
     */
    public function an_order_placed_while_logged_in_is_not_proof_of_owning_the_phone(): void
    {
        Mail::fake();
        $bac = User::create(['name' => 'Bác Chỉ Có SĐT', 'phone' => '0912345678']);
        $this->assertTrue($bac->hasPlaceholderEmail());

        // Đơn bác đặt hộ cháu, LÚC ĐANG ĐĂNG NHẬP, và shop đã xác nhận.
        Order::create([
            'user_id' => $bac->id,
            'customer_name' => 'Bác Chỉ Có SĐT', 'customer_phone' => '0912345678',
            'customer_email' => 'chau-toi@example.com', 'customer_address' => 'x',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02',
            'total_price' => 100000, 'deposit_total' => 0, 'status' => 'confirmed',
        ]);

        // Bác quay lại, gõ SĐT của mình: KHÔNG được gửi mã vào hộp thư cháu.
        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertSessionHas('login_needs_support', true)
            ->assertSessionHasErrors('email');

        Mail::assertNothingQueued();
        $this->assertGuest();

        // Bác vẫn là tài khoản "chưa gắn hộp thư" → được tự nhập email CỦA MÌNH.
        $this->assertTrue($bac->fresh()->hasPlaceholderEmail());
    }

    /** Đơn bịa bị shop HUỶ cũng phải mất hiệu lực làm bằng chứng sở hữu. @test */
    public function a_cancelled_order_is_not_proof_of_owning_the_phone(): void
    {
        Mail::fake();
        Order::create([
            'customer_name' => 'Kẻ Lạ', 'customer_phone' => '0912345678',
            'customer_email' => 'kela@evil.com', 'customer_address' => 'x',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02',
            'total_price' => 100000, 'deposit_total' => 0, 'status' => 'cancelled',
        ]);
        User::factory()->create([
            'phone' => '0912345678', 'email' => 'ngoc@example.com',
            'email_verified_at' => now(), 'is_admin' => false,
        ]);

        $this->post(route('guest.login'), [
            'phone' => '0912345678', 'email' => 'kela@evil.com',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        Mail::assertNothingQueued();
    }

    /** lookup() đọc chung allowedEmailsFor() nên cũng không được lộ email trên đơn chưa xác nhận. @test */
    public function lookup_ignores_the_email_on_an_unconfirmed_order(): void
    {
        User::create(['name' => 'Khách Cũ', 'phone' => '0912345678']);
        Order::create([
            'customer_name' => 'Kẻ Lạ', 'customer_phone' => '0912345678',
            'customer_email' => 'kela@evil.com', 'customer_address' => 'x',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-02',
            'total_price' => 100000, 'deposit_total' => 0, 'status' => 'pending',
        ]);

        // Vẫn là tài khoản "không hộp thư nào" — đơn pending không nâng nó lên được.
        $this->getJson(route('guest.lookup', ['phone' => '0912345678']))
            ->assertJson(['exists' => true, 'email_mask' => null, 'needs_support' => true]);
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

    /**
     * Gõ SĐT xong thì màn nhập mã hiện "đã gửi tới …" — chỗ đó PHẢI che email.
     *
     * Người gõ số chưa chắc là chủ số. Trả email đầy đủ về client nghĩa là ai cũng moi được
     * email thật của người khác chỉ bằng số điện thoại — đúng thứ mà lookup() đã cố tình che.
     * Đo được trên staging 26/08 (lộ nguyên địa chỉ email thật của chủ số) rồi mới vá.
     *
     * Email trong SESSION vẫn phải là bản đầy đủ, nếu không bước xác thực mã sẽ hỏng.
     *
     * @test
     */
    public function the_email_shown_back_is_masked_but_the_session_keeps_the_real_one(): void
    {
        Mail::fake();
        User::factory()->create([
            'name' => 'Chị Ngọc', 'phone' => '0912345678',
            'email' => 'ngocanh@gmail.com', 'email_verified_at' => now(), 'is_admin' => false,
        ]);

        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertSessionHas('otp_sent', true);

        $this->assertSame('ng*****@gmail.com', session('otp_email'));
        $this->assertSame('ngocanh@gmail.com', session('otp_pending')['email']);
    }

    /**
     * Gõ SAI mã cũng không được làm lộ email thật.
     *
     * Đây là hàng rào cho một bản vá SAI rất dễ nghĩ ra. Trên màn nhập mã, câu "đã gửi mã
     * tới ..." lấy email từ flash `otp_email` — mà flash chỉ sống một request, nên gõ sai mã
     * một cái là nó rụng, còn trơ "gửi mã tới .". Cách chữa nghe hợp lý nhất là cho verifyOtp
     * phát lại email từ session; nhưng session giữ email THẬT, trong khi nhánh này cố ý chỉ
     * đưa bản che vì người đang gõ chưa chắc là chủ số (bopcamping-bqsv).
     *
     * Vá kiểu đó là mở lại đúng lỗ hổng ca 3.6 qua đường khác: gõ SĐT người lạ, cố tình nhập
     * sai một lần, server tự khai địa chỉ thật. Nên chỗ hiển thị được giữ ở client (LoginModal
     * nhớ lại đúng chuỗi server đã cố ý gửi), còn test này canh ranh giới phía server.
     *
     * @test
     */
    public function a_wrong_code_never_leaks_the_real_email(): void
    {
        Mail::fake();
        User::factory()->create([
            'name' => 'Chị Ngọc', 'phone' => '0912345678',
            'email' => 'ngocanh@gmail.com', 'email_verified_at' => now(), 'is_admin' => false,
        ]);

        $this->post(route('guest.login'), ['phone' => '0912345678'])
            ->assertSessionHas('otp_sent', true);

        $response = $this->post(route('guest.login.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        // Không nằm trong nội dung trả về...
        $response->assertDontSee('ngocanh@gmail.com');
        // ...và cũng không lén quay lại qua flash mà client sẽ render thẳng ra màn hình.
        $this->assertNotSame('ngocanh@gmail.com', session('otp_email'));
    }

    /**
     * Chạy trọn luồng đăng nhập có email (gửi mã → nhập mã) và trả response của bước cuối —
     * bước duy nhất mang cookie nhớ đăng nhập. Gọi Mail::fake() trước khi dùng.
     */
    private function loginViaOtp(string $phone, string $email): TestResponse
    {
        User::factory()->create([
            'phone' => $phone, 'email' => $email,
            'email_verified_at' => now(), 'is_admin' => false,
        ]);

        $this->post(route('guest.login'), ['phone' => $phone, 'email' => $email])
            ->assertSessionHas('otp_sent', true);

        $code = null;
        Mail::assertQueued(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $this->post(route('guest.login.verify'), ['code' => $code]);
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
