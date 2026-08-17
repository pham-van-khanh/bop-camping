<?php

namespace App\Jobs;

use App\Mail\ContractSignedMail;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Sinh PDF hợp đồng rồi gửi cho khách — chạy NỀN, không nằm trong request bấm "Ký".
 *
 * Lý do phải tách ra khỏi request (đo thật, không phải phòng xa): render một hợp đồng đầy đủ
 * (3 phần + biên bản chứng thực) ngốn ĐỈNH ~75MB. memory_limit mặc định của PHP-FPM là 128M,
 * nên làm inline là đặt cược vào việc VPS còn dư bộ nhớ — mà nếu thua thì khách ăn lỗi 500
 * đúng khoảnh khắc bấm ký, tức là mất luôn niềm tin vào chỗ cần niềm tin nhất.
 *
 * Đổi lại: pdf_path đến sau vài giây. Trang ký đã nói trước "bản PDF sẽ được gửi vào email",
 * nên độ trễ này không làm khách hụt hẫng. CẦN queue worker chạy (composer run dev đã có).
 */
class GenerateContractPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public Contract $contract, public string $stage) {}

    public function handle(ContractService $contracts): void
    {
        $contract = $this->contract->fresh(['order', 'items', 'signatures']);

        // Hợp đồng bị xoá giữa chừng thì job không có việc gì để làm — thoát êm, đừng retry.
        if (! $contract) {
            return;
        }

        $contract->forceFill(['pdf_path' => $contracts->storePdf($contract)])->save();

        if ($email = $contract->order->notifiableEmail()) {
            Mail::to($email)->send(new ContractSignedMail($contract, $this->stage));
        }
    }
}
