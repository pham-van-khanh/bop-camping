@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
@endphp
<x-mail.brand variant="green" :preheader="'Đánh giá chuyến đi cho đơn '.$order->code.' nhé!'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Đơn #{{ $order->code }}</div>
    <h1 style="font-size:21px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Chuyến đi vừa rồi thế nào?</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">
        Cảm ơn {{ $order->customer_name }} đã thuê đồ ở BỐP CAMPING. Bạn dành chút thời gian đánh giá để tụi mình phục vụ tốt hơn — và giúp khách sau chọn đồ dễ hơn nhé.
    </p>

    <div style="margin:16px 0 4px;">
        <x-mail.item-list :order="$order" />
    </div>

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="$reviewUrl">Viết đánh giá ★</x-mail.button>
    </div>
    <p style="text-align:center;font-size:12px;color:#a39b88;line-height:1.6;margin:0;">
        Link riêng cho đơn của bạn, không cần đăng nhập.
    </p>
</x-mail.brand>
