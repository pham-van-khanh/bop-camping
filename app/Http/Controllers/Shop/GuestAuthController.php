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
     * Bước 1 — Đăng nhập bằng SĐT + tên + email.
     * - Email ĐÃ verify (đăng nhập lại) → vào thẳng, KHÔNG cần OTP.
     * - Lần đầu / email chưa verify → gửi OTP qua email, chờ bước 2.
     */
    public function store(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'email' => ['required', 'email', 'max:255'],
            'ref' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'Vui lòng nhập tên.',
            'name.min' => 'Tên phải có ít nhất 2 ký tự.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không hợp lệ.',
        ]);

        // Mã giới thiệu nhập tay → lưu session để áp lúc đặt đơn đầu (như link ?ref=).
        if ($ref = $request->input('ref')) {
            $request->session()->put('referral_ref', (string) $ref);
        }

        $existing = User::where('phone', $data['phone'])->first();

        if ($existing && $existing->name !== $data['name']) {
            return back()->withErrors(['phone' => 'Số điện thoại đã đăng ký với tên khác.'])->withInput();
        }

        // Email đã thuộc tài khoản KHÁC → chặn.
        $emailOwner = User::where('email', $data['email'])->first();
        if ($emailOwner && (! $existing || $emailOwner->id !== $existing->id)) {
            return back()->withErrors(['email' => 'Email đã dùng cho tài khoản khác.'])->withInput();
        }

        // Đã verify email này rồi → đăng nhập thẳng (chỉ OTP lần đầu).
        if ($existing && $existing->email_verified_at && $existing->email === $data['email']) {
            Auth::login($existing, remember: true);
            $request->session()->regenerate();

            return back();
        }

        // Còn lại: gửi OTP, giữ thông tin chờ ở session cho bước 2.
        $otp->send($data['email']);
        $request->session()->put('otp_pending', [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
        ]);

        return back()->with('otp_sent', true)->with('otp_email', $data['email']);
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
