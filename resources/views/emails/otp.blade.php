@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
@endphp
<x-mail.brand variant="green" preheader="Mã đăng nhập BỐP CAMPING của bạn.">
    <h1 style="font-size:20px;line-height:1.3;margin:0 0 6px;color:#2e2a20;">Mã đăng nhập của bạn</h1>
    <p style="font-size:14.5px;line-height:1.65;margin:0;color:#5a5445;">Nhập mã bên dưới để tiếp tục đăng nhập:</p>

    <div style="font-family:{{ $mono }};font-size:34px;font-weight:800;letter-spacing:10px;color:#2e2a20;background:#eef2e3;border-radius:14px;text-align:center;padding:20px 0;margin:18px 0;">{{ $code }}</div>

    <p style="font-size:13px;color:#8a8170;line-height:1.6;margin:0;">
        Mã có hiệu lực trong {{ $minutes }} phút và chỉ dùng được một lần. Nếu bạn không yêu cầu mã này, hãy bỏ qua email.
    </p>
</x-mail.brand>
