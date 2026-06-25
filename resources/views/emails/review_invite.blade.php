<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đánh giá chuyến đi {{ $order->code }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f1e8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#3a3226;">
    <div style="max-width:520px;margin:0 auto;padding:32px 20px;">
        <div style="background:#fbf9f4;border:1px solid #e6ddc9;border-radius:18px;padding:32px;">
            <div style="font-size:20px;font-weight:800;color:#557a2b;margin-bottom:4px;">BopCamping</div>
            <h1 style="font-size:18px;margin:14px 0 6px;">Chuyến đi vừa rồi thế nào? 🏕</h1>
            <p style="font-size:15px;line-height:1.6;margin:6px 0 0;">
                Cảm ơn <strong>{{ $order->customer_name }}</strong> đã thuê đồ ở BopCamping (đơn
                <strong style="color:#557a2b;">{{ $order->code }}</strong>). Bạn dành chút thời gian
                đánh giá để tụi mình phục vụ tốt hơn nhé — và giúp khách sau chọn đồ dễ hơn.
            </p>

            <div style="margin:16px 0 6px;font-weight:700;font-size:14px;">Bạn đã thuê</div>
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                @foreach ($order->items as $item)
                    <tr style="border-top:1px solid #ece4d2;">
                        <td style="padding:8px 0;">{{ $item->product->name ?? 'Sản phẩm' }}</td>
                        <td style="padding:8px 0;text-align:right;color:#8a8170;">× {{ $item->quantity }}</td>
                    </tr>
                @endforeach
            </table>

            <a href="{{ $reviewUrl }}" style="display:inline-block;margin-top:20px;background:#557a2b;color:#fff;text-decoration:none;font-weight:700;padding:12px 22px;border-radius:11px;font-size:15px;">
                Viết đánh giá ★
            </a>
            <p style="font-size:12px;color:#a39b88;line-height:1.6;margin:14px 0 0;">
                Link riêng cho đơn của bạn, không cần đăng nhập. Đánh giá sẽ hiển thị sau khi được duyệt.
            </p>
        </div>
        <p style="font-size:12px;color:#a39b88;text-align:center;margin-top:18px;">© BopCamping — Cho thuê đồ cắm trại</p>
    </div>
</body>
</html>
