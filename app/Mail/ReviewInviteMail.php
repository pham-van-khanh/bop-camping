<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Mail mời đánh giá sau khi trả đồ (KE_HOACH 8.2). Gửi qua queue. */
class ReviewInviteMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Chuyến đi thế nào? Đánh giá giúp tụi mình nhé — '.$this->order->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.review_invite', with: [
            'order' => $this->order->loadMissing('items.product'),
            'reviewUrl' => url('/danh-gia/'.$this->order->review_token),
        ]);
    }
}
