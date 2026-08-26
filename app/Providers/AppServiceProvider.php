<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        /**
         * Cookie "nhớ đăng nhập" 60 ngày (bopcamping-bqsv).
         *
         * Mặc định của Laravel là 576.000 phút = 400 NGÀY — quá dài cho máy dùng chung.
         * Đây cũng là thứ giữ cho khách quen khỏi phải nhập OTP mỗi lần: từ nay mọi đăng
         * nhập đều qua OTP, nên cookie này chính là phần bù trải nghiệm. Đừng nhầm với
         * SESSION_LIFETIME (120 phút) — đó là tuổi của phiên, không phải tuổi của cookie.
         */
        Auth::guard('web')->setRememberDuration(60 * 24 * 60);
    }
}
