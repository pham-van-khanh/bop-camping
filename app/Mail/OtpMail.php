<?php

namespace App\Mail;

use App\Services\Auth\OtpService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Email chứa mã OTP đăng nhập. */
class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Mã đăng nhập BopCamping: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.otp', with: [
            'code' => $this->code,
            'minutes' => OtpService::TTL_MINUTES,
        ]);
    }
}
