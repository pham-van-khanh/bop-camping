@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $fmt = fn ($d) => $d->format('d/m/Y');
@endphp
<x-mail.brand variant="green" :preheader="'Đơn '.$order->code.' đã đổi lịch: nhận '.$fmt($order->start_date).'.'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Đơn #{{ $order->code }}</div>
    <h1 style="font-size:21px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Lịch thuê của bạn đã được cập nhật</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">
        Chào {{ $order->customer_name }}, tụi mình đã đổi lịch đơn thuê theo trao đổi. Lịch mới như sau — nếu có gì chưa đúng, liên hệ tụi mình ngay nhé.
    </p>

    {{-- Lịch cũ → lịch mới --}}
    <div style="margin:16px 0 4px;background:#f6efd8;border-radius:14px;padding:14px 18px;">
        <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#a5843f;">Lịch cũ</div>
        <div style="font-size:13.5px;color:#8a8170;margin-top:3px;text-decoration:line-through;">{{ $fmt($oldStart) }} → {{ $fmt($oldEnd) }}</div>
    </div>

    <x-mail.order-facts :order="$order" />

    <div style="margin:10px 0 4px;">
        <x-mail.item-list :order="$order" />
    </div>

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="route('lookup')">Xem chi tiết đơn</x-mail.button>
    </div>
    <p style="text-align:center;margin:0;font-size:13px;">
        <a href="{{ route('lookup') }}" style="color:#557a2b;font-weight:600;text-decoration:none;">Cần đổi lại? Liên hệ tụi mình</a>
    </p>
</x-mail.brand>
