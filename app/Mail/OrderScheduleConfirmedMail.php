<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email báo khách giờ giao/thu đã được SHOP CHỐT hoặc đổi (bopcamping-641t). Gửi qua queue. */
class OrderScheduleConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Đơn '.$this->order->code.' — đã chốt giờ giao/thu — BỐP CAMPING');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_schedule_confirmed', with: [
            'order' => $this->order->loadMissing('items.product'),
        ]);
    }
}
