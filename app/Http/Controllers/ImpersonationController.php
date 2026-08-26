<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Thoát khỏi phiên "đăng nhập thay khách" (bopcamping-bqsv).
 *
 * Nằm NGOÀI nhóm route `admin` là có chủ ý: lúc đang mạo danh, phiên hiện tại là KHÁCH chứ
 * không phải admin, nên middleware `EnsureAdmin` sẽ chặn ngay. Bảo vệ thật nằm ở chỗ chỉ ai
 * có `impersonator_id` trong session mới làm được gì ở đây.
 */
class ImpersonationController extends Controller
{
    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');

        // Không có phiên mạo danh nào → đây là khách bình thường gọi nhầm. Đừng đăng xuất họ
        // (đá một khách đang mua hàng ra ngoài chỉ vì một request lạc là quá tay).
        if (! $adminId) {
            return redirect()->route('home');
        }

        $admin = User::find($adminId);

        // KHÔNG tin session: quyền admin có thể đã bị gỡ trong lúc đang mạo danh. Kiểm lại
        // `is_admin` ở thời điểm thoát, nếu không thì một cựu-admin quay lại được panel.
        if (! $admin || ! $admin->is_admin) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        Log::info('admin.user.impersonation_stopped', [
            'actor_id' => $admin->id,
            'ip' => $request->ip(),
        ]);

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.users');
    }
}
