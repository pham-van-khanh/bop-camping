<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestAuthController extends Controller
{
    /**
     * Bước 1 — Đăng nhập bằng SĐT (+ tên tuỳ ý, + email TUỲ CHỌN).
     * - SĐT là khoá định danh duy nhất; KHÔNG ràng buộc tên — khách đổi tên thoải mái.
     * - Email không bắt buộc: bỏ trống → vào thẳng bằng SĐT, không cần OTP.
     * - Có nhập email mới/chưa verify → gửi OTP qua email, chờ bước 2. Email ĐÃ verify → vào thẳng.
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

        $existing = User::where('phone', $data['phone'])->first();

        // Email KHÔNG bắt buộc — SĐT là khoá định danh duy nhất. Để trống:
        // - khách quen (đã có email thật + verify) → server tự dùng email đã lưu (đăng nhập thẳng, không đi qua client).
        // - khách chưa từng có email thật (mới hoặc cũ) → tạo/đăng nhập thẳng bằng SĐT, KHÔNG cần OTP
        //   (không có email thật để xác thực). Khách có thể bổ sung email sau để nhận ưu đãi đơn đầu.
        $email = trim((string) ($data['email'] ?? ''));
        if ($email === '') {
            if ($existing && $existing->email_verified_at && ! $existing->hasPlaceholderEmail()) {
                $email = $existing->email;
            } else {
                $name = $this->resolveName($data['name'] ?? null, $existing, $data['phone']);
                $user = $existing ?? new User(['phone' => $data['phone']]);
                $user->name = $name;
                $user->save();

                Auth::login($user, remember: true);
                $request->session()->regenerate();

                return back();
            }
        }

        // Email đã thuộc tài khoản KHÁC → chặn.
        $emailOwner = User::where('email', $email)->first();
        if ($emailOwner && (! $existing || $emailOwner->id !== $existing->id)) {
            return back()->withErrors(['email' => 'Email đã dùng cho tài khoản khác.'])->withInput();
        }

        // Tên hiển thị: nhập gì lấy nấy; bỏ trống thì giữ tên cũ, hoặc lấy SĐT cho user mới.
        $name = $this->resolveName($data['name'] ?? null, $existing, $data['phone']);

        // Đã verify email này rồi → đăng nhập thẳng (chỉ OTP lần đầu); cập nhật tên nếu đổi.
        if ($existing && $existing->email_verified_at && $existing->email === $email) {
            if ($existing->name !== $name) {
                $existing->name = $name;
                $existing->save();
            }
            Auth::login($existing, remember: true);
            $request->session()->regenerate();

            return back();
        }

        // Còn lại: gửi OTP, giữ thông tin chờ ở session cho bước 2.
        $otp->send($email);
        $request->session()->put('otp_pending', [
            'name' => $name,
            'phone' => $data['phone'],
            'email' => $email,
        ]);

        return back()->with('otp_sent', true)->with('otp_email', $email);
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
        $user = User::where('phone', $pending['phone'])->first() ?? new User(['phone' => $pending['phone']]);
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
