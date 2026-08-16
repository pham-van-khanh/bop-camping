@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
@endphp
<x-mail.brand variant="green" :preheader="'Bản hợp đồng đã ký của đơn '.$order->code.' — file PDF đính kèm.'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Đơn #{{ $order->code }}</div>
    <h1 style="font-size:21px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Đã ký xong: {{ $stageLabel }}</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">
        Cảm ơn {{ $order->customer_name }}. Bản hợp đồng đã ký được đính kèm trong email này dưới dạng PDF.
        <strong>Bạn hãy giữ lại email này</strong> — nó là bản lưu độc lập của bạn.
    </p>

    <table role="presentation" width="100%" style="margin:18px 0 6px;border-collapse:collapse;font-size:13px;color:#5a5445;">
        <tr>
            <td style="padding:6px 0;color:#a39b88;">Số hợp đồng</td>
            <td style="padding:6px 0;text-align:right;">{{ $contract->code }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:#a39b88;">Thời điểm ký</td>
            <td style="padding:6px 0;text-align:right;">{{ $signedAt }}</td>
        </tr>
        @if ($contentHash)
            <tr>
                <td style="padding:6px 0;color:#a39b88;vertical-align:top;">Mã kiểm tra nội dung</td>
                <td style="padding:6px 0;text-align:right;font-family:{{ $mono }};font-size:10px;word-break:break-all;">{{ $contentHash }}</td>
            </tr>
        @endif
    </table>

    <p style="font-size:12px;color:#a39b88;line-height:1.6;margin:14px 0 0;">
        Mã kiểm tra là dấu vân tay của đúng nội dung bạn đã đọc lúc ký. Nếu nội dung bị sửa dù chỉ một ký tự,
        mã này sẽ khác hoàn toàn — nên bạn luôn đối chiếu được bản mình giữ với bản của shop.
    </p>
</x-mail.brand>
