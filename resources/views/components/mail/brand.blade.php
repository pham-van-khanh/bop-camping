@props([
    // 'green' (xác nhận/mặc định) · 'orange' (nhắc lịch) · 'muted' (huỷ/trung tính)
    'variant' => 'green',
    // Header kiểu "hero": eyebrow nhỏ + tiêu đề lớn căn giữa (dùng cho mail nhắc lịch).
    // Bỏ trống => header kiểu thương hiệu (logo + BỐP CAMPING).
    'eyebrow' => null,
    'heading' => null,
    // Dòng preheader ẩn (hiện ở preview inbox).
    'preheader' => null,
])
@php
    $headers = [
        'green' => ['bg' => '#3f6a24', 'grad' => 'linear-gradient(135deg,#3f6a24,#6f9a3f)'],
        'orange' => ['bg' => '#c47a2c', 'grad' => 'linear-gradient(135deg,#c07327,#dd9a4a)'],
        'muted' => ['bg' => '#6b6357', 'grad' => 'linear-gradient(135deg,#6b6357,#8a8170)'],
    ];
    $h = $headers[$variant] ?? $headers['green'];

    $site = \App\Models\SiteSetting::current();
    $hotline = $site->hotline_primary;
    $areas = \App\Models\ServiceLocation::open()->pluck('area')->filter()->unique()->implode(' & ');
    if ($areas === '') {
        $areas = 'Vinh & Hà Nội';
    }
    $mono = "'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace";
    $isHero = filled($heading);
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>{{ $heading ?? 'BỐP CAMPING' }}</title>
</head>
<body style="margin:0;padding:0;background:#f1ede1;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#332f26;-webkit-font-smoothing:antialiased;">
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">{{ $preheader }}</div>
    @endif
    <div style="max-width:560px;margin:0 auto;padding:28px 16px;">
        <div style="background:#fbf9f4;border:1px solid #ece3cf;border-radius:20px;overflow:hidden;">

            {{-- Header --}}
            <div style="background:{{ $h['bg'] }};background:{{ $h['grad'] }};padding:{{ $isHero ? '30px 28px' : '24px 28px' }};text-align:{{ $isHero ? 'center' : 'left' }};">
                @if ($isHero)
                    @if ($eyebrow)
                        <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.82);margin-bottom:8px;">{{ $eyebrow }}</div>
                    @endif
                    <div style="font-size:26px;font-weight:800;color:#ffffff;line-height:1.2;">{{ $heading }}</div>
                @else
                    <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                        <tr>
                            <td style="vertical-align:middle;">
                                <img src="{{ url('/images/logo-128.png') }}" width="34" height="34" alt="Bốp Camping" style="display:block;width:34px;height:34px;border-radius:50%;">
                            </td>
                            <td style="vertical-align:middle;padding-left:10px;">
                                <div style="font-size:17px;font-weight:800;letter-spacing:.5px;color:#ffffff;">BỐP CAMPING</div>
                            </td>
                        </tr>
                    </table>
                @endif
            </div>

            {{-- Body --}}
            <div style="padding:26px 28px 28px;">
                {{ $slot }}
            </div>

            {{-- Footer --}}
            <div style="border-top:1px solid #efe7d5;padding:18px 28px;text-align:center;background:#f8f4ea;">
                @if ($hotline)
                    <div style="font-family:{{ $mono }};font-size:14px;font-weight:700;color:#557a2b;letter-spacing:1px;">{{ $hotline }}</div>
                @endif
                <div style="font-family:{{ $mono }};font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#a39b88;margin-top:6px;">BỐP CAMPING · {{ $areas }}</div>
            </div>
        </div>
        <div style="text-align:center;font-size:11px;color:#b3ab98;margin-top:14px;">Cho thuê thiết bị cắm trại</div>
    </div>
</body>
</html>
