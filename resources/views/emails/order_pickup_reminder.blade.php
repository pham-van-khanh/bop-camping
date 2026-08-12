@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $fmtDay = fn ($d) => \Illuminate\Support\Str::ucfirst($d->locale('vi')->isoFormat('dddd, DD/MM/YYYY'));
@endphp
<x-mail.brand variant="orange" eyebrow="Sắp đến ngày nhận đồ" heading="Còn 1 ngày nữa!"
    :preheader="'Ngày mai ('.$order->start_date->format('d/m').($order->confirmed_pickup_time ? ' lúc '.$order->confirmed_pickup_time : '').') nhận đồ cho đơn '.$order->code.'.'">
    <p style="font-size:14.5px;line-height:1.65;margin:0 0 2px;color:#5a5445;">
        Chào {{ $order->customer_name }}, ngày mai tụi mình sẽ giao đồ cho đơn
        <strong style="font-family:{{ $mono }};color:#c06a2a;">#{{ $order->code }}</strong>. Kiểm tra lại thông tin bên dưới giúp tụi mình nhé.
    </p>

    {{-- Thẻ NHẬN ĐỒ (nền vàng nhạt) — hiện giờ đã chốt nếu có, chưa chốt thì hẹn liên hệ trước --}}
    <div style="margin:16px 0;background:#f6efd8;border-radius:14px;padding:16px 18px;">
        <div style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#a5843f;">Nhận đồ</div>
        <div style="font-size:15px;font-weight:800;color:#2e2a20;margin-top:4px;">
            {{ $fmtDay($order->start_date) }}
            @if ($order->confirmed_pickup_time)
                — Giao lúc <strong>{{ $order->confirmed_pickup_time }}</strong>
            @endif
        </div>
        @if ($order->customer_address)
            <div style="font-size:14px;color:#5a5445;margin-top:4px;">{{ $order->customer_address }}</div>
        @endif
        @unless ($order->confirmed_pickup_time)
            <div style="font-size:12.5px;color:#a5843f;margin-top:6px;">Tụi mình sẽ liên hệ trước khi giao để hẹn giờ.</div>
        @endunless
    </div>

    <div style="margin:6px 0 4px;">
        <x-mail.item-list :order="$order" />
    </div>

    {{-- Mail này gửi sát ngày nhận nên phải nói ĐỦ số tiền mặt cần cầm, tách rõ khoản
         nào mất khoản nào được hoàn (bopcamping-944h). Trước đây chỉ nhắc mỗi tiền cọc
         nên khách chuẩn bị thiếu tiền thuê.

         Dùng rental_due chứ không phải total_price: tới lúc này admin đã gọi xác nhận và
         có thể đã nhập phí giao vào extra_fee — in total_price là báo thiếu. --}}
    <div style="margin:14px 0 4px;border-top:1px solid #efe7d5;padding-top:14px;">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
            <tr>
                <td style="padding:2px 0;color:#5a5445;">Tiền thuê</td>
                <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#2e2a20;white-space:nowrap;">{{ number_format($order->rental_due, 0, ',', '.') }}đ</td>
            </tr>
            <tr>
                <td style="padding:2px 0;color:#5a5445;">Tiền cọc <span style="color:#8a8170;">(hoàn khi trả đồ)</span></td>
                <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#c06a2a;white-space:nowrap;">{{ number_format($order->deposit_total, 0, ',', '.') }}đ</td>
            </tr>
            <tr>
                <td style="padding:8px 0 0;font-weight:800;color:#2e2a20;border-top:1px solid #efe7d5;">Chuẩn bị sẵn</td>
                <td style="padding:8px 0 0;text-align:right;font-family:{{ $mono }};font-weight:800;font-size:15px;color:#2e2a20;white-space:nowrap;border-top:1px solid #efe7d5;">{{ number_format($order->amount_due, 0, ',', '.') }}đ (COD)</td>
            </tr>
        </table>
    </div>

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="route('lookup')">Xem chi tiết đơn</x-mail.button>
    </div>
    <p style="text-align:center;margin:0;font-size:13px;">
        <a href="{{ route('lookup') }}" style="color:#557a2b;font-weight:600;text-decoration:none;">Cần đổi giờ giao? Liên hệ tụi mình</a>
    </p>
</x-mail.brand>
