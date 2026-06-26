<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestAuthController extends Controller
{
    /**
     * Tra thông tin theo SĐT để tự điền email (+ tên hiện tại) khi đăng nhập.
     * SĐT là khoá định danh; tên để tuỳ khách đổi. Chỉ trả khách thường, email thật
     * (bỏ email tạm @bopcamping.local) — admin không lộ qua đây.
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

        return response()->json([
            'exists' => true,
            'name' => $user->name,
            'email' => $user->hasPlaceholderEmail() ? null : $user->email,
        ]);
    }

    /**
     * Bước 1 — Đăng nhập bằng SĐT + email (+ tên tuỳ ý).
     * - SĐT là khoá định danh; KHÔNG ràng buộc tên — khách đổi tên thoải mái (như đổi tên hiển thị).
     * - Email ĐÃ verify (đăng nhập lại) → vào thẳng, KHÔNG cần OTP.
     * - Lần đầu / email chưa verify → gửi OTP qua email, chờ bước 2.
     */
    public function store(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
            'email' => ['required', 'email', 'max:255'],
            'ref' => ['nullable', 'string', 'max:20'],
        ], [
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

        // Email đã thuộc tài khoản KHÁC → chặn.
        $emailOwner = User::where('email', $data['email'])->first();
        if ($emailOwner && (! $existing || $emailOwner->id !== $existing->id)) {
            return back()->withErrors(['email' => 'Email đã dùng cho tài khoản khác.'])->withInput();
        }

        // Tên hiển thị: nhập gì lấy nấy; bỏ trống thì giữ tên cũ, hoặc lấy SĐT cho user mới.
        $name = $this->resolveName($data['name'] ?? null, $existing, $data['phone']);

        // Đã verify email này rồi → đăng nhập thẳng (chỉ OTP lần đầu); cập nhật tên nếu đổi.
        if ($existing && $existing->email_verified_at && $existing->email === $data['email']) {
            if ($existing->name !== $name) {
                $existing->name = $name;
                $existing->save();
            }
            Auth::login($existing, remember: true);
            $request->session()->regenerate();

            return back();
        }

        // Còn lại: gửi OTP, giữ thông tin chờ ở session cho bước 2.
        $otp->send($data['email']);
        $request->session()->put('otp_pending', [
            'name' => $name,
            'phone' => $data['phone'],
            'email' => $data['email'],
        ]);

        return back()->with('otp_sent', true)->with('otp_email', $data['email']);
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
