<?php

namespace App\Mail;

use App\Models\Feedback;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Phản hồi góp ý gửi tới khách (Epic 2). Gửi qua queue, bằng mailer phản hồi
 * cấu hình trong .env (MAIL_REPLY_* — xem config/mail.php). Template cố định
 * chào theo tên khách; phần nội dung do admin soạn ($feedback->reply_content).
 */
class FeedbackReplyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Feedback $feedback) {}

    public function envelope(): Envelope
    {
        // From riêng cho mail phản hồi nếu .env có khai, fallback from mặc định.
        $address = config('mail.reply_from.address') ?: config('mail.from.address');
        $name = config('mail.reply_from.name') ?: config('mail.from.name');

        return new Envelope(
            from: new Address($address, $name),
            subject: 'BỐP CAMPING phản hồi góp ý của bạn',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.feedback_reply', with: [
            'feedback' => $this->feedback,
        ]);
    }
}
