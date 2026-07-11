<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Báo QTV có góp ý mới từ khách (Epic 2). Gửi qua queue. */
class FeedbackReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Feedback $feedback) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '💬 Góp ý mới từ '.$this->feedback->name);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.feedback_received', with: [
            'feedback' => $this->feedback,
            'adminUrl' => url('/admin/gop-y'),
        ]);
    }
}
