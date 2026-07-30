<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bảo vệ khu vực /shipper/* (adr_shipper_role_and_access mục 3). Cùng khuôn EnsureAdmin
 * nhưng kiểm `is_shipper` và trả về trang đăng nhập của shipper.
 *
 * Middleware chỉ chặn "có phải shipper không". Việc "đơn này có phải của shipper này không"
 * là uỷ quyền theo bản ghi — kiểm trong controller (chống IDOR, CWE-639).
 */
class EnsureShipper
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isShipper()) {
            // guest() ghi lại URL đang muốn vào → sau khi đăng nhập quay đúng chỗ đó.
            // Cần cho link "Xem đơn" trong tin nhắn Zalo (kèm ?date=&month=): shipper bấm
            // link khi chưa đăng nhập vẫn về đúng NGÀY của lượt, không rơi về hôm nay.
            return redirect()->guest(route('shipper.login'));
        }

        return $next($request);
    }
}
