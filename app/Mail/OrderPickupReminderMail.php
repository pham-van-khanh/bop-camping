<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email nhắc khách sắp đến ngày nhận đồ (gửi trước 1 ngày) — bopcamping-sdy8.
 * Do command SendPickupReminders gửi qua queue cho đơn confirmed.
 */
class OrderPickupReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mai nhận đồ rồi nhé — đơn '.$this->order->code.' · BỐP CAMPING');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.order_pickup_reminder', with: [
            'order' => $this->order->loadMissing('items.product'),
        ]);
    }
}
