@php
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $row = fn ($label, $value) => '<tr><td style="padding:4px 0;font-family:'.$mono.';font-size:11px;letter-spacing:1px;text-transform:uppercase;color:#a39b88;">'.$label.'</td><td style="padding:4px 0;text-align:right;font-weight:600;color:#332f26;">'.e($value).'</td></tr>';
@endphp
<x-mail.brand variant="green" preheader="Có góp ý mới từ khách.">
    <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c06a2a;">Quản trị</div>
    <h1 style="font-size:20px;line-height:1.3;margin:8px 0 6px;color:#2e2a20;">Có góp ý mới từ khách</h1>

    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px;margin-top:12px;">
        {!! $row('Khách', $feedback->name) !!}
        @if ($feedback->phone) {!! $row('SĐT', $feedback->phone) !!} @endif
        @if ($feedback->email) {!! $row('Email', $feedback->email) !!} @endif
        {!! $row('Lúc', $feedback->created_at->format('H:i d/m/Y')) !!}
    </table>

    <div style="margin:16px 0 6px;font-weight:700;font-size:13px;color:#5a5445;">Nội dung góp ý</div>
    <div style="background:#ffffff;border:1px solid #ece4d2;border-radius:12px;padding:14px 16px;font-size:14px;line-height:1.65;white-space:pre-line;color:#332f26;">{{ $feedback->content }}</div>

    <div style="margin:22px 0 0;">
        <x-mail.button :href="$adminUrl">Mở trang quản trị góp ý →</x-mail.button>
    </div>
</x-mail.brand>
