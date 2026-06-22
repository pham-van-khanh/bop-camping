<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\ReferralService;

class OrderObserver
{
    public function __construct(private ReferralService $referrals) {}

    /**
     * Khi đơn chuyển sang 'returned' (đã trả) → xét cấp thưởng giới thiệu.
     */
    public function updated(Order $order): void
    {
        if ($order->wasChanged('status') && $order->status === 'returned') {
            $this->referrals->rewardForReturnedOrder($order);
        }
    }
}
