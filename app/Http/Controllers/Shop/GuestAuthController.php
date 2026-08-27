<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GuestAuthController extends Controller
{
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
            return response()->json(['exists' => false]);
        }

        // Có hộp thư THẬT là đủ để nhận OTP — không đòi `email_verified_at` nữa, cho khớp luật
        // ở store() (bopcamping-bqsv): khách do admin tạo hộ có email thật nhưng chưa xác thực,
        // bắt họ gõ lại email của chính mình là phiền vô ích. Email tạm → null như cũ.
        $hasRealEmail = ! $user->hasPlaceholderEmail();

        return response()->json([
            'exists' => true,
            'name' => $user->name,
            'email_mask' => $hasRealEmail ? $this->maskEmail($user->email) : null,
        ]);
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

        // Những hộp thư ĐƯỢC PHÉP nhận OTP cho SĐT này = email thật của tài khoản + email
        // trên các đơn cũ của chính SĐT đó.
        //
        // Đây là chốt chặn quan trọng nhất. Nếu chỉ bắt "phải qua OTP" mà KHÔNG giới hạn gửi
        // đi đâu, thì kẻ lạ gõ SĐT nạn nhân kèm email CỦA CHÍNH MÌNH sẽ nhận mã trong hộp thư
        // mình, và verifyOtp (tìm tài khoản theo SĐT) sẽ gắn email đó vào tài khoản nạn nhân
        // rồi đăng nhập — chiếm trọn tài khoản. Bắt buộc mã phải về đúng hộp thư đã gắn sẵn
        // với số này từ trước.
        //
        // Đơn vãng lai chỉ có `customer_phone` (không có user_id) nhưng `relatedOrders()` khớp
        // theo SĐT, nên email trên đơn cũ cũng là bằng chứng sở hữu hợp lệ.
        $allowedEmails = collect([
            $existing && ! $existing->hasPlaceholderEmail() ? $existing->email : null,
        ])
            ->merge(Order::where('customer_phone', $data['phone'])->pluck('customer_email'))
            ->filter()
            ->map(fn (string $e) => Str::lower(trim($e)))
            ->unique()
            ->values();

        // SĐT đã có "chủ" = đã có hộp thư nào đó gắn với nó. Tài khoản chỉ có email TẠM và
        // chưa từng đặt đơn thì chưa có gì để chiếm — vẫn cho gắn email mới như trước.
        $phoneIsClaimed = $allowedEmails->isNotEmpty();

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
                    return back()->withErrors([
                        'email' => 'Email gắn với số này đang dùng cho tài khoản khác. Nhắn Zalo để shop hỗ trợ.',
                    ])->withInput();
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

            if ($existing) {
                // Tài khoản chỉ có email tạm và chưa có đơn → không có hộp thư nào để gửi.
                return back()->withErrors([
                    'email' => 'Vui lòng nhập email để nhận mã xác thực.',
                ])->withInput();
            }

            // Số hoàn toàn mới: chưa tài khoản, chưa đơn nào → không có gì để chiếm.
            $user = new User(['phone' => $data['phone']]);
            $user->name = $this->resolveName($data['name'] ?? null, null, $data['phone']);
            $user->save();

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
