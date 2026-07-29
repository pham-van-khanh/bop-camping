<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Email lịch giao/thu trong ngày gửi cho SHIPPER (bopcamping-5r5m). Gửi qua queue.
 *
 * Đây là mail NỘI BỘ cho nhân sự nên có ghi chú shipper, SĐT và địa chỉ khách, số tiền
 * cần thu — khác hẳn mail gửi khách. Chỉ gửi tới email của chính shipper đó.
 *
 * @param  list<array<string,mixed>>  $pickups
 * @param  list<array<string,mixed>>  $returns
 */
class ShipperScheduleMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $shipper,
        public Carbon $date,
        public array $pickups,
        public array $returns,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Lịch giao/thu ngày '.$this->date->format('d/m/Y').' — BỐP CAMPING',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.shipper_schedule', with: [
            'shipper' => $this->shipper,
            'date' => $this->date,
            'pickups' => $this->pickups,
            'returns' => $this->returns,
        ]);
    }
}
