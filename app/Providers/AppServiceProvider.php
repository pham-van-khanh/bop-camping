<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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

        /**
         * Mọi lần đăng nhập MỚI đều dọn cờ "đang xem hộ khách" (bopcamping-bqsv).
         *
         * `session()->regenerate()` chỉ đổi id phiên chứ GIỮ NGUYÊN dữ liệu, nên trước đây
         * `impersonator_id` sống sót qua một lần đăng nhập lại. Gặp thật khi admin đang xem hộ
         * khách rồi mở một link /admin/… đã bookmark: bị đẩy về trang đăng nhập admin, đăng nhập
         * xong thì phiên vừa là admin vừa còn cờ mạo danh — thanh vàng hiện tên chính mình, và
         * nút "Đăng xuất" phía khách rơi vào nhánh thoát-mạo-danh nên đăng nhập lại admin thay vì
         * đăng xuất. Bấm xong tưởng đã ra, thực tế vẫn vào đầy đủ; máy dùng chung là người sau
         * thừa hưởng phiên admin.
         *
         * Đặt ở đây thay vì rải `forget()` vào từng controller: cờ này phải chết ở MỌI đường
         * đăng nhập, kể cả đường viết sau này. Không đụng tới impersonate(): chỗ đó gọi
         * Auth::login() TRƯỚC rồi mới put() cờ, nên listener chạy xong xuôi mới tới lượt ghi.
         */
        Event::listen(function (Login $event) {
            if (! app()->bound('session') || ! app('session')->isStarted()) {
                return;
            }

            app('session')->forget('impersonator_id');
        });
    }
}
