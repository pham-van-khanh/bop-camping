<?php

use App\Support\EditorHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chính sách bảo mật: thêm gạch đầu dòng "Thống kê truy cập của shop (cookie bop_vid)" vào
 * mục 5, ngay sau gạch "Nguồn truy cập (cookie bop_attr)". Cùng cách làm migration 2026_10_12:
 * trang đã có trên production không đổi theo nội dung mặc định của model.
 *
 * - Chưa có trang / chưa có mục cookie / đã có bop_vid: bỏ qua (idempotent).
 * - down(): gỡ đúng khối đã chèn.
 */
return new class extends Migration
{
    private const SLUG = 'chinh-sach-bao-mat';

    private const ANCHOR = 'Nhờ vậy chúng tôi biết kênh quảng cáo nào hiệu quả.</li>';

    private const ITEM = '<li><strong>Thống kê truy cập của shop (cookie bop_vid):</strong> shop lưu một mã ngẫu nhiên trong trình duyệt 1 năm để đếm số khách, trang và thiết bị được xem, và biết có người bấm nút Zalo, gọi điện hay Messenger. Mã này không chứa thông tin cá nhân. Nếu bạn đã đăng nhập, lúc bạn bấm các nút liên hệ shop biết đó là tài khoản của bạn, và tin báo nội bộ (gồm tên, số điện thoại) được gửi tới chủ shop qua Telegram.</li>';

    public function up(): void
    {
        $page = DB::table('static_pages')->where('slug', self::SLUG)->first();
        $content = (string) ($page->content ?? '');
        if ($page === null || str_contains($content, 'bop_vid') || ! str_contains($content, self::ANCHOR)) {
            return;
        }

        DB::table('static_pages')->where('id', $page->id)->update([
            'content' => str_replace(self::ANCHOR, self::ANCHOR.$this->item(), $content),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $page = DB::table('static_pages')->where('slug', self::SLUG)->first();
        $content = (string) ($page->content ?? '');
        if ($page === null || ! str_contains($content, $this->item())) {
            return;
        }

        DB::table('static_pages')->where('id', $page->id)->update([
            'content' => str_replace($this->item(), '', $content),
            'updated_at' => now(),
        ]);
    }

    /** Khối đã qua EditorHtml::clean như lúc admin lưu, để khớp chính xác lúc gỡ. */
    private function item(): string
    {
        return (string) EditorHtml::clean(self::ITEM);
    }
};
