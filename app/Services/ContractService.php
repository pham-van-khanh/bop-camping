<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Order;
use App\Models\SiteSetting;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * NGUỒN DUY NHẤT dựng, render và ký hợp đồng thuê điện tử (bopcamping-4jao).
 *
 * Không nơi nào khác được tự render hợp đồng. Có hai chỗ render thì sớm muộn cũng có bản
 * khách ĐỌC khác bản khách KÝ — và đó đúng là thứ tính năng này tồn tại để chặn.
 */
class ContractService
{
    /**
     * Dựng hợp đồng cho đơn. Idempotent — gọi lại chỉ cập nhật danh tính, KHÔNG nhân đôi
     * danh mục đồ (admin bấm nút hai lần là chuyện thường).
     *
     * @param  array{id_number?: ?string, id_issued_on?: ?string, id_issued_place?: ?string}  $identity
     */
    public function createFor(Order $order, array $identity): Contract
    {
        // Đơn CHA chỉ gom đợt, không có ngày/đồ riêng — hợp đồng bám đơn con.
        if ($order->is_parent) {
            throw new InvalidArgumentException('Đơn cha không có hợp đồng riêng — lập hợp đồng trên từng đơn con.');
        }

        if ($existing = $order->contract) {
            $existing->fill($this->identityAttributes($identity))->save();

            return $existing->load('items', 'signatures');
        }

        $contract = Contract::create([
            'order_id' => $order->id,
            'code' => $order->code.'/HĐTTB',
            'token' => Str::random(64),
            ...$this->identityAttributes($identity),
        ]);

        foreach ($order->items()->with('product', 'combo')->get()->values() as $i => $item) {
            ContractItem::create([
                'contract_id' => $contract->id,
                'product_id' => $item->product_id,
                'combo_name' => $item->combo?->name,
                // Sản phẩm bị xoá về sau thì hợp đồng vẫn phải đọc được nguyên vẹn.
                'name' => $item->product?->name ?? '(sản phẩm đã xoá)',
                'parts_list' => $item->product?->parts_list,
                'quantity' => $item->quantity,
                'replacement_value' => (int) ($item->product?->replacement_value ?? 0),
                'sort_order' => $i,
            ]);
        }

        return $contract->load('items', 'signatures');
    }

    public function render(Contract $contract, string $stage): string
    {
        if (! in_array($stage, Contract::STAGES, true)) {
            throw new InvalidArgumentException("Giai đoạn hợp đồng không hợp lệ: {$stage}");
        }

        return strtr($this->templateFor($stage), $this->variables($contract));
    }

