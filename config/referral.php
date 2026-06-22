<?php

return [
    /*
    | Số tiền (VND) của mỗi voucher thưởng giới thiệu — giảm vào tiền thuê đơn sau.
    | Cấp cho CẢ referrer và referee khi referee trả đơn đầu tiên.
    */
    'reward_amount' => (int) env('REFERRAL_REWARD_AMOUNT', 50000),
];
