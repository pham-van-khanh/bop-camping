<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận đơn thuê {{ $order->code }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f1e8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#3a3226;">
    <div style="max-width:560px;margin:0 auto;padding:32px 20px;">
        <div style="background:#fbf9f4;border:1px solid #e6ddc9;border-radius:18px;padding:32px;">
            <div style="font-size:20px;font-weight:800;color:#557a2b;margin-bottom:4px;">BopCamping</div>
            <h1 style="font-size:18px;margin:14px 0 4px;">Đã nhận đơn thuê của bạn 🎒</h1>
            <p style="font-size:15px;line-height:1.6;margin:6px 0 0;">
                Cảm ơn <strong>{{ $order->customer_name }}</strong>! Đơn
                <strong style="color:#557a2b;">{{ $order->code }}</strong> đã được ghi nhận và đang chờ shop xác nhận.
                Bạn trả tiền khi nhận đồ (COD).
            </p>

            <div style="margin:20px 0;border-top:1px solid #ece4d2;"></div>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:4px 0;color:#8a8170;">Ngày nhận</td>
                    <td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->start_date->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:#8a8170;">Ngày trả</td>
                    <td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->end_date->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:#8a8170;">Số điện thoại</td>
                    <td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->customer_phone }}</td>
                </tr>
            </table>

            <div style="margin:18px 0 8px;font-weight:700;font-size:14px;">Thiết bị thuê</div>
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                @foreach ($order->items as $item)
                    <tr style="border-top:1px solid #ece4d2;">
                        <td style="padding:8px 0;">
                            {{ $item->product->name ?? 'Sản phẩm' }}
                            <span style="color:#8a8170;">× {{ $item->quantity }} · {{ $item->days }} ngày</span>
                        </td>
                        <td style="padding:8px 0;text-align:right;font-weight:600;">{{ number_format($item->subtotal, 0, ',', '.') }}đ</td>
                    </tr>
                @endforeach
            </table>

            <div style="margin:18px 0 0;background:#eef2e3;border-radius:12px;padding:14px 16px;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;">
                    <tr>
                        <td style="padding:3px 0;color:#3a5a1f;">Tiền thuê</td>
                        <td style="padding:3px 0;text-align:right;">{{ number_format($order->total_price, 0, ',', '.') }}đ</td>
                    </tr>
                    @if ($order->discount_total > 0)
                        <tr>
                            <td style="padding:3px 0;color:#3a5a1f;">Giảm giá</td>
                            <td style="padding:3px 0;text-align:right;">−{{ number_format($order->discount_total, 0, ',', '.') }}đ</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:3px 0;color:#3a5a1f;">Tiền cọc</td>
                        <td style="padding:3px 0;text-align:right;">{{ number_format($order->deposit_total, 0, ',', '.') }}đ</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 0 0;font-weight:800;border-top:1px solid #d9e2c4;">Trả khi nhận</td>
                        <td style="padding:8px 0 0;text-align:right;font-weight:800;border-top:1px solid #d9e2c4;">{{ number_format($order->amount_due, 0, ',', '.') }}đ</td>
                    </tr>
                </table>
            </div>

            <p style="font-size:13px;color:#8a8170;line-height:1.6;margin:18px 0 0;">
                Theo dõi đơn bất cứ lúc nào tại trang <strong>Tra cứu đơn</strong> với mã {{ $order->code }} và số điện thoại.
            </p>
        </div>
        <p style="font-size:12px;color:#a39b88;text-align:center;margin-top:18px;">
            © BopCamping — Cho thuê đồ cắm trại
        </p>
    </div>
</body>
</html>
