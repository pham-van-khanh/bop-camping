<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tpl['heading'] }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f1e8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#3a3226;">
    <div style="max-width:520px;margin:0 auto;padding:32px 20px;">
        <div style="background:#fbf9f4;border:1px solid #e6ddc9;border-radius:18px;padding:32px;">
            <div style="font-size:20px;font-weight:800;color:#557a2b;margin-bottom:4px;">BopCamping</div>
            <h1 style="font-size:18px;margin:14px 0 6px;">{{ $tpl['heading'] }}</h1>
            <p style="font-size:15px;line-height:1.6;margin:6px 0 0;">
                Xin chào <strong>{{ $order->customer_name }}</strong>, đơn
                <strong style="color:#557a2b;">{{ $order->code }}</strong>: {{ $tpl['message'] }}
            </p>

            <div style="margin:20px 0;border-top:1px solid #ece4d2;"></div>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr>
                    <td style="padding:4px 0;color:#8a8170;">Khoảng thuê</td>
                    <td style="padding:4px 0;text-align:right;font-weight:600;">
                        {{ $order->start_date->format('d/m/Y') }} → {{ $order->end_date->format('d/m/Y') }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:#8a8170;">Trả khi nhận</td>
                    <td style="padding:4px 0;text-align:right;font-weight:600;">{{ number_format($order->amount_due, 0, ',', '.') }}đ</td>
                </tr>
            </table>

            <p style="font-size:13px;color:#8a8170;line-height:1.6;margin:18px 0 0;">
                Xem chi tiết tại trang <strong>Tra cứu đơn</strong> với mã {{ $order->code }} và số điện thoại.
            </p>
        </div>
        <p style="font-size:12px;color:#a39b88;text-align:center;margin-top:18px;">
            © BopCamping — Cho thuê đồ cắm trại
        </p>
    </div>
</body>
</html>
