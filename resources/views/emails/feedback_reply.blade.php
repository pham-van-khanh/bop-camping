@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
@endphp
<x-mail.brand variant="green" preheader="BỐP CAMPING phản hồi góp ý của bạn.">
    <h1 style="font-size:20px;line-height:1.3;margin:0 0 8px;color:#2e2a20;">Chào {{ $feedback->name }},</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">
        Cảm ơn bạn đã dành thời gian góp ý cho BỐP CAMPING. Tụi mình đã đọc kỹ và xin phản hồi như sau:
    </p>

    <div style="background:#ffffff;border:1px solid #ece4d2;border-radius:12px;padding:14px 16px;margin-top:14px;font-size:14.5px;line-height:1.7;white-space:pre-line;color:#332f26;">{{ $feedback->reply_content }}</div>

    <div style="margin:18px 0 0;font-size:13px;color:#8a8170;line-height:1.6;">
        <span style="font-family:{{ $mono }};font-size:10.5px;letter-spacing:1.5px;text-transform:uppercase;color:#a39b88;">Góp ý của bạn · {{ $feedback->created_at->format('d/m/Y') }}</span>
        <div style="margin-top:6px;font-style:italic;white-space:pre-line;">“{{ \Illuminate\Support\Str::limit($feedback->content, 400) }}”</div>
    </div>

    <p style="font-size:14.5px;line-height:1.65;margin:18px 0 0;color:#5a5445;">
        Thân mến,<br>
        <strong style="color:#557a2b;">Đội ngũ BỐP CAMPING</strong>
    </p>
</x-mail.brand>
