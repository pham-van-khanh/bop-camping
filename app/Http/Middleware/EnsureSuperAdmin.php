<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chỉ super admin được SỬA số liệu thu chi — khoản chi và vốn góp (bopcamping-n4qy).
 *
 * Đặt sau middleware 'admin' nên tới đây chắc chắn đã là admin đăng nhập; việc còn lại
 * chỉ là phân biệt quyền ghi. Trả 403 chứ không redirect: đây là hành động ghi, phản hồi
 * phải nói rõ bị từ chối thay vì lặng lẽ đưa về trang khác như thể thao tác đã chạy.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->is_super_admin,
            403,
            'Chỉ super admin được sửa số liệu thu chi.'
        );

        return $next($request);
    }
}
