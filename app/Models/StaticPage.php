<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaticPage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'cover_path',
        'content',
    ];

    /**
     * Trang giới thiệu — firstOrCreate với template mặc định để prod không cần
     * chạy seeder riêng (controller/seeder cùng gọi hàm này, single source).
     * Nội dung soạn theo kiểu TipTap (ảnh rời + đoạn văn xen kẽ) để phía khách
     * render bố cục magazine bằng MagazineContent.
     */
    public static function about(): self
    {
        return static::firstOrCreate(['slug' => 'gioi-thieu'], static::aboutDefaults());
    }

    /**
     * Các trang chính sách — slug => tiêu đề mặc định. Nội dung mẫu ở
     * policyDefaults(); admin sửa lại trong "Trang nội dung".
     */
    public const POLICIES = [
        'chinh-sach-bao-mat' => 'Chính sách bảo mật',
        'dieu-khoan-su-dung' => 'Điều khoản sử dụng',
        'chinh-sach-thanh-toan' => 'Chính sách thanh toán',
        'chinh-sach-giao-nhan' => 'Chính sách giao nhận',
        'chinh-sach-doi-tra' => 'Chính sách hủy / đổi / trả',
    ];

    /**
     * Lấy (hoặc tạo) một trang chính sách theo slug. firstOrCreate với nội dung
     * mẫu để prod không cần seeder — cùng cơ chế single source như about().
     */
    public static function policy(string $slug): self
    {
        return static::firstOrCreate(['slug' => $slug], static::policyDefaults($slug));
    }

    /** Đảm bảo cả trang giới thiệu lẫn 5 trang chính sách đều tồn tại. */
    public static function provisionAll(): void
    {
        static::about();
        foreach (array_keys(self::POLICIES) as $slug) {
            static::policy($slug);
        }
    }

    /** Template mặc định cho một trang chính sách — user sửa lại trong admin. */
    public static function policyDefaults(string $slug): array
    {
        $title = self::POLICIES[$slug] ?? 'Chính sách';

        return [
            'title' => $title.' — BỐP CAMPING',
            'cover_path' => null,
            'content' => self::policyContent($slug),
        ];
    }

    /** Nội dung HTML mẫu (TipTap) cho từng trang chính sách. */
    private static function policyContent(string $slug): string
    {
        return match ($slug) {
            'chinh-sach-bao-mat' => '<p>BỐP CAMPING tôn trọng và cam kết bảo vệ thông tin cá nhân của khách hàng. Chính sách này giải thích chúng tôi thu thập, sử dụng và bảo vệ dữ liệu của bạn như thế nào.</p>'
                .'<h2>1. Thông tin chúng tôi thu thập</h2>'
                .'<ul><li>Họ tên, số điện thoại và email khi bạn đăng nhập hoặc đặt thuê.</li><li>Địa chỉ giao nhận và thời gian thuê để phục vụ đơn hàng.</li><li>Lịch sử đơn thuê và đánh giá của bạn.</li></ul>'
                .'<h2>2. Mục đích sử dụng</h2>'
                .'<ul><li>Xử lý và giao nhận đơn thuê, liên hệ xác nhận lịch.</li><li>Gửi mã OTP xác thực đăng nhập qua email.</li><li>Chăm sóc khách hàng, xử lý khiếu nại và cải thiện dịch vụ.</li></ul>'
                .'<h2>3. Bảo mật & chia sẻ</h2>'
                .'<p>Thông tin của bạn được lưu trữ an toàn và chỉ dùng nội bộ cho việc vận hành dịch vụ. Chúng tôi <strong>không mua bán, trao đổi</strong> thông tin cá nhân của khách với bên thứ ba, trừ khi có yêu cầu hợp pháp của cơ quan chức năng.</p>'
                .'<h2>4. Quyền của khách hàng</h2>'
                .'<p>Bạn có thể yêu cầu xem, cập nhật hoặc xóa thông tin cá nhân của mình bằng cách liên hệ với chúng tôi qua hotline hoặc Zalo.</p>',
            'dieu-khoan-su-dung' => '<p>Khi truy cập và sử dụng website BỐP CAMPING, bạn đồng ý với các điều khoản dưới đây.</p>'
                .'<h2>1. Dịch vụ</h2>'
                .'<p>BỐP CAMPING cung cấp dịch vụ cho thuê thiết bị cắm trại theo ngày. Giá thuê, tồn kho và tình trạng thiết bị được hiển thị trên website và có thể thay đổi mà không cần báo trước.</p>'
                .'<h2>2. Tài khoản khách hàng</h2>'
                .'<p>Khách đăng nhập bằng số điện thoại, họ tên và email, xác thực qua mã OTP gửi tới email. Bạn chịu trách nhiệm về tính chính xác của thông tin cung cấp và giữ bí mật mã OTP của mình.</p>'
                .'<h2>3. Trách nhiệm khi thuê</h2>'
                .'<ul><li>Sử dụng thiết bị đúng mục đích, giữ gìn và hoàn trả đúng hẹn.</li><li>Bồi thường theo thỏa thuận nếu thiết bị hư hỏng, mất mát do lỗi của người thuê.</li><li>Cung cấp thông tin đặt cọc và giấy tờ (nếu được yêu cầu) khi nhận đồ.</li></ul>'
                .'<h2>4. Sở hữu trí tuệ</h2>'
                .'<p>Toàn bộ nội dung, hình ảnh và thương hiệu trên website thuộc về BỐP CAMPING. Vui lòng không sao chép khi chưa được phép.</p>',
            'chinh-sach-thanh-toan' => '<p>BỐP CAMPING áp dụng hình thức thanh toán đơn giản, minh bạch cho dịch vụ thuê thiết bị cắm trại.</p>'
                .'<h2>1. Hình thức thanh toán</h2>'
                .'<p>Hiện tại chúng tôi nhận <strong>thanh toán khi nhận hàng (COD)</strong>: bạn thanh toán tiền thuê và tiền cọc cho nhân viên giao nhận tại thời điểm nhận thiết bị.</p>'
                .'<h2>2. Tiền cọc</h2>'
                .'<ul><li>Mỗi đơn thuê có khoản tiền cọc theo giá trị thiết bị, hiển thị rõ khi đặt hàng.</li><li>Tiền cọc được <strong>hoàn lại đầy đủ</strong> khi bạn trả thiết bị đúng hẹn và trong tình trạng như lúc nhận.</li><li>Trường hợp thiết bị hư hỏng, thiếu hoặc trả trễ, khoản khấu trừ sẽ được trao đổi và trừ vào tiền cọc.</li></ul>'
                .'<h2>3. Hóa đơn & xác nhận</h2>'
                .'<p>Chi tiết tiền thuê, tiền cọc và thời gian thuê được ghi rõ trong đơn hàng và email/tin nhắn xác nhận. Vui lòng kiểm tra trước khi nhận đồ.</p>',
            'chinh-sach-giao-nhan' => '<p>BỐP CAMPING giao nhận thiết bị tận nơi hoặc hẹn điểm lấy đồ thuận tiện cho khách.</p>'
                .'<h2>1. Khu vực phục vụ</h2>'
                .'<p>Chúng tôi phục vụ tại Vinh và các khu vực lân cận. Với địa điểm xa hơn, vui lòng liên hệ để được báo phí và thời gian giao nhận cụ thể.</p>'
                .'<h2>2. Thời gian giao nhận</h2>'
                .'<ul><li>Thời gian giao/nhận được hẹn theo ngày bắt đầu và kết thúc thuê trong đơn.</li><li>Vui lòng có mặt đúng hẹn để nhận và trả thiết bị; nếu thay đổi lịch, hãy báo trước cho chúng tôi.</li></ul>'
                .'<h2>3. Phí giao nhận</h2>'
                .'<p>Phí giao nhận (nếu có) phụ thuộc vào khoảng cách và được thông báo rõ trước khi bạn xác nhận đơn.</p>'
                .'<h2>4. Kiểm tra khi nhận</h2>'
                .'<p>Khi nhận đồ, vui lòng kiểm tra số lượng và tình trạng thiết bị cùng nhân viên giao nhận để đảm bảo đúng như đơn đặt.</p>',
            'chinh-sach-doi-tra' => '<p>Chúng tôi hiểu kế hoạch dã ngoại có thể thay đổi. Chính sách dưới đây áp dụng cho việc hủy, đổi lịch và trả thiết bị.</p>'
                .'<h2>1. Hủy đơn</h2>'
                .'<ul><li>Bạn có thể hủy đơn miễn phí trước ngày bắt đầu thuê. Vui lòng báo sớm để chúng tôi sắp xếp thiết bị cho khách khác.</li><li>Với đơn đã chuẩn bị/đang trên đường giao, chúng tôi có thể tính một phần chi phí phát sinh — sẽ trao đổi cụ thể với bạn.</li></ul>'
                .'<h2>2. Đổi lịch / đổi thiết bị</h2>'
                .'<p>Bạn có thể yêu cầu đổi ngày thuê hoặc đổi thiết bị tùy theo tình trạng tồn kho tại thời điểm đó. Hãy liên hệ càng sớm càng tốt để chúng tôi hỗ trợ.</p>'
                .'<h2>3. Trả thiết bị</h2>'
                .'<ul><li>Trả thiết bị đúng hẹn và trong tình trạng như lúc nhận để được hoàn cọc đầy đủ.</li><li>Trả trễ có thể phát sinh phí thuê thêm ngày.</li><li>Thiết bị hư hỏng hoặc thiếu sẽ được khấu trừ vào tiền cọc theo thỏa thuận.</li></ul>'
                .'<h2>4. Thiết bị lỗi</h2>'
                .'<p>Nếu thiết bị gặp lỗi không do người thuê gây ra, vui lòng báo ngay cho chúng tôi để được đổi thiết bị khác hoặc xử lý phù hợp.</p>',
            default => '<p>Nội dung đang được cập nhật.</p>',
        };
    }

    /** Template mặc định trang giới thiệu — user sửa lại trong admin. */
    public static function aboutDefaults(): array
    {
        $content = '<p><img src="/images/album/forest-camp-aerial.jpg" alt="Khu cắm trại giữa rừng"></p>'
            .'<h2>Câu chuyện BỐP CAMPING</h2>'
            .'<p>BỐP CAMPING ra đời từ những chuyến đi của chính tụi mình — những lần vác lều lên núi, dựng trại bên bờ biển và nhận ra: không phải ai cũng cần MUA cả bộ đồ cắm trại chỉ để đi vài chuyến mỗi năm. Thuê đúng món, đúng ngày, giá hợp lý — phần còn lại cứ để tụi mình lo.</p>'
            .'<p><img src="/images/album/tent-interior-night.jpg" alt="Không gian trong lều"></p>'
            .'<h3>Đồ sạch – đủ – chuẩn</h3>'
            .'<p>Mỗi món đồ sau chuyến thuê đều được vệ sinh, phơi khô và kiểm tra từng chi tiết trước khi đến tay khách tiếp theo. Lều đủ cọc đủ dây, bếp thử lửa, đèn sạc đầy pin — nhận đồ là đi, không phải kiểm đếm lại.</p>'
            .'<h3>Khu vực phục vụ</h3>'
            .'<p>Tụi mình đang phục vụ tại Vinh và các khu vực lân cận, giao nhận tận nơi hoặc hẹn điểm lấy đồ thuận đường đi của bạn. Khu vực phục vụ sẽ tiếp tục mở rộng — theo dõi fanpage để cập nhật nhé.</p>'
            .'<p><img src="/images/album/cloud-sea-sunrise.jpg" alt="Săn mây bình minh"></p>'
            .'<p><img src="/images/album/beach-night-tent.jpg" alt="Cắm trại biển đêm"></p>'
            .'<p><img src="/images/album/cliff-turquoise.jpg" alt="Vách đá biển xanh"></p>'
            .'<h2>Đi thôi, đồ đã có tụi mình</h2>'
            .'<p>Chọn ngày, chọn đồ, đặt thuê trong vài phút — cọc hoàn lại đầy đủ khi trả đồ. Hẹn gặp bạn ở một khung trời nào đó!</p>';

        return [
            'title' => 'Về BỐP CAMPING — thuê đồ dã ngoại sạch, đủ, chuẩn',
            'cover_path' => null,
            'content' => $content,
        ];
    }
}
