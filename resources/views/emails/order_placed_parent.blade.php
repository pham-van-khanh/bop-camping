@php
    /** Mail xác nhận ĐƠN GỘP (bopcamping-wtuv T9): 1 email cấp CHA liệt kê từng đợt giao. */
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $vnd = fn ($n) => number_format((int) $n, 0, ',', '.').'đ';
    $fmtDay = fn ($d) => $d->format('d/m/Y');
    $children = $order->children;
@endphp
<x-mail.brand variant="green" :preheader="'Tụi mình đã nhận đơn '.$order->code.' gồm '.$children->count().' đợt giao.'">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Đơn thuê mới · {{ $children->count() }} đợt giao</div>
    <h1 style="font-size:21px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Cảm ơn {{ $order->customer_name }}, tụi mình đã nhận đơn!</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">
        Đơn <strong style="font-family:{{ $mono }};color:#c06a2a;">#{{ $order->code }}</strong> gồm <strong>{{ $children->count() }} đợt giao</strong> theo các khoảng ngày bạn chọn.
        Mỗi đợt tụi mình sẽ giao – nhận riêng; bạn trả tiền mặt (COD) theo từng đợt khi nhận đồ.
    </p>

    <div style="display:inline-block;margin:16px 0 4px;background:#fdeede;color:#c06a2a;font-family:{{ $mono }};font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;padding:5px 12px;border-radius:999px;">● Chờ xác nhận</div>

    {{-- Từng đợt giao --}}
    @foreach ($children as $i => $child)
        <div style="margin:16px 0 0;border:1px solid #e3e8d6;border-radius:14px;padding:14px 16px;">
            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">
                <tr>
                    <td>
                        <span style="display:inline-block;background:#557a2b;color:#fff;font-family:{{ $mono }};font-size:10.5px;font-weight:700;letter-spacing:1px;padding:3px 10px;border-radius:999px;">ĐỢT {{ $i + 1 }}</span>
                        <span style="font-family:{{ $mono }};font-size:12.5px;font-weight:700;color:#557a2b;margin-left:6px;">#{{ $child->code }}</span>
                    </td>
                    <td style="text-align:right;font-family:{{ $mono }};font-size:13px;font-weight:700;color:#2e2a20;">{{ $fmtDay($child->start_date) }} → {{ $fmtDay($child->end_date) }}</td>
                </tr>
            </table>

            <div style="margin:6px 0 2px;">
                <x-mail.item-list :order="$child" :per-day="true" />
            </div>

            <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13.5px;border-top:1px dashed #e3e8d6;">
                <tr>
                    <td style="padding:6px 0 2px;color:#5a5445;">Tiền thuê ({{ $child->days }} ngày)</td>
                    <td style="padding:6px 0 2px;text-align:right;font-family:{{ $mono }};font-weight:700;color:#2e2a20;">{{ $vnd($child->total_price) }}</td>
                </tr>
                @if ($child->discount_total > 0)
                    <tr>
                        <td style="padding:2px 0;color:#5a5445;">Giảm (phân bổ từ voucher đơn gộp)</td>
                        <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};color:#557a2b;">−{{ $vnd($child->discount_total) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:2px 0;color:#5a5445;">Tiền cọc (hoàn khi trả)</td>
                    <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};color:#c06a2a;">{{ $vnd($child->deposit_total) }}</td>
                </tr>
                <tr>
                    <td style="padding:6px 0 0;font-weight:800;color:#2e2a20;">COD đợt này</td>
                    <td style="padding:6px 0 0;text-align:right;font-family:{{ $mono }};font-weight:800;color:#2e2a20;">{{ $vnd($child->amount_due) }}</td>
                </tr>
            </table>
        </div>
    @endforeach

    {{-- Tổng cả cụm --}}
    <div style="margin:18px 0 4px;background:#eef2e3;border-radius:14px;padding:14px 18px;">
        <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;">
            <tr>
                <td style="padding:2px 0;color:#5a5445;">Tổng tiền thuê ({{ $children->count() }} đợt)</td>
                <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#2e2a20;">{{ $vnd($order->total_price) }}</td>
            </tr>
            @if ($order->discount_total > 0)
                <tr>
                    <td style="padding:2px 0;color:#5a5445;">Giảm giá (voucher tính trên tổng đơn)</td>
                    <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};color:#557a2b;">−{{ $vnd($order->discount_total) }}</td>
                </tr>
            @endif
            <tr>
                <td style="padding:2px 0;color:#5a5445;">Tổng tiền cọc</td>
                <td style="padding:2px 0;text-align:right;font-family:{{ $mono }};font-weight:700;color:#c06a2a;">{{ $vnd($order->deposit_total) }}</td>
            </tr>
            <tr>
                <td style="padding:8px 0 0;font-weight:800;color:#2e2a20;">Tổng thanh toán (cả cụm)</td>
                <td style="padding:8px 0 0;text-align:right;font-family:{{ $mono }};font-weight:800;color:#2e2a20;">{{ $vnd($order->amount_due) }}</td>
            </tr>
        </table>
    </div>

    <div style="margin:22px 0 12px;">
        <x-mail.button :href="route('lookup')">Xem chi tiết đơn</x-mail.button>
    </div>
    <p style="text-align:center;margin:0;font-size:13px;">
        <a href="{{ route('lookup') }}" style="color:#557a2b;font-weight:600;text-decoration:none;">Cần đổi lịch? Liên hệ tụi mình</a>
    </p>
</x-mail.brand>
