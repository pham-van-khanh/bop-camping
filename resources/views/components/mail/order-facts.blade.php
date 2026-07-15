@props(['order'])
@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $fmtDay = fn ($d) => \Illuminate\Support\Str::ucfirst($d->locale('vi')->isoFormat('dddd, DD/MM/YYYY'));
@endphp
{{-- Thẻ ngày nhận / trả + giao nhận --}}
<div style="margin:16px 0;background:#eef2e3;border-radius:14px;padding:16px 18px;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="width:50%;vertical-align:top;">
                <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#7d8a63;">Ngày nhận</div>
                <div style="font-size:14px;font-weight:700;color:#2e2a20;margin-top:3px;">{{ $fmtDay($order->start_date) }}</div>
            </td>
            <td style="width:50%;vertical-align:top;">
                <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#7d8a63;">Ngày trả</div>
                <div style="font-size:14px;font-weight:700;color:#2e2a20;margin-top:3px;">{{ $fmtDay($order->end_date) }}</div>
            </td>
        </tr>
        @if ($order->customer_address)
            <tr>
                <td colspan="2" style="padding-top:12px;">
                    <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#7d8a63;">Giao nhận</div>
                    <div style="font-size:14px;color:#2e2a20;margin-top:3px;">{{ $order->customer_address }}</div>
                </td>
            </tr>
        @endif
    </table>
</div>

{{-- Tổng tiền --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
    <tr>
        <td style="padding:3px 0;color:#5a5445;">Tổng tiền thuê ({{ $order->days }} ngày)</td>
        <td style="padding:3px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#2e2a20;">{{ number_format($order->total_price, 0, ',', '.') }}đ</td>
    </tr>
    @if ($order->discount_total > 0)
        <tr>
            <td style="padding:3px 0;color:#5a5445;">Giảm giá</td>
            <td style="padding:3px 0;text-align:right;font-family:{{ $mono }};color:#557a2b;">−{{ number_format($order->discount_total, 0, ',', '.') }}đ</td>
        </tr>
    @endif
    <tr>
        <td style="padding:3px 0;color:#5a5445;">Tiền cọc (thu khi nhận)</td>
        <td style="padding:3px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#c06a2a;">{{ number_format($order->deposit_total, 0, ',', '.') }}đ</td>
    </tr>
</table>
