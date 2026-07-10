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
