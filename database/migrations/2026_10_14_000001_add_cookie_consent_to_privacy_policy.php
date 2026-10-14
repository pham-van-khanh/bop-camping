<?php

use App\Support\EditorHtml;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chính sách bảo mật, mục "5. Cookie và công cụ phân tích": thêm phần banner đồng ý cookie
 * (bopcamping-vqwr) — công cụ đo lường/quảng cáo chỉ tải khi khách bấm "Đồng ý", từ chối
 * vẫn thuê bình thường, đổi ý ở "Cài đặt cookie".
 *
 * Mục 5 đã được migration 2026_10_12_000001 nối vào trang trên production, và trang chính
 * sách chỉ tạo 1 lần (firstOrCreate) nên sửa mặc định trong model không tới được production.
 * Migration này thay ĐÚNG khối cũ bằng khối mới:
 *
 * - Không có trang / đã là khối mới: bỏ qua (idempotent).
 * - Chủ shop đã tự sửa mục 5 (không còn khớp khối cũ): KHÔNG ghi đè — chỉ ghi log để sửa
 *   tay trong admin.
 * - down(): đổi khối mới về khối cũ nếu còn nguyên.
 *
 * Hai khối là ảnh chụp nội dung (cố ý không trỏ sang model), như migration trước.
 */
return new class extends Migration
{
    private const SLUG = 'chinh-sach-bao-mat';

    /** Nguyên văn SECTION của 2026_10_12_000001. */
    private const OLD_SECTION =
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

    /** Khớp StaticPage::policyContent('chinh-sach-bao-mat') lúc viết migration này. */
    private const NEW_SECTION =
        '<h2>5. Cookie và công cụ phân tích</h2>'
        .'<p>Cookie là tệp nhỏ website lưu trong trình duyệt của bạn. BỐP CAMPING dùng cookie và bộ nhớ trình duyệt cho các việc sau:</p>'
        .'<ul><li><strong>Đăng nhập và bảo mật:</strong> cookie phiên làm việc và cookie XSRF-TOKEN giữ cho bạn đăng nhập và bảo vệ giỏ thuê, bước đặt đơn khỏi bị giả mạo. Thiếu hai cookie này website không hoạt động đúng.</li>'
        .'<li><strong>Nhớ đăng nhập:</strong> sau khi đăng nhập, trình duyệt nhớ bạn trong 60 ngày để không phải nhập lại mã OTP (tài khoản chưa có email được nhớ tới 400 ngày). Bấm đăng xuất là cookie này bị xóa.</li>'
        .'<li><strong>Nguồn truy cập (cookie bop_attr):</strong> khi bạn vào website từ một quảng cáo hoặc trang khác, chúng tôi lưu nguồn đó trong 30 ngày, có mã hóa. Cookie chỉ chứa các tham số utm_*, tên miền trang dẫn tới và loại mã quảng cáo (Google, Facebook hay TikTok), không lưu chính mã đó và không chứa thông tin cá nhân. Nhờ vậy chúng tôi biết kênh quảng cáo nào hiệu quả.</li>'
        .'<li><strong>Giỏ thuê:</strong> lưu trong bộ nhớ trình duyệt trên máy bạn (thiết bị, số lượng, ngày thuê) cho tới khi bạn đặt đơn.</li>'
        .'<li><strong>Lựa chọn cookie của bạn:</strong> lưu trong bộ nhớ trình duyệt trên máy bạn 12 tháng; hết hạn thì website hỏi lại.</li>'
        .'<li><strong>Phân tích và quảng cáo:</strong> chỉ khi shop bật <strong>và bạn bấm "Đồng ý" ở banner cookie</strong>, website mới tải Google Analytics 4, Google Tag Manager, Meta Pixel và TikTok Pixel. Các công cụ này nhận lượt xem trang và hoạt động mua sắm như xem thiết bị, thêm vào giỏ, bấm gọi hoặc nhắn Zalo, mã đơn và giá trị đơn. Chúng <strong>không</strong> nhận họ tên, số điện thoại, email hay địa chỉ của bạn, nhưng có thể đặt cookie riêng của Google, Meta, TikTok. Bấm "Từ chối" thì bạn vẫn xem và thuê đồ bình thường. Trang quản trị không tải các công cụ này.</li></ul>'
        .'<p><strong>Nếu bạn không muốn bị theo dõi:</strong></p>'
        .'<ul><li>Bấm "Từ chối" ở banner cookie, hoặc đổi ý bất cứ lúc nào qua mục "Cài đặt cookie" ở cuối trang. Rút lại đồng ý thì website ngừng gửi dữ liệu cho các công cụ trên và xóa cookie chúng đã đặt trên website này.</li>'
        .'<li>Chặn hoặc xóa cookie trong cài đặt trình duyệt, hoặc dùng tiện ích chặn theo dõi. Nếu chặn cả cookie đăng nhập, bạn sẽ không đăng nhập hay đặt thuê được.</li>'
        .'<li>Google Analytics: cài <a href="https://tools.google.com/dlpage/gaoptout">tiện ích chặn Google Analytics</a> của Google.</li>'
        .'<li>Meta (Facebook, Instagram): chỉnh trong <a href="https://accountscenter.facebook.com/ad_preferences">Tùy chọn quảng cáo</a> của Meta.</li>'
        .'<li>TikTok: trong ứng dụng TikTok, vào Hồ sơ, chọn Cài đặt và quyền riêng tư, rồi chọn Quảng cáo.</li></ul>'
        .'<p>Muốn xem hoặc xóa dữ liệu của bạn, hãy liên hệ chúng tôi qua hotline hoặc Zalo.</p>';

    public function up(): void
    {
        $this->swap(self::OLD_SECTION, self::NEW_SECTION, warnIfMissing: true);
    }

    public function down(): void
    {
        $this->swap(self::NEW_SECTION, self::OLD_SECTION, warnIfMissing: false);
    }

    private function swap(string $from, string $to, bool $warnIfMissing): void
    {
        $page = DB::table('static_pages')->where('slug', self::SLUG)->first();
        if ($page === null) {
            return;
        }

        $content = (string) $page->content;
        $fromBlock = (string) EditorHtml::clean($from);
        $toBlock = (string) EditorHtml::clean($to);

        if (str_contains($content, $toBlock)) {
            return;
        }
        $pos = strrpos($content, $fromBlock);
        if ($pos === false) {
            if ($warnIfMissing) {
                Log::warning('Chính sách bảo mật: mục Cookie đã bị sửa tay, không tự thêm phần đồng ý cookie — cần sửa trong admin (bopcamping-vqwr).');
            }

            return;
        }

        DB::table('static_pages')->where('id', $page->id)->update([
            'content' => substr_replace($content, $toBlock, $pos, strlen($fromBlock)),
            'updated_at' => now(),
        ]);
    }
};