    /**
     * Ký một giai đoạn.
     *
     * Đóng băng nội dung + hash TẠI ĐÂY chứ không phải lúc tạo hợp đồng, vì admin sửa mẫu
     * được ở giữa chừng.
     *
     * $expectedHash là hash của bản khách ĐANG ĐỌC trên màn hình. Lệch nghĩa là nội dung vừa
     * đổi giữa lúc khách mở trang và lúc bấm ký → từ chối, bắt tải lại. Nhờ vậy không ai ký
     * được thứ mình chưa đọc.
     *
     * @throws RuntimeException giai đoạn đã ký, chưa tới lượt, hoặc biên bản còn thiếu ô
     * @throws DomainException hash lệch, hoặc chữ ký không phải PNG hợp lệ
     */
    public function sign(Contract $contract, string $stage, string $signaturePng, string $expectedHash, Request $request): void
    {
        if ($contract->nextStage() !== $stage) {
            throw new RuntimeException('Giai đoạn này đã ký hoặc chưa tới lượt ký.');
        }

        // Phụ lục A/B chỉ ký được khi ĐỦ tình trạng mọi món — biên bản thiếu ô là biên bản
        // vô dụng đúng lúc cần đối chiếu để trừ cọc.
        $conditionField = match ($stage) {
            'handover' => 'handover_condition',
            'return' => 'return_condition',
            default => null,
        };

        if ($conditionField !== null && $contract->items->contains(fn ($i) => $i->{$conditionField} === null)) {
            throw new RuntimeException('Còn thiết bị chưa ghi nhận tình trạng — không ký được biên bản thiếu ô.');
        }

        $html = $this->render($contract, $stage);
        $hash = hash('sha256', $html);

        if (! hash_equals($hash, $expectedHash)) {
            throw new DomainException('Nội dung hợp đồng vừa thay đổi. Hãy tải lại trang và đọc lại trước khi ký.');
        }

        // Giải mã TRƯỚC khi ghi gì vào DB: chữ ký hỏng thì không được để lại bản ghi nửa vời.
        $binary = $this->decodePng($signaturePng);

        $path = "contracts/{$contract->id}/{$stage}.png";
        Storage::disk('media')->put($path, $binary);

        $contract->signatures()->create([
            'stage' => $stage,
            'content_html' => $html,
            'content_hash' => $hash,
            'signature_path' => $path,
            'signed_at' => now(),
            'signed_ip' => $request->ip(),
            'signed_user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }

    /**
     * data URL PNG -> binary. Từ chối mọi thứ không phải PNG.
     *
     * Không tin client: trường này là chuỗi tự do gửi từ trình duyệt, nhận bừa là cho phép
     * nhồi file bất kỳ vào disk media dưới cái tên .png.
     */
    private function decodePng(string $dataUrl): string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            throw new DomainException('Chữ ký không hợp lệ.');
        }

        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);

        // Kiểm cả magic bytes, không chỉ tin cái tiền tố do client tự khai.
        if ($binary === false || ! str_starts_with($binary, "\x89PNG\x0d\x0a\x1a\x0a")) {
            throw new DomainException('Chữ ký không hợp lệ.');
        }

        return $binary;
    }

    /**
     * Bảng biến thay vào mẫu.
     *
     * MỌI giá trị đến từ dữ liệu khách nhập đều đi qua e() — hợp đồng là HTML, tên khách có
     * ký tự '<' mà không escape là chèn được thẻ vào giấy tờ pháp lý (CWE-79).
     *
     * @return array<string, string>
     */
    private function variables(Contract $contract): array
    {
        $order = $contract->order;

        return [
            '{{so_hop_dong}}' => e($contract->code),
            '{{ma_don}}' => e($order->code),
            '{{ten_khach}}' => e($order->customer_name),
            '{{sdt_khach}}' => e($order->customer_phone),
            '{{dia_chi_khach}}' => e($order->customer_address ?? '.....................'),
            '{{cccd_khach}}' => e($contract->signer_id_number ?? '.....................'),
            '{{ngay_cap}}' => $contract->signer_id_issued_on?->format('d/m/Y') ?? '..../..../........',
            '{{noi_cap}}' => e($contract->signer_id_issued_place ?? '.....................'),
            '{{ngay_nhan}}' => $order->start_date?->format('d/m/Y') ?? '',
            '{{ngay_tra}}' => $order->end_date?->format('d/m/Y') ?? '',
            '{{so_ngay_thue}}' => (string) $this->rentalDays($order),
            '{{tong_tien}}' => $this->money($order->total_price),
            '{{tien_coc}}' => $this->money($order->deposit_total),
            '{{bang_thiet_bi}}' => $this->equipmentTable($contract),
            '{{bang_ban_giao}}' => $this->conditionTable($contract, 'handover'),
            '{{bang_nhan_lai}}' => $this->conditionTable($contract, 'return'),
            '{{bang_quyet_toan}}' => $this->settlementTable($contract),
        ];
    }

    private function rentalDays(Order $order): int
    {
        if (! $order->start_date || ! $order->end_date) {
            return 0;
        }

        return (int) $order->start_date->diffInDays($order->end_date) + 1;
    }

