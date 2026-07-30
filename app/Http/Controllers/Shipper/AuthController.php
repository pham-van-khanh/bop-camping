<?php

namespace App\Http\Controllers\Shipper;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Đăng nhập cho shipper: SĐT + mật khẩu (adr_shipper_role_and_access mục 3).
 *
 * CỐ TÌNH tách khỏi form đăng nhập admin: bead bopcamping-vo4 dự định đổi login admin
 * sang HTTP Basic Auth, dùng chung sẽ vỡ cả hai. Khách hàng không lọt vào được vì
 * Auth::attempt cần mật khẩu khớp (khách đăng nhập bằng OTP, không có mật khẩu thật).
 */
class AuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::check() && Auth::user()->isShipper()) {
            return redirect()->route('shipper.schedule');
        }

        return Inertia::render('Shipper/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        // Thông báo lỗi CHUNG cho cả 3 trường hợp (sai SĐT / sai mật khẩu / không phải
        // shipper) — không tiết lộ tài khoản nào có tồn tại trong hệ thống.
        $failed = fn () => back()->withErrors(['phone' => 'Số điện thoại hoặc mật khẩu không đúng.']);

        if (! Auth::attempt(['phone' => $credentials['phone'], 'password' => $credentials['password']], true)) {
            return $failed();
        }

        if (! Auth::user()->isShipper()) {
            Auth::logout();

            return $failed();
        }

        $request->session()->regenerate();

        // Về đúng trang shipper định vào (vd link Zalo kèm ?date=), mặc định là lịch hôm nay.
        return redirect()->intended(route('shipper.schedule'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('shipper.login');
    }
}
