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
        return new Envelope(subject: '🛎 Đơn mới '.$this->order->code.' — '.$this->order->customer_name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new_order_admin', with: [
            'order' => $this->order->loadMissing('items.product'),
            'adminUrl' => url('/admin/orders'),
        ]);
    }
}
