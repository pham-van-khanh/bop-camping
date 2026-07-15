@props([
    'order',
    // Hiện giá/ngày dưới tên món.
    'perDay' => false,
    // Hiện thành tiền căn phải (kiểu hoá đơn).
    'subtotal' => false,
])
@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    // Màu vuông fallback khi món không có ảnh (xoay vòng cho đỡ đơn điệu).
    $swatch = ['linear-gradient(135deg,#5a7d33,#7ba045)', 'linear-gradient(135deg,#b8863f,#d0a95c)', 'linear-gradient(135deg,#4f6b57,#6f8f78)'];
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
    @foreach ($order->items as $i => $item)
        @php
            $product = $item->product;
            $thumb = $product && $product->thumbnail ? \Illuminate\Support\Facades\Storage::disk('media')->url($product->thumbnail) : null;
        @endphp
        <tr>
            <td style="width:48px;padding:8px 0;vertical-align:middle;">
                @if ($thumb)
                    <img src="{{ $thumb }}" width="44" height="44" alt="" style="width:44px;height:44px;border-radius:10px;object-fit:cover;display:block;">
                @else
                    <div style="width:44px;height:44px;border-radius:10px;background:{{ $swatch[$i % count($swatch)] }};"></div>
                @endif
            </td>
            <td style="padding:8px 0 8px 12px;vertical-align:middle;">
                <div style="font-size:14px;font-weight:700;color:#332f26;">{{ $product->name ?? 'Sản phẩm' }}</div>
                @if ($perDay)
                    <div style="font-family:{{ $mono }};font-size:12px;color:#8a8170;margin-top:2px;">Số lượng: {{ $item->quantity }} · {{ number_format((int) ($item->days ? $item->subtotal / max(1, $item->days) / max(1, $item->quantity) : $item->subtotal), 0, ',', '.') }}đ/ngày</div>
                @else
                    <div style="font-family:{{ $mono }};font-size:12px;color:#8a8170;margin-top:2px;">×{{ $item->quantity }}</div>
                @endif
            </td>
            @if ($subtotal)
                <td style="padding:8px 0;text-align:right;vertical-align:middle;font-family:{{ $mono }};font-size:13px;font-weight:700;color:#332f26;white-space:nowrap;">{{ number_format($item->subtotal, 0, ',', '.') }}đ</td>
            @endif
        </tr>
    @endforeach
</table>
