<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Gửi bản hợp đồng đã ký (PDF) cho khách ngay sau mỗi lần ký (bopcamping-4jao).
 *
 * Đây KHÔNG phải mail xã giao — nó là TRỤ CHỨNG CỨ CHÍNH của cả tính năng: bản PDF nằm trong
 * hộp thư khách, trên server Google/Microsoft, có header DKIM chứng minh xuất phát từ tên
 * miền shop đúng thời điểm đó, và shop KHÔNG sửa được. Nhờ vậy lập luận "bên cho thuê tự
 * dựng ra hết" không còn đứng được (adr_contract_esignature mục 3.2).
 *
 * Mã kiểm tra (hash) in trong thân mail để sau này đối chiếu được bản khách giữ với bản shop
 * giữ mà không cần mở file.
 */
class ContractSignedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Contract $contract, public string $stage) {}

    public function envelope(): Envelope
    {
        $label = Contract::STAGE_LABELS[$this->stage] ?? 'Hợp đồng';

        return new Envelope(
            subject: "Đã ký: {$label} — đơn {$this->contract->order->code}"
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contract_signed', with: [
            'order' => $this->contract->order,
            'contract' => $this->contract,
            'stageLabel' => Contract::STAGE_LABELS[$this->stage] ?? 'Hợp đồng',
            'contentHash' => $this->contract->signatureFor($this->stage)?->content_hash,
            'signedAt' => $this->contract->signatureFor($this->stage)?->signed_at?->format('H:i d/m/Y'),
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        $path = $this->contract->pdf_path;

        // Không có file thì gửi mail không đính kèm còn hơn ném lỗi và mất luôn cả mail.
        if (! $path || ! Storage::disk('media')->exists($path)) {
            return [];
        }

        return [
            Attachment::fromData(
                fn () => Storage::disk('media')->get($path),
                "hop-dong-{$this->contract->order->code}.pdf"
            )->withMime('application/pdf'),
        ];
    }
}
