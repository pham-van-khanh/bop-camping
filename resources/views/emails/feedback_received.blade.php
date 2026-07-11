<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Góp ý mới</title>
</head>
<body style="margin:0;padding:0;background:#f5f1e8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#3a3226;">
    <div style="max-width:540px;margin:0 auto;padding:32px 20px;">
        <div style="background:#fbf9f4;border:1px solid #e6ddc9;border-radius:18px;padding:32px;">
            <div style="font-size:20px;font-weight:800;color:#557a2b;margin-bottom:4px;">BopCamping · Quản trị</div>
            <h1 style="font-size:18px;margin:14px 0 6px;">💬 Có góp ý mới từ khách</h1>

            <div style="margin:20px 0;border-top:1px solid #ece4d2;"></div>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <tr><td style="padding:4px 0;color:#8a8170;">Khách</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $feedback->name }}</td></tr>
                @if ($feedback->phone)
                    <tr><td style="padding:4px 0;color:#8a8170;">SĐT</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $feedback->phone }}</td></tr>
                @endif
                @if ($feedback->email)
                    <tr><td style="padding:4px 0;color:#8a8170;">Email</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $feedback->email }}</td></tr>
                @endif
                <tr><td style="padding:4px 0;color:#8a8170;">Lúc</td><td style="padding:4px 0;text-align:right;font-weight:600;">{{ $feedback->created_at->format('H:i d/m/Y') }}</td></tr>
            </table>

            <div style="margin:16px 0 6px;font-weight:700;font-size:14px;">Nội dung góp ý</div>
            <div style="background:#fff;border:1px solid #ece4d2;border-radius:12px;padding:14px 16px;font-size:14px;line-height:1.65;white-space:pre-line;">{{ $feedback->content }}</div>

            <a href="{{ $adminUrl }}" style="display:block;margin-top:22px;background:#557a2b;color:#fff;text-decoration:none;text-align:center;padding:13px 18px;border-radius:12px;font-weight:700;font-size:15px;">
                Mở trang quản trị góp ý →
            </a>
        </div>
        <p style="text-align:center;font-size:12px;color:#a89f8d;margin-top:16px;">Email tự động từ hệ thống BopCamping.</p>
    </div>
</body>
</html>
