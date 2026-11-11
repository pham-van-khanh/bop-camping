<?php

/*
 * Chỉ ghi đè phần SSR (bopcamping-zvrx); các khoá khác lấy mặc định của package
 * (mergeConfigFrom gộp theo khoá cấp đầu).
 *
 * SSR là OPT-IN theo môi trường: mặc định TẮT để máy dev/test không vô tình gọi
 * sang một bundle SSR cũ. Production/staging bật bằng INERTIA_SSR_ENABLED=true trong
 * .env, sau khi đã có process `php artisan inertia:start-ssr` chạy nền (supervisor).
 * Staging dùng chung máy với production → phải đặt cổng khác (INERTIA_SSR_URL +
 * INERTIA_SSR_PORT). Hướng dẫn: artifacts/deploy_runbook.md mục SSR.
 */
return [
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
    ],
];
