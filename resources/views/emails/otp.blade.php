<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mã đăng nhập BopCamping</title>
</head>
<body style="margin:0;padding:0;background:#f5f1e8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#3a3226;">
    <div style="max-width:480px;margin:0 auto;padding:32px 20px;">
        <div style="background:#fbf9f4;border:1px solid #e6ddc9;border-radius:18px;padding:32px;">
            <div style="font-size:20px;font-weight:800;color:#557a2b;margin-bottom:4px;">BopCamping</div>
            <p style="font-size:15px;line-height:1.6;margin:16px 0 8px;">
                Đây là mã đăng nhập của bạn. Nhập mã này để tiếp tục:
            </p>
            <div style="font-size:34px;font-weight:800;letter-spacing:10px;color:#3a3226;background:#eef2e3;border-radius:12px;text-align:center;padding:18px 0;margin:18px 0;">
                {{ $code }}
            </div>
            <p style="font-size:13px;color:#8a8170;line-height:1.6;margin:8px 0 0;">
                Mã có hiệu lực trong {{ $minutes }} phút và chỉ dùng được một lần.
                Nếu bạn không yêu cầu mã này, hãy bỏ qua email.
            </p>
        </div>
        <p style="font-size:12px;color:#a39b88;text-align:center;margin-top:18px;">
            © BopCamping — Cho thuê đồ cắm trại
        </p>
    </div>
</body>
</html>
