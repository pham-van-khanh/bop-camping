<?php

return [
    /*
    | Thông tin chuyển khoản hiển thị ở checkout (khách trả trước phí cọc / toàn bộ).
    | KHÔNG phải secret — đọc từ env để dễ đổi khi deploy; có mặc định để dev chạy ngay.
    */
    'bank' => [
        'name' => env('SHOP_BANK_NAME', 'Vietcombank'),
        'account_number' => env('SHOP_BANK_ACCOUNT', '0123456789'),
        'account_holder' => env('SHOP_BANK_HOLDER', 'BOP CAMPING'),
    ],
];
