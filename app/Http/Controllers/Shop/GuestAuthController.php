<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GuestAuthController extends Controller
{
    /**
     * Tuổi cookie nhớ đăng nhập cho tài khoản CHỈ CÓ SĐT (không hộp thư nào nhận được mã).
     *
     * Loại tài khoản này không khôi phục được: mất cookie là mất quyền vào, phải nhắn Zalo.
     * Nên cho nó sống lâu nhất có thể — 400 ngày là TRẦN CỨNG của Chrome cho tuổi cookie,
     * đặt cao hơn cũng bị trình duyệt cắt về đây. Tài khoản có email dùng mặc định 60 ngày
     * (AppServiceProvider) vì còn đường OTP để quay lại.
     */
    private const REMEMBER_MINUTES_PHONE_ONLY = 400 * 24 * 60;

    /**
     * Tra thông tin theo SĐT khi đăng nhập. SĐT là khoá định danh; tên để khách đổi tuỳ ý.
     * KHÔNG trả email thật ra client (tránh dò số gom email) — chỉ trả bản CHE để khách
     * nhận ra tài khoản; khi đăng nhập, server tự dùng email đã lưu. Admin không lộ qua đây.
     */
    public function lookup(Request $request): JsonResponse
    {
        $phone = (string) $request->query('phone', '');
        if (! preg_match('/^0[0-9]{8,10}$/', $phone)) {
            return response()->json(['exists' => false]);
        }

        $user = User::where('phone', $phone)->where('is_admin', false)->first();
        if (! $user) {
            // Số chưa có tài khoản. KHÔNG tiết lộ "số này từng đặt đơn" — đó là thông tin của
            // khách vãng lai. Nếu số đó thật sự vướng luật thì store() mới chặn (bopcamping-kuhg).
            return response()->json(['exists' => false]);
        }

        // Dùng CHUNG allowedEmailsFor() với store() (bopcamping-kuhg). Trước đây chỗ này chỉ nhìn
        // `users.email` nên lệch với store(): tài khoản email tạm NHƯNG có đơn cũ kèm email thật
        // vẫn nhận được mã, mà màn đăng nhập lại báo "không có email" → khách tưởng bị khoá.
        $allowed = $this->allowedEmailsFor($phone, $user);

        return response()->json([
            'exists' => true,
            'name' => $user->name,
            'email_mask' => $allowed->isNotEmpty() ? $this->maskEmail($allowed->first()) : null,
            // Không hộp thư nào nhận được mã → không có đường đăng nhập tự động. Báo sớm ngay
            // khi khách vừa gõ xong SĐT, kèm nút Zalo, thay vì để họ bấm gửi mã rồi mới báo lỗi.
            'needs_support' => $allowed->isEmpty(),
        ]);
    }

    /**
     * Những hộp thư ĐƯỢC PHÉP nhận mã xác thực cho một SĐT = email thật của tài khoản +
     * email trên các đơn cũ của chính SĐT đó. Trả về dạng đã lower + bỏ trùng.
     *
     * Đây là chốt chặn quan trọng nhất của bopcamping-bqsv, nên nó phải là NGUỒN DUY NHẤT:
     * `lookup()` (hiện gì cho khách) và `store()` (gửi mã đi đâu) bắt buộc đọc cùng một hàm,
     * lệch nhau là sinh lỗ hổng hoặc thông báo sai.
     *
     * Nếu chỉ bắt "phải qua OTP" mà KHÔNG giới hạn gửi đi đâu, kẻ lạ gõ SĐT nạn nhân kèm email
     * CỦA CHÍNH MÌNH sẽ nhận mã trong hộp thư mình, rồi verifyOtp (tìm tài khoản theo SĐT) gắn
     * email đó vào tài khoản nạn nhân và đăng nhập — chiếm trọn tài khoản.
     *
     * Đơn vãng lai chỉ có `customer_phone` (không có user_id) nhưng `relatedOrders()` khớp theo
     * SĐT, nên email trên đơn cũ cũng là bằng chứng sở hữu hợp lệ.
     *
     * @return Collection<int, string>
     */
    private function allowedEmailsFor(string $phone, ?User $existing): Collection
    {
        return collect([
            $existing && ! $existing->hasPlaceholderEmail() ? $existing->email : null,
        ])
            ->merge(Order::where('customer_phone', $phone)->pluck('customer_email'))
            ->filter()
            ->map(fn (string $e) => Str::lower(trim($e)))
            ->unique()
            ->values();
    }

    /**
     * Bước 1 — Đăng nhập bằng SĐT (+ tên tuỳ ý, + email TUỲ CHỌN).
     * - SĐT là khoá định danh duy nhất; KHÔNG ràng buộc tên — khách đổi tên thoải mái.
     * - SĐT đã gắn với tài khoản/đơn nào đó → LUÔN qua OTP. Số điện thoại chưa hề được xác
     *   thực nên không dùng nó làm bằng chứng danh tính được (bopcamping-bqsv).
     * - Số hoàn toàn mới (chưa tài khoản, chưa đơn) → tạo và vào thẳng, không có gì để chiếm.
     */
    public function store(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'ref' => ['nullable', 'string', 'max:20'],
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'email.email' => 'Email không hợp lệ.',
        ]);

        // Mã giới thiệu nhập tay → lưu session để áp lúc đặt đơn đầu (như link ?ref=).
        if ($ref = $request->input('ref')) {
            $request->session()->put('referral_ref', (string) $ref);
        }

        // SĐT thuộc tài khoản ADMIN → không phục vụ ở luồng khách (bopcamping-bqsv).
        // Trước đây `$existing` không lọc `is_admin` nên gõ đúng SĐT admin là đăng nhập thẳng
        // vào tài khoản admin — mà số đó in công khai ở footer dưới dạng hotline. Chặn hẳn ở
        // đây (thay vì chỉ lọc) để không đẻ thêm tài khoản khách trùng SĐT với admin.
        if (User::where('phone', $data['phone'])->where('is_admin', true)->exists()) {
            return back()->withErrors([
                'phone' => 'Số điện thoại này không dùng để đăng nhập khách.',
            ])->withInput();
        }

        $existing = User::where('phone', $data['phone'])->where('is_admin', false)->first();
        $allowedEmails = $this->allowedEmailsFor($data['phone'], $existing);

        // SĐT đã có "chủ" = đã có tài khoản HOẶC đã từng đặt đơn.
        //
        // Trước bopcamping-kuhg tiêu chí là "đã có hộp thư gắn vào" — hụt đúng một ca có thật:
        // email ở checkout là TUỲ CHỌN (OrderController), nên khách đặt cả chục đơn mà bỏ trống
        // email thì `$allowedEmails` rỗng → số bị coi là vô chủ → người lạ biết SĐT gõ kèm email
        // CỦA HỌ là tạo/chiếm được tài khoản, rồi `relatedOrders()` khớp theo SĐT dọn ra toàn bộ
        // lịch sử đơn: tên, địa chỉ giao, số điện thoại. Đếm theo đơn thay vì theo email bịt chỗ đó.
        $phoneIsClaimed = $existing !== null
            || Order::where('customer_phone', $data['phone'])->exists();

        // Số đã có chủ NHƯNG không hộp thư nào nhận được mã → không có cách nào xác thực tự động.
        // Chặn hẳn (cả khi bỏ trống lẫn khi gõ email lạ — nếu chỉ chặn nhánh bỏ trống thì gõ đại
        // một email là lách được), chỉ đường sang Zalo để người trực xác minh thủ công.
        //
        // Người trực PHẢI hỏi thông tin một đơn cũ (mã đơn / ngày thuê / địa chỉ giao) trước khi
        // gắn email, nếu không thì kẻ tấn công chỉ việc nhắn Zalo là qua mặt được toàn bộ chốt này.
        if ($phoneIsClaimed && $allowedEmails->isEmpty()) {
            return back()
                ->withErrors(['phone' => 'Số này cần có email mới đăng nhập được. Nhắn Zalo để shop hỗ trợ.'])
                ->with('login_needs_support', true)
                ->withInput();
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            // SĐT đã thuộc về ai đó → KHÔNG BAO GIỜ cho vào chỉ bằng SĐT. Số điện thoại chưa
            // hề được xác thực (không có OTP SMS) nên nó không phải bằng chứng danh tính.
            if ($phoneIsClaimed) {
                // Gửi tới hộp thư đã gắn sẵn (ưu tiên email tài khoản — nó đứng đầu danh sách).
                $target = $allowedEmails->first();

                // Hộp thư đó đang thuộc về một TÀI KHOẢN KHÁC (vd khách từng đặt hộ bằng email
                // người thân). Đi tiếp thì verifyOtp tạo user mới trùng email → vỡ ràng buộc
                // unique và trả 500. Dừng lại và chỉ đường liên hệ.
                if ($this->emailBelongsToAnotherAccount($target, $existing)) {
                    // Cũng là ngõ cụt như nhánh "không có hộp thư nào" → bật cùng cờ để
                    // LoginModal hiện nút Zalo, thay vì để khách bấm gửi mã đi bấm lại.
                    return back()->withErrors([
                        'email' => 'Email gắn với số này đang dùng cho tài khoản khác. Nhắn Zalo để shop hỗ trợ.',
                    ])->with('login_needs_support', true)->withInput();
                }

                $otp->send($target);
                $request->session()->put('otp_pending', [
                    'name' => $this->resolveName($data['name'] ?? null, $existing, $data['phone']),
                    'phone' => $data['phone'],
                    'email' => $target,
                ]);

                // CHE email khi trả về client (bopcamping-bqsv). Nhánh này chạy khi khách chỉ
                // gõ SĐT — người gõ CHƯA CHẮC là chủ số. Trả email đầy đủ thì bất kỳ ai cũng
                // moi được email thật của người khác chỉ bằng số điện thoại, đúng thứ mà
                // lookup() đã cố tình che. Đo được trên staging 26/08 trước khi vá.
                return back()->with('otp_sent', true)->with('otp_email', $this->maskEmail($target));
            }

            // Số hoàn toàn mới: chưa tài khoản, chưa đơn nào → không có gì để chiếm.
            // (Không còn nhánh "$existing nhưng chưa có email": tài khoản nào cũng làm
            // $phoneIsClaimed = true, và trường hợp không có hộp thư đã bị chặn ở trên.)
            $user = new User(['phone' => $data['phone']]);
            $user->name = $this->resolveName($data['name'] ?? null, null, $data['phone']);
            $user->save();

            // Tài khoản này chưa có email → không có đường OTP để quay lại. Cho cookie sống
            // 400 ngày thay vì 60 mặc định, vì với họ mất cookie là mất luôn tài khoản.
            // setRememberDuration() chỉ có ở SessionGuard (không nằm trong interface Guard) —
            // kiểm kiểu trước để đổi driver auth không làm nổ ứng dụng ở đúng chỗ đăng nhập.
            $guard = Auth::guard('web');
            if ($guard instanceof SessionGuard) {
                $guard->setRememberDuration(self::REMEMBER_MINUTES_PHONE_ONLY);
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return back();
        }

        // Gõ một email LẠ cho một SĐT đã có chủ → chính là kịch bản chiếm tài khoản ở trên.
        if ($phoneIsClaimed && ! $allowedEmails->contains(Str::lower($email))) {
            return back()->withErrors([
                'email' => 'Email không khớp với số điện thoại này. Dùng email bạn đã đăng ký, hoặc nhắn Zalo để shop hỗ trợ.',
            ])->withInput();
        }

        // Email đã thuộc tài khoản KHÁC → chặn.
        if ($this->emailBelongsToAnotherAccount($email, $existing)) {
            return back()->withErrors(['email' => 'Email đã dùng cho tài khoản khác.'])->withInput();
        }

        // Tên hiển thị: nhập gì lấy nấy; bỏ trống thì giữ tên cũ, hoặc lấy SĐT cho user mới.
        $name = $this->resolveName($data['name'] ?? null, $existing, $data['phone']);

        // KHÔNG còn nhánh "email đã verify → vào thẳng" (bopcamping-bqsv): gõ đúng email cũng
        // không phải bằng chứng danh tính (email đoán được, lộ được). Mọi đường đều qua OTP;
        // khách quen không bị phiền vì cookie nhớ đăng nhập 60 ngày lo phần quay lại.

        // Gửi OTP, giữ thông tin chờ ở session cho bước 2.
        $otp->send($email);
        $request->session()->put('otp_pending', [
            'name' => $name,
            'phone' => $data['phone'],
            'email' => $email,
        ]);

        return back()->with('otp_sent', true)->with('otp_email', $email);
    }

    /**
     * Email này đang thuộc về một tài khoản KHÁC (không phải tài khoản của SĐT đang đăng nhập)?
     *
     * `users.email` là UNIQUE, nên đi tiếp mà không kiểm sẽ vỡ ràng buộc lúc verifyOtp tạo
     * user — trả 500 thay vì một thông báo đọc được.
     */
    private function emailBelongsToAnotherAccount(string $email, ?User $existing): bool
    {
        $owner = User::where('email', $email)->first();

        return $owner && (! $existing || $owner->id !== $existing->id);
    }

    /** Tên hiển thị hiệu lực: ưu tiên tên vừa nhập, rồi tên cũ, cuối cùng là SĐT. */
    private function resolveName(?string $input, ?User $existing, string $phone): string
    {
        $input = trim((string) $input);
        if ($input !== '') {
            return $input;
        }

        return $existing?->name ?? $phone;
    }

    /** Che email để hiển thị: giữ 2 ký tự đầu phần tên + tên miền, vd quen@x.com → qu**@x.com. */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $keep = mb_strlen($local) <= 2 ? 1 : 2;
        $masked = mb_substr($local, 0, $keep).str_repeat('*', max(2, mb_strlen($local) - $keep));

        return $domain === '' ? $masked : $masked.'@'.$domain;
    }

    /**
     * Bước 2 — Xác thực OTP rồi tạo/cập nhật tài khoản và đăng nhập.
     */
    public function verifyOtp(Request $request, OtpService $otp): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ], [
            'code.required' => 'Vui lòng nhập mã OTP.',
            'code.regex' => 'Mã OTP gồm 6 chữ số.',
        ]);

        $pending = $request->session()->get('otp_pending');
        if (! $pending) {
            return back()->withErrors(['code' => 'Phiên đã hết hạn, vui lòng gửi lại mã.']);
        }

        if (! $otp->verify($pending['email'], $request->input('code'))) {
            return back()->withErrors(['code' => 'Mã không đúng hoặc đã hết hạn.']);
        }

        // Set trực tiếp vì email_verified_at không nằm trong $fillable (tránh mass-assign).
        // Lọc `is_admin` ở đây nữa (phòng thủ nhiều lớp): store() đã chặn SĐT admin từ đầu nên
        // otp_pending không thể mang SĐT admin, nhưng không dựa vào một chốt chặn duy nhất.
        $user = User::where('phone', $pending['phone'])->where('is_admin', false)->first()
            ?? new User(['phone' => $pending['phone']]);
        $user->name = $pending['name'];
        $user->email = $pending['email'];
        $user->email_verified_at = now();
        $user->save();

        $request->session()->forget('otp_pending');
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return back();
    }

    /**
     * Đăng xuất.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
