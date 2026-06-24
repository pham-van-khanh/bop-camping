<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn mới {{ $order->code }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f1e8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#3a3226;">
    <div style="max-width:540px;margin:0 auto;padding:32px 20px;">
        <div style="background:#fbf9f4;border:1px solid #e6ddc9;border-radius:18px;padding:32px;">
            <div style="font-size:20px;font-weight:800;color:#557a2b;margin-bottom:4px;">BopCamping · Quản trị</div>
            <h1 style="font-size:18px;margin:14px 0 6px;">🛎 Có đơn thuê mới</h1>
            <p style="font-size:15px;line-height:1.6;margin:6px 0 0;">
                Đơn <strong style="color:#557a2b;">{{ $order->code }}</strong> vừa được đặt và đang chờ xác nhận.
            </p>

            <div style="margin:20px 0;border-top:1px solid #ece4d2;"></div>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr><td style="padding:4px 0;color:#8a8170;">Khách</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->customer_name }}</td></tr>
                <tr><td style="padding:4px 0;color:#8a8170;">SĐT</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->customer_phone }}</td></tr>
                @if ($order->customer_email)
                    <tr><td style="padding:4px 0;color:#8a8170;">Email</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->customer_email }}</td></tr>
                @endif
                @if ($order->customer_address)
                    <tr><td style="padding:4px 0;color:#8a8170;">Địa chỉ</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->customer_address }}</td></tr>
                @endif
                <tr><td style="padding:4px 0;color:#8a8170;">Khoảng thuê</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $order->start_date->format('d/m/Y') }} → {{ $order->end_date->format('d/m/Y') }}</td></tr>
            </table>

            <div style="margin:16px 0 6px;font-weight:700;font-size:14px;">Thiết bị</div>
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                @foreach ($order->items as $item)
                    <tr style="border-top:1px solid #ece4d2;">
                        <td style="padding:8px 0;">{{ $item->product->name ?? 'Sản phẩm' }} <span style="color:#8a8170;">× {{ $item->quantity }} · {{ $item->days }} ngày</span></td>
                        <td style="padding:8px 0;text-align:right;font-weight:600;">{{ number_format($item->subtotal, 0, ',', '.') }}đ</td>
                    </tr>
                @endforeach
            </table>

            <div style="margin:16px 0 0;background:#eef2e3;border-radius:12px;padding:12px 16px;display:flex;justify-content:space-between;">
                <span style="font-weight:800;">Trả khi nhận</span>
                <span style="font-weight:800;">{{ number_format($order->amount_due, 0, ',', '.') }}đ</span>
            </div>

            <a href="{{ $adminUrl }}" style="display:inline-block;margin-top:18px;background:#557a2b;color:#fff;text-decoration:none;font-weight:700;padding:11px 20px;border-radius:10px;font-size:14px;">
                Mở trang quản trị đơn →
            </a>
        </div>
        <p style="font-size:12px;color:#a39b88;text-align:center;margin-top:18px;">© BopCamping — Quản trị đơn thuê</p>
    </div>
</body>
</html>
