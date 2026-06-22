<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bắt mã giới thiệu từ link (?ref=CODE) và lưu vào session để giữ tới lúc khách đăng ký.
 * Chỉ lưu khi khách CHƯA đăng nhập (người đã có tài khoản không cần referred_by).
 */
class CaptureReferralCode
{
    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');

        if ($ref && ! $request->user()) {
            $request->session()->put('referral_ref', (string) $ref);
        }

        return $next($request);
    }
}
