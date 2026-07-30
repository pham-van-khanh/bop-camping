@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $fmtDay = fn ($d) => \Illuminate\Support\Str::ucfirst($d->locale('vi')->isoFormat('dddd, DD/MM/YYYY'));
    $fmtTime = fn (?string $t) => $t ?: 'sẽ liên hệ';
@endphp
<x-mail.brand variant="green" :preheader="'Đơn '.$order->code.' đã chốt giờ giao '.$fmtTime($order->confirmed_pickup_time).'.'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Đơn #{{ $order->code }}</div>
    <h1 style="font-size:21px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Giờ giao/thu đã được chốt</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">
        Chào {{ $order->customer_name }}, tụi mình đã chốt giờ giao và thu đồ cho đơn thuê của bạn như sau — nếu có gì chưa tiện, liên hệ tụi mình ngay nhé.
    </p>

    {{-- Giờ giao / giờ thu đã chốt --}}
    <div style="margin:16px 0 4px;background:#f6efd8;border-radius:14px;padding:14px 18px;">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="width:50%;vertical-align:top;">
                    <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#a5843f;">Giao</div>
                    <div style="font-size:14px;font-weight:700;color:#2e2a20;margin-top:3px;">{{ $fmtDay($order->start_date) }}</div>
                    <div style="font-family:{{ $mono }};font-size:15px;font-weight:700;color:#557a2b;margin-top:2px;">{{ $fmtTime($order->confirmed_pickup_time) }}</div>
                </td>
                <td style="width:50%;vertical-align:top;">
                    <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#a5843f;">Thu</div>
                    <div style="font-size:14px;font-weight:700;color:#2e2a20;margin-top:3px;">{{ $fmtDay($order->end_date) }}</div>
                    <div style="font-family:{{ $mono }};font-size:15px;font-weight:700;color:#557a2b;margin-top:2px;">{{ $fmtTime($order->confirmed_return_time) }}</div>
                </td>
            </tr>
        </table>
        @if ($order->customer_address)
            <div style="margin-top:12px;">
                <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#a5843f;">Địa chỉ giao nhận</div>
                <div style="font-size:14px;color:#2e2a20;margin-top:3px;">{{ $order->customer_address }}</div>
            </div>
        @endif
    </div>

    <x-mail.order-facts :order="$order" />

    <div style="margin:10px 0 4px;">
        <x-mail.item-list :order="$order" />
    </div>

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="route('lookup')">Xem chi tiết đơn</x-mail.button>
    </div>
    <p style="text-align:center;margin:0;font-size:13px;">
        <a href="{{ route('lookup') }}" style="color:#557a2b;font-weight:600;text-decoration:none;">Cần đổi giờ? Liên hệ tụi mình</a>
    </p>
</x-mail.brand>
