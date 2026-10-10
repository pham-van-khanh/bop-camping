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
            // Ảnh mặc định khi bóc link (Facebook/Zalo/Messenger): bìa thương hiệu cắt
            // sẵn 1200x630 — đúng tỉ lệ 1,91:1 các mạng dùng, nên không bị họ tự cắt
            // mất chữ. Trước đây dùng ảnh album 1.048 KB không đúng tỉ lệ (bopcamping-marf).
            $seoImage = $seo['image'] ?? url('/images/og-cover.jpg');
            $seoUrl = $seo['url'] ?? url()->current();
        @endphp

        @if (! empty($seoSite['google_verification']))
            <meta name="google-site-verification" content="{{ $seoSite['google_verification'] }}">
        @endif

        <title inertia>{{ $seoTitle }}</title>
        <meta name="description" content="{{ $seoDesc }}">
        <meta name="keywords" content="thuê đồ cắm trại, cho thuê lều, thuê túi ngủ, thuê bếp dã ngoại, camping Vinh, camping Hà Nội, cắm trại">
        <meta name="author" content="{{ $brand }}">
        {{-- seo.robots: SeoService::noindex() cho trang mỏng (vd điểm cắm trại chưa có mô tả/ảnh). --}}
        <meta name="robots" content="{{ $seo['robots'] ?? 'index, follow' }}">
        <meta name="theme-color" content="#557A2B">
        <meta name="thumbnail" content="{{ $seoImage }}">
        <link rel="canonical" href="{{ $seoUrl }}">

        {{-- Preload ảnh hero (LCP) — CHỈ ở trang chủ (bopcamping-xxk8).

             Ảnh này chỉ dùng trong HeroSlideshow, mà component đó chỉ có ở Welcome.tsx.
             Để ở layout chung thì MỌI trang (sản phẩm, combo, chính sách...) đều tải thừa
             717 KB — đo được là 50% trọng lượng trang chi tiết SP — lại còn ở
             fetchpriority=high nên giành băng thông với ảnh LCP thật của trang đó.
             Preload sai chỗ thì hại LCP chứ không giúp.

             Href = slide ĐẦU TIÊN thật sự hiện (banner hero admin quản lý), chỉ rơi về
             ảnh mặc định của HeroSlideshow khi chưa có banner (bopcamping-b4c3). Trước
             đây ghi cứng .webp trong khi banner seed là .jpg → tải 2 ảnh, preload phí. --}}
        @if (($page['component'] ?? '') === 'Welcome')
            <link rel="preload" as="image" href="{{ data_get($page, 'props.hero_banners.0.src') ?: '/images/album/beach-night-tent.webp' }}" fetchpriority="high">
        @endif

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
        {{-- Khai sẵn kích thước để mạng xã hội dựng khung ảnh ngay, không phải tải ảnh
             về đo — thiếu hai thẻ này thì lần bóc link đầu tiên hay hiện thiếu ảnh.
             Chỉ đúng cho ảnh mặc định; trang có ảnh riêng (sản phẩm, combo) thì bỏ qua. --}}
        @if (empty($seo['image']))
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:type" content="image/jpeg">
        @endif
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
            <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endforeach

        {{-- FAQPage KHÔNG khai ở đây (bopcamping-s5ct): markup phải ứng với FAQ nhìn
             thấy được trên từng trang, mà FAQ chỉ có ở trang chủ. Để ở layout chung thì
             trang sản phẩm/chính sách cũng khai FAQ dù không có câu nào — Google coi là
             structured data không khớp nội dung. Giờ trang chủ tự đưa vào seo.jsonld
             qua SeoService::faqPage(), sinh từ chính dữ liệu đang render. --}}

        {{-- JSON-LD riêng theo trang (vd Product/Breadcrumb ở trang chi tiết).

             JSON_HEX_TAG ở MỌI khối JSON-LD là BẮT BUỘC: {!! !!} không escape, mà JSON-LD
             giờ chứa chữ khách tự gõ (tên + nội dung đánh giá). Không có cờ này thì tên
             "</script><script>…" thoát khỏi thẻ script = XSS lưu trữ (CWE-79). Cờ đổi
             < > thành < > — JSON vẫn hợp lệ, Google đọc y nguyên. --}}
        @if (! empty($seo['jsonld']))
            <script type="application/ld+json">{!! json_encode($seo['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endif

        {{-- LocalBusiness (site-wide) — chỉ khi shop đã điền hotline ở Cài đặt shop.
             1 khối cho mỗi cơ sở đã có địa chỉ (SeoService::localBusinessJsonLd), gộp
             chung 1 khối generic khi chưa cơ sở nào điền địa chỉ. --}}
        @foreach ($seoSite['local_business'] ?? [] as $block)
            <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
        @endforeach

        {{-- Đo lường/quảng cáo CHỈ ở trang khách (bopcamping-wi6h): /admin và /shipper là nhân
             viên thao tác — đếm vào thì sai số liệu traffic, và pixel quảng cáo sẽ gom nhân viên
             vào tệp remarketing. Mã do admin nhập ở Cài đặt shop; seoSite đưa qua
             SiteSetting::trackingId() (kiểm lại định dạng). Trong <script> luôn in bằng @json
             (escape đúng ngữ cảnh JS), KHÔNG dùng {{ }} (escape HTML, sai ngữ cảnh). --}}
        @php
            $trackingOn = ! request()->is('admin', 'admin/*', 'shipper', 'shipper/*');
            $metaPixel = $trackingOn ? ($seoSite['meta_pixel_id'] ?? null) : null;
            $tiktokPixel = $trackingOn ? ($seoSite['tiktok_pixel_id'] ?? null) : null;
        @endphp

        {{-- Google Tag Manager (chỉ khi đặt GTM_ID trong .env) --}}
        @if ($trackingOn && config('services.gtm.id'))
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json((string) config('services.gtm.id')));</script>
        @endif

        {{-- Google Analytics 4 (chỉ khi admin đã điền mã GA4 ở Cài đặt shop) --}}
        {{-- Page view khi chuyển trang SPA do Enhanced measurement của GA4 tự bắt (theo lịch
             sử trình duyệt) — resources/js/lib/analytics.ts cố ý KHÔNG gửi thêm để khỏi đếm đôi. --}}
        @if ($trackingOn && ! empty($seoSite['ga_id']))
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $seoSite['ga_id'] }}"></script>
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($seoSite['ga_id']));</script>
        @endif

        {{-- Meta Pixel. disablePushState: tắt tự đếm PageView theo pushState — analytics.ts tự
             gửi PageView mỗi lần Inertia đổi URL, tránh đếm đôi. --}}
        @if ($metaPixel)
            <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq.disablePushState=true;fbq('init',@json($metaPixel));fbq('track','PageView');</script>
        @endif

        {{-- TikTok Pixel --}}
        @if ($tiktokPixel)
            <script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=d.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=d.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};ttq.load(@json($tiktokPixel));ttq.page();}(window,document,'ttq');</script>
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
        @if ($trackingOn && config('services.gtm.id'))
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ config('services.gtm.id') }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        @if ($metaPixel)
            <noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $metaPixel }}&amp;ev=PageView&amp;noscript=1"></noscript>
        @endif
        @inertia
    </body>
</html>
