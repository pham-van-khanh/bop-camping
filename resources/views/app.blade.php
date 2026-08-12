<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
        @if (config('services.facebook.domain_verification'))
            <meta name="facebook-domain-verification" content="{{ config('services.facebook.domain_verification') }}">
        @endif

        @php
            // SEO động: lấy từ prop 'seo' của trang (controller/shared), có fallback site-wide.
            $seo = $page['props']['seo'] ?? [];
            // SEO site-wide (không bị controller ghi đè): GA4, Google verification, LocalBusiness.
            $seoSite = $page['props']['seoSite'] ?? [];
            $brand = 'BỐP CAMPING';
            $seoTitle = $seo['title'] ?? $brand.' — Cho thuê thiết bị cắm trại';
            $seoDesc = $seo['description'] ?? 'Cho thuê lều, bếp, túi ngủ, đèn trại... theo ngày. Giao nhận tận nơi tại Vinh & Hà Nội, cọc linh hoạt, trả tiền khi nhận (COD).';
            $seoImage = $seo['image'] ?? url('/images/album/forest-camp-aerial.jpg');
            $seoUrl = $seo['url'] ?? url()->current();
        @endphp

        @if (! empty($seoSite['google_verification']))
            <meta name="google-site-verification" content="{{ $seoSite['google_verification'] }}">
        @endif

        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDesc }}">
        <meta name="keywords" content="thuê đồ cắm trại, cho thuê lều, thuê túi ngủ, thuê bếp dã ngoại, camping Vinh, camping Hà Nội, cắm trại">
        <meta name="author" content="{{ $brand }}">
        <meta name="robots" content="index, follow">
        <meta name="theme-color" content="#557A2B">
        <meta name="thumbnail" content="{{ $seoImage }}">
        <link rel="canonical" href="{{ $seoUrl }}">

        {{-- Preload ảnh hero (LCP) — tải sớm để trang hiện nhanh hơn (tốt cho SEO tốc độ) --}}
        <link rel="preload" as="image" href="/images/album/beach-night-tent.jpg" fetchpriority="high">

        {{-- Favicon --}}
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
        <link rel="apple-touch-icon" href="/favicon-180.png">

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

        {{-- Organization + WebSite (ô tìm kiếm sitelinks).

             TUYỆT ĐỐI không viết mảng có khoá '@context' thẳng trong file blade: Laravel
             11+ có directive @context nên compiler biến chuỗi đó thành mã PHP, key JSON-LD
             ra thành "<?php $__contextArgs = [] ...". Đo trên production 11/08: Organization,
             WebSite, FAQPage đều mất @context nên Google không đọc được khối nào.
             Dựng sẵn ở SeoService::siteJsonLd() rồi chỉ in ra ở đây. --}}
        @foreach ($seoSite['site_jsonld'] ?? [] as $block)
            <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endforeach

        {{-- FAQPage KHÔNG khai ở đây (bopcamping-s5ct): markup phải ứng với FAQ nhìn
             thấy được trên từng trang, mà FAQ chỉ có ở trang chủ. Để ở layout chung thì
             trang sản phẩm/chính sách cũng khai FAQ dù không có câu nào — Google coi là
             structured data không khớp nội dung. Giờ trang chủ tự đưa vào seo.jsonld
             qua SeoService::faqPage(), sinh từ chính dữ liệu đang render. --}}

        {{-- JSON-LD riêng theo trang (vd Product/Breadcrumb ở trang chi tiết) --}}
        @if (! empty($seo['jsonld']))
            <script type="application/ld+json">{!! json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endif

        {{-- LocalBusiness (site-wide) — chỉ khi shop đã điền hotline ở Cài đặt shop --}}
        @if (! empty($seoSite['local_business']))
            <script type="application/ld+json">{!! json_encode($seoSite['local_business'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
        @endif

        {{-- Google Tag Manager (chỉ khi đặt GTM_ID trong .env) --}}
        @if (config('services.gtm.id'))
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ config('services.gtm.id') }}');</script>
        @endif

        {{-- Google Analytics 4 (chỉ khi admin đã điền mã GA4 ở Cài đặt shop) --}}
        @if (! empty($seoSite['ga_id']))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seoSite['ga_id'] }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $seoSite['ga_id'] }}');</script>
        @endif

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
        @if (config('services.gtm.id'))
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm.id') }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        @inertia
    </body>
</html>
