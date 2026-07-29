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
            return redirect()->route('shipper.login');
        }

        return $next($request);
    }
}
