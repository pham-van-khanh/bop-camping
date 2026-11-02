<?php

use App\Http\Middleware\CanonicalHost;
use App\Http\Middleware\CaptureAttribution;
use App\Http\Middleware\CaptureReferralCode;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureShipper;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            // Chuẩn hoá tên miền TRƯỚC mọi thứ khác: chạy sau thì đã tốn công dựng
            // response cho một host sắp bị chuyển hướng (bopcamping-1xja).
            CanonicalHost::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CaptureReferralCode::class,
            // Nguồn khách first-touch (UTM/click id/referrer) → cookie bop_attr (bopcamping-wi6h).
            CaptureAttribution::class,
            // Thống kê truy cập nội bộ (visit_events) — đứng SAU CaptureAttribution.
            RecordVisit::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'shipper' => EnsureShipper::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
