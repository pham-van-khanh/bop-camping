<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BỐP CAMPING phản hồi góp ý của bạn</title>
</head>
<body style="margin:0;padding:0;background:#f5f1e8;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#3a3226;">
    <div style="max-width:540px;margin:0 auto;padding:32px 20px;">
        <div style="background:#fbf9f4;border:1px solid #e6ddc9;border-radius:18px;padding:32px;">
            <div style="font-size:20px;font-weight:800;color:#557a2b;margin-bottom:4px;">BỐP CAMPING</div>

            {{-- Template cố định: chào theo tên + cảm ơn; nội dung chính do admin soạn --}}
            <p style="font-size:15px;line-height:1.65;margin:16px 0 0;">Chào <strong>{{ $feedback->name }}</strong>,</p>
            <p style="font-size:15px;line-height:1.65;margin:10px 0 0;">
                Cảm ơn bạn đã dành thời gian góp ý cho BỐP CAMPING. Tụi mình đã đọc kỹ và xin phản hồi như sau:
            </p>

            <div style="background:#fff;border:1px solid #ece4d2;border-radius:12px;padding:14px 16px;margin-top:14px;font-size:15px;line-height:1.7;white-space:pre-line;">{{ $feedback->reply_content }}</div>

            <div style="margin:20px 0;border-top:1px solid #ece4d2;"></div>

            <div style="font-size:13px;color:#8a8170;line-height:1.6;">
                Góp ý của bạn ({{ $feedback->created_at->format('d/m/Y') }}):
                <div style="margin-top:6px;font-style:italic;white-space:pre-line;">“{{ \Illuminate\Support\Str::limit($feedback->content, 400) }}”</div>
            </div>

            <p style="font-size:15px;line-height:1.65;margin:18px 0 0;">
                Thân mến,<br>
                <strong style="color:#557a2b;">Đội ngũ BỐP CAMPING</strong> 🏕
            </p>
        </div>
        <p style="text-align:center;font-size:12px;color:#a89f8d;margin-top:16px;">Bạn nhận được email này vì đã gửi góp ý trên website BỐP CAMPING.</p>
    </div>
</body>
</html>
