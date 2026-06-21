<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthController extends Controller
{
    public function showLogin(): SymfonyResponse
    {
        // Nếu đã đăng nhập với quyền admin → thẳng vào panel
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.orders');
        }

        return Inertia::render('Admin/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'phone.required'    => 'Vui lòng nhập số điện thoại.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        // Thử login bằng phone + password
        if (Auth::attempt(['phone' => $credentials['phone'], 'password' => $credentials['password']], true)) {
            if (! Auth::user()->is_admin) {
                Auth::logout();
                return back()->withErrors(['phone' => 'Tài khoản không có quyền admin.']);
            }

            $request->session()->regenerate();
            return redirect()->route('admin.orders');
        }

        return back()->withErrors(['phone' => 'Số điện thoại hoặc mật khẩu không đúng.']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
