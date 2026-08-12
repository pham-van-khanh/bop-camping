@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $row = fn ($label, $value) => '<tr><td style="padding:4px 0;font-family:'.$mono.';font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#a39b88;">'.$label.'</td><td style="padding:4px 0;text-align:right;font-weight:600;color:#332f26;">'.e($value).'</td></tr>';
@endphp
<x-mail.brand variant="green" :preheader="'Có đơn thuê mới '.$order->code.' đang chờ xác nhận.'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Quản trị · Đơn #{{ $order->code }}</div>
    <h1 style="font-size:20px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Có đơn thuê mới{{ $order->is_parent ? ' — '.$order->children->count().' đợt giao' : '' }}</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">Đơn vừa được đặt và đang chờ xác nhận.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;margin-top:12px;">
        {!! $row('Khách', $order->customer_name) !!}
        {!! $row('SĐT', $order->customer_phone) !!}
        @if ($order->customer_email) {!! $row('Email', $order->customer_email) !!} @endif
        @if ($order->customer_address) {!! $row('Địa chỉ', $order->customer_address) !!} @endif
        {!! $row('Khoảng thuê', $order->start_date->format('d/m/Y').' → '.$order->end_date->format('d/m/Y')) !!}
    </table>

    @if ($order->is_parent)
        {{-- Đơn gộp (bopcamping-wtuv T9): liệt kê thiết bị + tiền theo TỪNG ĐỢT giao. --}}
        @foreach ($order->children as $i => $child)
            <div style="margin:12px 0 0;border:1px solid #e3e8d6;border-radius:12px;padding:10px 14px;">
                <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                    <tr>
                        <td style="font-family:{{ $mono }};font-size:12px;font-weight:700;color:#557a2b;">ĐỢT {{ $i + 1 }} · #{{ $child->code }}</td>
                        <td style="text-align:right;font-family:{{ $mono }};font-size:12.5px;font-weight:700;color:#2e2a20;">{{ $child->start_date->format('d/m/Y') }} → {{ $child->end_date->format('d/m/Y') }}</td>
                    </tr>
                </table>
                <x-mail.item-list :order="$child" :subtotal="true" />
                <div style="text-align:right;font-family:{{ $mono }};font-size:12.5px;color:#5a5445;border-top:1px dashed #e3e8d6;padding-top:6px;">COD đợt này: <strong style="color:#2e2a20;">{{ number_format($child->amount_due, 0, ',', '.') }}đ</strong></div>
            </div>
        @endforeach
    @else
        <div style="margin:14px 0 4px;">
            <x-mail.item-list :order="$order" :subtotal="true" />
        </div>
    @endif

    {{-- Tách THUÊ và CỌC (bopcamping-944h). Trước đây chỉ in mỗi amount_due nên nhìn
         mail admin không biết bao nhiêu là doanh thu, bao nhiêu là tiền giữ hộ phải
         hoàn lại — hai khoản có bản chất khác hẳn nhau. --}}
    <div style="margin:14px 0 0;background:#eef2e3;border-radius:12px;padding:12px 16px;">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
            <tr>
                <td style="padding:2px 0;color:#5a5445;">Tiền thuê{{ $order->is_parent ? ' (cả '.$order->children->count().' đợt)' : '' }}</td>
                <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#2e2a20;">{{ number_format($order->rental_due, 0, ',', '.') }}đ</td>
            </tr>
            <tr>
                <td style="padding:2px 0;color:#5a5445;">Tiền cọc <span style="color:#8a8170;">(hoàn khi trả đồ)</span></td>
                <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#c06a2a;">{{ number_format($order->deposit_total, 0, ',', '.') }}đ</td>
            </tr>
            <tr>
                <td style="padding:8px 0 0;font-weight:800;color:#2e2a20;border-top:1px solid #dbe3c9;">{{ $order->is_parent ? 'Tổng thu' : 'Tổng thu khi giao' }}</td>
                <td style="padding:8px 0 0;text-align:right;font-family:{{ $mono }};font-weight:800;font-size:15px;color:#2e2a20;border-top:1px solid #dbe3c9;">{{ number_format($order->amount_due, 0, ',', '.') }}đ</td>
            </tr>
        </table>
    </div>

    <div style="margin:22px 0 0;">
        <x-mail.button :href="$adminUrl">Mở trang quản trị đơn →</x-mail.button>
    </div>
</x-mail.brand>
