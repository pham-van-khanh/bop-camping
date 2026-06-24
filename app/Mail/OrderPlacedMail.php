<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email xác nhận đặt đơn thành công (COD) — KE_HOACH 8.1. Gửi qua queue. */
class OrderPlacedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Đã nhận đơn thuê '.$this->order->code.' — BopCamping');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_placed', with: [
            'order' => $this->order->loadMissing('items.product'),
        ]);
    }
}
