<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Tài khoản nhận chuyển khoản, dùng dựng QR thanh toán (bopcamping-55rh).
    // Để trong .env chứ KHÔNG để DB: gõ nhầm số tài khoản là tiền sang người lạ, mà đổi
    // ở đây thì phải deploy — chính bước đó là lớp chặn. Không có secret nào (số tài
    // khoản + tên chủ vốn hiện công khai ngay trên QR). Thiếu bank/account thì QR tự ẩn.
    'sepay' => [
        'bank' => env('SEPAY_BANK'),           // VD: Vietcombank (mã/tên ở vietqr.app/banks.json)
        'account' => env('SEPAY_ACCOUNT'),     // Số tài khoản (ảo) SePay cấp
        'holder' => env('SEPAY_HOLDER'),       // Tên chủ TK, không dấu — chỉ để hiện trên QR
    ],

    // Marketing / SEO — chỉ render khi đặt biến môi trường tương ứng (không nhúng ID giả).
    'gtm' => [
        'id' => env('GTM_ID'),                                  // VD: GTM-XXXXXXX
    ],
    'facebook' => [
        'domain_verification' => env('FACEBOOK_DOMAIN_VERIFICATION'),
    ],

];
