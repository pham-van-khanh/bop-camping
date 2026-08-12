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

{{-- Tiền: THUÊ và CỌC là hai khoản tách bạch (bopcamping-944h).

     Cọc được hoàn lại khi trả đồ nguyên vẹn, tiền thuê thì không — gộp chung một con số
     khiến khách tưởng phải trả hết ngần ấy. Kết lại bằng dòng tổng vì đây là đơn COD,
     khách cần biết chính xác cầm bao nhiêu tiền mặt.

     Tiền thuê in ra là `rental_due` (đã cộng phí phát sinh, đã trừ giảm giá) chứ không
     phải `total_price` thô: admin nhập phí giao ở extra_fee sau khi gọi xác nhận, dùng
     total_price thì mail nhắc lịch báo thiếu tiền so với lúc giao. --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
    <tr>
        <td style="padding:3px 0;color:#5a5445;">Tiền thuê ({{ $order->days }} ngày)</td>
        <td style="padding:3px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#2e2a20;">{{ number_format($order->total_price, 0, ',', '.') }}đ</td>
    </tr>
    @if ($order->extra_fee > 0)
        <tr>
            <td style="padding:3px 0;color:#5a5445;">{{ $order->extra_fee_note ?: 'Phí phát sinh' }}</td>
            <td style="padding:3px 0;text-align:right;font-family:{{ $mono }};color:#2e2a20;">+{{ number_format($order->extra_fee, 0, ',', '.') }}đ</td>
        </tr>
    @endif
    @if ($order->discount_total > 0)
        <tr>
            <td style="padding:3px 0;color:#5a5445;">Giảm giá</td>
            <td style="padding:3px 0;text-align:right;font-family:{{ $mono }};color:#557a2b;">−{{ number_format($order->discount_total, 0, ',', '.') }}đ</td>
        </tr>
    @endif
    <tr>
        <td style="padding:3px 0;color:#5a5445;border-top:1px solid #ece3cf;padding-top:7px;">Tiền cọc <span style="color:#8a8170;">(hoàn lại khi trả đồ)</span></td>
        <td style="padding:3px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#c06a2a;border-top:1px solid #ece3cf;padding-top:7px;">{{ number_format($order->deposit_total, 0, ',', '.') }}đ</td>
    </tr>
    <tr>
        <td style="padding:8px 0 0;font-weight:800;color:#2e2a20;">Tổng cầm khi nhận đồ</td>
        <td style="padding:8px 0 0;text-align:right;font-family:{{ $mono }};font-weight:800;font-size:15px;color:#2e2a20;">{{ number_format($order->amount_due, 0, ',', '.') }}đ</td>
    </tr>
</table>
