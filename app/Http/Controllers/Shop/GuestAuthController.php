<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuestAuthController extends Controller
{
    /**
     * Đăng nhập / tự động tạo tài khoản bằng SĐT + tên.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/'],
        ], [
            'name.required' => 'Vui lòng nhập tên.',
            'name.min' => 'Tên phải có ít nhất 2 ký tự.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
        ]);

        $user = User::where('phone', $validated['phone'])->first();

        if ($user) {
            if ($user->name !== $validated['name']) {
                return back()->withErrors([
                    'phone' => 'Số điện thoại đã đăng ký với tên khác.',
                ])->withInput();
            }
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'referred_by' => $this->resolveReferrerId(
                    $request->input('ref') ?: $request->session()->pull('referral_ref')
                ),
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return back();
    }

    /**
     * Tra người giới thiệu từ mã (case-insensitive). null nếu không có / không hợp lệ.
     */
    private function resolveReferrerId(?string $code): ?int
    {
        if (! $code) {
            return null;
        }

        return User::whereRaw('UPPER(referral_code) = ?', [strtoupper(trim($code))])
            ->value('id');
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
