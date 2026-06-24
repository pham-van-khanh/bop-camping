<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email thông báo khi đơn đổi trạng thái (confirmed / returned / cancelled) — KE_HOACH 8.1. */
class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    /** Nội dung theo từng trạng thái: tiêu đề + lời nhắn. */
    public const TEMPLATES = [
        'confirmed' => [
            'subject' => 'đã được xác nhận',
            'heading' => 'Đơn của bạn đã được xác nhận ✅',
            'message' => 'Shop đã xác nhận đơn thuê. Tụi mình sẽ giao đúng hẹn vào ngày nhận. Nhớ chuẩn bị tiền mặt (thuê + cọc) khi nhận đồ.',
        ],
        'returned' => [
            'subject' => 'đã hoàn tất',
            'heading' => 'Đã nhận lại đồ — cảm ơn bạn! 🙌',
            'message' => 'Tụi mình đã nhận lại đầy đủ thiết bị và hoàn cọc. Cảm ơn bạn đã thuê đồ ở BopCamping, hẹn gặp lại chuyến sau!',
        ],
        'cancelled' => [
            'subject' => 'đã được huỷ',
            'heading' => 'Đơn của bạn đã được huỷ',
            'message' => 'Đơn thuê này đã được huỷ. Nếu đây là nhầm lẫn hoặc bạn cần hỗ trợ, vui lòng liên hệ shop nhé.',
        ],
    ];

    public function __construct(public Order $order, public string $status) {}

    /** Trạng thái có gửi mail không. */
    public static function notifies(string $status): bool
    {
        return array_key_exists($status, self::TEMPLATES);
    }

    public function envelope(): Envelope
    {
        $label = self::TEMPLATES[$this->status]['subject'] ?? 'cập nhật';

        return new Envelope(subject: 'Đơn '.$this->order->code.' '.$label.' — BopCamping');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_status', with: [
            'order' => $this->order->loadMissing('items.product'),
            'tpl' => self::TEMPLATES[$this->status],
        ]);
    }
}