    /** Bảng Điều 1 — CÓ cột giá trị đền bù, thứ hợp đồng giấy đang thiếu. */
    private function equipmentTable(Contract $contract): string
    {
        $rows = '';
        foreach ($contract->items as $i => $item) {
            $rows .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                $i + 1,
                e($item->name),
                $item->quantity,
                // 0 = chưa khai giá. In "—" chứ KHÔNG in "0 đ": số 0 dễ bị đọc thành "đền
                // 0 đồng", tức là tự tay bỏ mất căn cứ đòi bồi thường.
                $item->replacement_value > 0 ? $this->money($item->replacement_value) : '—',
            );
        }

        return '<table><thead><tr><th>STT</th><th>Tên thiết bị</th><th>SL</th>'
            .'<th>Giá trị đền bù (VNĐ)</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    private function conditionTable(Contract $contract, string $stage): string
    {
        $labels = $stage === 'handover' ? ContractItem::HANDOVER_LABELS : ContractItem::RETURN_LABELS;
        $field = $stage === 'handover' ? 'handover_condition' : 'return_condition';
        $noteField = $stage === 'handover' ? 'handover_note' : 'return_note';

        $rows = '';
        foreach ($contract->items as $i => $item) {
            $rows .= sprintf(
                '<tr><td>%d</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>',
                $i + 1,
                e($item->name),
                e($item->parts_list ?? ''),
                $item->quantity,
                e($labels[$item->{$field}] ?? '(chưa ghi nhận)'),
                e($item->{$noteField} ?? ''),
            );
        }

        return '<table><thead><tr><th>STT</th><th>Tên thiết bị</th><th>Phụ kiện</th>'
            .'<th>SL</th><th>Tình trạng</th><th>Ghi chú</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * Bảng quyết toán của Phụ lục B — số lấy từ ĐƠN, cố ý không để admin gõ tay vào editor.
     * Số trên biên bản mà lệch số trong hệ thống thì biên bản thành vô dụng lúc đối chiếu.
     */
    private function settlementTable(Contract $contract): string
    {
        $order = $contract->order;
        $deposit = (int) $order->deposit_total;
        $fee = (int) ($order->extra_fee ?? 0);
        // Không bao giờ âm: phụ phí vượt cọc thì phần vượt khách trả thêm, không phải shop
        // "hoàn âm" — dòng đó tính riêng ở Điều 3.3.
        $refund = max(0, $deposit - $fee);

        $rows = [
            'Tiền đặt cọc đã thu' => $this->money($deposit),
            'Phí phạt trễ / bồi thường hư hỏng, mất mát' => $this->money($fee),
            'Số tiền hoàn lại cho Bên B' => $this->money($refund),
        ];

        $html = '';
        foreach ($rows as $label => $value) {
            $html .= sprintf('<tr><td>%s</td><td>%s</td></tr>', e($label), $value);
        }

        return '<table><thead><tr><th>Nội dung</th><th>Số tiền (VNĐ)</th></tr></thead><tbody>'
            .$html.'</tbody></table>';
    }

    private function templateFor(string $stage): string
    {
        $column = match ($stage) {
            'main' => 'contract_template_html',
            'handover' => 'handover_template_html',
            'return' => 'return_template_html',
        };

        return SiteSetting::current()->{$column} ?: $this->defaultTemplate($stage);
    }

    /** Mẫu mặc định khi admin chưa soạn — bám nguyên văn hợp đồng giấy 1408/HĐTTB. */
    public function defaultTemplate(string $stage): string
    {
        return view("contracts.defaults.{$stage}")->render();
    }

    /** @param  array{id_number?: ?string, id_issued_on?: ?string, id_issued_place?: ?string}  $identity */
    private function identityAttributes(array $identity): array
    {
        return array_filter([
            'signer_id_number' => $identity['id_number'] ?? null,
            'signer_id_issued_on' => $identity['id_issued_on'] ?? null,
            'signer_id_issued_place' => $identity['id_issued_place'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function money(?int $amount): string
    {
        return number_format((int) $amount, 0, ',', '.').' đ';
    }
}
