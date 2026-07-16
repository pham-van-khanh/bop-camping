@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
@endphp
<x-mail.brand variant="green" :preheader="'Tụi mình đã nhận đơn '.$order->code.' — đang chờ xác nhận.'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Đơn thuê mới</div>
    <h1 style="font-size:21px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Cảm ơn {{ $order->customer_name }}, tụi mình đã nhận đơn!</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">
        Đơn <strong style="font-family:{{ $mono }};color:#c06a2a;">#{{ $order->code }}</strong> đã được ghi nhận và đang chờ shop xác nhận. Bạn trả tiền khi nhận đồ (COD).
    </p>

    <div style="display:inline-block;margin:16px 0 4px;background:#fdeede;color:#c06a2a;font-family:{{ $mono }};font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:5px 12px;border-radius:999px;">● Chờ xác nhận</div>

    <div style="margin:10px 0 4px;">
        <x-mail.item-list :order="$order" :per-day="true" />
    </div>

    <x-mail.order-facts :order="$order" />

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="route('lookup')">Xem chi tiết đơn</x-mail.button>
    </div>
    <p style="text-align:center;margin:0;font-size:13px;">
        <a href="{{ route('lookup') }}" style="color:#557a2b;font-weight:600;text-decoration:none;">Cần đổi lịch? Liên hệ tụi mình</a>
    </p>
</x-mail.brand>
