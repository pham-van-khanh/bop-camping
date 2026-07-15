@props(['href' => '#'])
{{-- Nút CTA chính (xanh, full-width) — dùng chung mọi email. --}}
<a href="{{ $href }}" style="display:block;background:#4d7327;color:#ffffff;text-decoration:none;text-align:center;font-size:15px;font-weight:700;padding:14px 20px;border-radius:12px;">{{ $slot }}</a>
