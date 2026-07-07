<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * FAQ suy từ nội dung site thật (COD, cọc, thuê theo ngày, OTP email, combo,
 * giao Vinh/Hà Nội, tra cứu đơn, đánh giá). Idempotent qua updateOrCreate theo câu hỏi.
 */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            [
                'question' => 'Thuê đồ ở BỐP CAMPING như thế nào?',
                'answer' => 'Bạn chọn thiết bị và ngày nhận – ngày trả, hệ thống hiện luôn món nào còn trống trong khoảng đó. Để lại tên và số điện thoại, tụi mình gọi xác nhận trong 15–30 phút, rồi giao tận nơi. Không cần trả trước — trả tiền khi nhận đồ.',
            ],
            [
                'question' => 'Có phải trả tiền trước không?',
                'answer' => 'Không. BỐP CAMPING thanh toán COD — bạn trả tiền thuê và tiền cọc khi nhận đồ, không cần chuyển khoản trước.',
            ],
            [
                'question' => 'Tiền cọc có được hoàn lại không?',
                'answer' => 'Có. Tiền cọc được hoàn đầy đủ khi bạn trả đồ đúng hẹn và còn nguyên vẹn.',
            ],
            [
                'question' => 'Giá thuê tính theo ngày như thế nào?',
                'answer' => 'Giá tính theo số ngày giữa ngày nhận và ngày trả. Hệ thống tự kiểm tra trùng lịch và tồn kho theo đúng khoảng ngày bạn chọn, nên bạn luôn thấy món còn thuê được.',
            ],
            [
                'question' => 'BỐP CAMPING giao nhận ở đâu?',
                'answer' => 'Tụi mình giao nhận tận nơi nội thành Vinh (Nghệ An) và Hà Nội. Miễn phí giao nội thành cho đơn từ 300.000đ.',
            ],
            [
                'question' => 'Đặt đồ có cần tạo tài khoản không?',
                'answer' => 'Không cần tài khoản rườm rà. Chỉ cần số điện thoại và tên là đặt được. Nếu thêm email, lần đầu bạn nhập mã OTP 6 số gửi qua email để xác thực; các lần sau vào thẳng. Thêm email còn được ưu đãi cho đơn đầu tiên.',
            ],
            [
                'question' => 'Combo là gì và có lợi gì?',
                'answer' => 'Combo là bộ thiết bị gói sẵn (ví dụ lều + bàn + ghế) với giá rẻ hơn thuê lẻ từng món. Xem các bộ tiết kiệm ở mục Combo trên trang chủ.',
            ],
            [
                'question' => 'Có voucher hay ưu đãi gì không?',
                'answer' => 'Có voucher, mã giới thiệu bạn bè, và ưu đãi cho đơn đầu tiên khi bạn bổ sung email. Áp mã ngay ở bước đặt đơn.',
            ],
            [
                'question' => 'Làm sao tra cứu đơn đã đặt?',
                'answer' => 'Vào mục "Tra cứu đơn", nhập mã đơn và số điện thoại đã đặt là xem được tình trạng đơn.',
            ],
            [
                'question' => 'Sau khi trả đồ có đánh giá được không?',
                'answer' => 'Được. Sau chuyến đi tụi mình gửi link để bạn đánh giá thiết bị và dịch vụ. Đánh giá sẽ được duyệt trước khi hiển thị công khai.',
            ],
        ];

        foreach ($faqs as $i => $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                ['answer' => $faq['answer'], 'sort_order' => $i + 1, 'is_active' => true],
            );
        }
    }
}
