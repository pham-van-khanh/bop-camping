<?php

use App\Support\EditorHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chính sách bảo mật: thêm mục "5. Cookie và công cụ phân tích".
 *
 * Trang chính sách được tạo 1 lần bằng firstOrCreate (StaticPage::policy) nên sửa nội
 * dung mặc định trong model KHÔNG đổi được trang đã có trên production — migration này
 * nối mục 5 vào cuối trang đang có, giữ nguyên phần chủ shop đã sửa phía trên.
 *
 * - Chưa có dòng chinh-sach-bao-mat: không làm gì (lần đầu mở trang, firstOrCreate dùng
 *   nội dung mặc định mới, đã có mục 5).
 * - Trang đã có tiêu đề "Cookie và công cụ phân tích": bỏ qua (idempotent).
 * - Khối được lọc qua EditorHtml::clean như lúc admin lưu.
 * - down(): gỡ đúng khối đã nối nếu còn nguyên.
 *
 * SECTION là ảnh chụp nội dung tại thời điểm viết (khớp StaticPage::policyContent lúc đó)
 * — cố ý không trỏ sang model để down() vẫn gỡ đúng khối dù sau này mặc định đổi.
 */
return new class extends Migration
{
    private const SLUG = 'chinh-sach-bao-mat';

    private const HEADING = 'Cookie và công cụ phân tích';

    private const SECTION =
        '<h2>5. Cookie và công cụ phân tích</h2>'
        .'<p>Cookie là tệp nhỏ website lưu trong trình duyệt của bạn. BỐP CAMPING dùng cookie và bộ nhớ trình duyệt cho các việc sau:</p>'
        .'<ul><li><strong>Đăng nhập và bảo mật:</strong> cookie phiên làm việc và cookie XSRF-TOKEN giữ cho bạn đăng nhập và bảo vệ giỏ thuê, bước đặt đơn khỏi bị giả mạo. Thiếu hai cookie này website không hoạt động đúng.</li>'
        .'<li><strong>Nhớ đăng nhập:</strong> sau khi đăng nhập, trình duyệt nhớ bạn trong 60 ngày để không phải nhập lại mã OTP (tài khoản chưa có email được nhớ tới 400 ngày). Bấm đăng xuất là cookie này bị xóa.</li>'
        .'<li><strong>Nguồn truy cập (cookie bop_attr):</strong> khi bạn vào website từ một quảng cáo hoặc trang khác, chúng tôi lưu nguồn đó trong 30 ngày, có mã hóa. Cookie chỉ chứa các tham số utm_*, tên miền trang dẫn tới và loại mã quảng cáo (Google, Facebook hay TikTok), không lưu chính mã đó và không chứa thông tin cá nhân. Nhờ vậy chúng tôi biết kênh quảng cáo nào hiệu quả.</li>'
        .'<li><strong>Giỏ thuê:</strong> lưu trong bộ nhớ trình duyệt trên máy bạn (thiết bị, số lượng, ngày thuê) cho tới khi bạn đặt đơn.</li>'
        .'<li><strong>Phân tích và quảng cáo:</strong> chỉ khi shop bật, website mới tải Google Analytics 4, Google Tag Manager, Meta Pixel và TikTok Pixel. Các công cụ này nhận lượt xem trang và hoạt động mua sắm như xem thiết bị, thêm vào giỏ, bấm gọi hoặc nhắn Zalo, mã đơn và giá trị đơn. Chúng <strong>không</strong> nhận họ tên, số điện thoại, email hay địa chỉ của bạn, nhưng có thể đặt cookie riêng của Google, Meta, TikTok. Trang quản trị không tải các công cụ này.</li></ul>'
        .'<p><strong>Nếu bạn không muốn bị theo dõi:</strong></p>'
        .'<ul><li>Chặn hoặc xóa cookie trong cài đặt trình duyệt, hoặc dùng tiện ích chặn theo dõi. Nếu chặn cả cookie đăng nhập, bạn sẽ không đăng nhập hay đặt thuê được.</li>'
        .'<li>Google Analytics: cài <a href="https://tools.google.com/dlpage/gaoptout">tiện ích chặn Google Analytics</a> của Google.</li>'
        .'<li>Meta (Facebook, Instagram): chỉnh trong <a href="https://accountscenter.facebook.com/ad_preferences">Tùy chọn quảng cáo</a> của Meta.</li>'
        .'<li>TikTok: trong ứng dụng TikTok, vào Hồ sơ, chọn Cài đặt và quyền riêng tư, rồi chọn Quảng cáo.</li></ul>'
        .'<p>Muốn xem hoặc xóa dữ liệu của bạn, hãy liên hệ chúng tôi qua hotline hoặc Zalo.</p>';

    public function up(): void
    {
        $page = DB::table('static_pages')->where('slug', self::SLUG)->first();
        if ($page === null || str_contains((string) $page->content, self::HEADING)) {
            return;
        }

        DB::table('static_pages')->where('id', $page->id)->update([
            'content' => (string) $page->content.$this->block(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $page = DB::table('static_pages')->where('slug', self::SLUG)->first();
        $content = (string) ($page->content ?? '');
        $block = $this->block();
        $pos = strrpos($content, $block);
        if ($page === null || $pos === false) {
            return;
        }

        DB::table('static_pages')->where('id', $page->id)->update([
            'content' => substr_replace($content, '', $pos, strlen($block)),
            'updated_at' => now(),
        ]);
    }

    private function block(): string
    {
        return (string) EditorHtml::clean(self::SECTION);
    }
};
