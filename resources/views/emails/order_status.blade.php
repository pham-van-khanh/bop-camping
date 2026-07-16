@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    // confirmed/returned = xanh, cancelled = trung tính.
    $variant = $order->status === 'cancelled' ? 'muted' : 'green';
    $isConfirmed = $order->status === 'confirmed';
    $pill = [
        'confirmed' => ['t' => '● Đã xác nhận', 'bg' => '#dcebc4', 'c' => '#3a5a1f'],
        'returned' => ['t' => '● Đã hoàn tất', 'bg' => '#dcebc4', 'c' => '#3a5a1f'],
        'cancelled' => ['t' => '● Đã huỷ', 'bg' => '#efe7e3', 'c' => '#8a6d63'],
    ][$order->status] ?? null;
@endphp
<x-mail.brand :variant="$variant" :preheader="'Đơn '.$order->code.' '.($tpl['subject'] ?? 'cập nhật').'.'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Đơn #{{ $order->code }}</div>
    <h1 style="font-size:21px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">{{ $tpl['heading'] }}</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">{{ $tpl['message'] }}</p>

    @if ($pill)
        <div style="display:inline-block;margin:16px 0 4px;background:{{ $pill['bg'] }};color:{{ $pill['c'] }};font-family:{{ $mono }};font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:5px 12px;border-radius:999px;">{{ $pill['t'] }}</div>
    @endif

    <div style="margin:10px 0 4px;">
        <x-mail.item-list :order="$order" :per-day="$isConfirmed" />
    </div>

    @if ($isConfirmed)
        <x-mail.order-facts :order="$order" />
    @endif

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="route('lookup')">Xem chi tiết đơn</x-mail.button>
    </div>
    <p style="text-align:center;margin:0;font-size:13px;">
        <a href="{{ route('lookup') }}" style="color:#557a2b;font-weight:600;text-decoration:none;">Cần hỗ trợ? Liên hệ tụi mình</a>
    </p>
</x-mail.brand>
