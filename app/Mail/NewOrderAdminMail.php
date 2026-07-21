<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email báo QTV có đơn thuê mới. Gửi qua queue. */
class NewOrderAdminMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $suffix = $this->order->is_parent ? ' ('.$this->order->children()->count().' đợt)' : '';

        return new Envelope(subject: '🛎 Đơn mới '.$this->order->code.$suffix.' — '.$this->order->customer_name);
    }

    public function content(): Content
    {
        // Đơn gộp: nạp con để view liệt kê từng đợt (bopcamping-wtuv T9).
        $this->order->loadMissing($this->order->is_parent ? 'children.items.product' : 'items.product');

        return new Content(view: 'emails.new_order_admin', with: [
            'order' => $this->order,
            'adminUrl' => url('/admin/orders'),
        ]);
    }
}
