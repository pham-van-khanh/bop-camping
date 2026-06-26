<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        @php
            // SEO động: lấy từ prop 'seo' của trang (controller/shared), có fallback site-wide.
            $seo = $page['props']['seo'] ?? [];
            $brand = 'BỐP CAMPING';
            $seoTitle = $seo['title'] ?? $brand.' — Cho thuê thiết bị cắm trại';
            $seoDesc = $seo['description'] ?? 'Cho thuê lều, bếp, túi ngủ, đèn trại... theo ngày. Giao nhận tận nơi tại Vinh & Hà Nội, cọc linh hoạt, trả tiền khi nhận (COD).';
            $seoImage = $seo['image'] ?? url('/images/album/forest-camp-aerial.jpg');
            $seoUrl = $seo['url'] ?? url()->current();
        @endphp

        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDesc }}">
        <meta name="keywords" content="thuê đồ cắm trại, cho thuê lều, thuê túi ngủ, thuê bếp dã ngoại, camping Vinh, camping Hà Nội, cắm trại">
        <meta name="author" content="{{ $brand }}">
        <meta name="robots" content="index, follow">
        <meta name="theme-color" content="#557A2B">
        <link rel="canonical" href="{{ $seoUrl }}">

        {{-- Favicon --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="apple-touch-icon" href="/favicon.svg">

        {{-- Open Graph (Facebook, Zalo, Messenger...) --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $brand }}">
        <meta property="og:locale" content="vi_VN">
        <meta property="og:title" content="{{ $seoTitle }}">
        <meta property="og:description" content="{{ $seoDesc }}">
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:url" content="{{ $seoUrl }}">

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $seoTitle }}">
        <meta name="twitter:description" content="{{ $seoDesc }}">
        <meta name="twitter:image" content="{{ $seoImage }}">

        {{-- Dữ liệu có cấu trúc (Google rich results) --}}
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $brand,
            'url' => url('/'),
            'logo' => url('/favicon.svg'),
            'image' => $seoImage,
            'description' => $seoDesc,
            'areaServed' => ['Vinh', 'Hà Nội'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700,800|space-mono:400,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
