<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/** Email báo khách đơn đã được ĐỔI LỊCH thuê (admin đổi) — bopcamping-5hjm. Gửi qua queue. */
class OrderDatesChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public Carbon $oldStart,
        public Carbon $oldEnd,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Đơn '.$this->order->code.' đã đổi lịch thuê — BỐP CAMPING');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_dates_changed', with: [
            'order' => $this->order->loadMissing('items.product'),
            'oldStart' => $this->oldStart,
            'oldEnd' => $this->oldEnd,
        ]);
    }
}
