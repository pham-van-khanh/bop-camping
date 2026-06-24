<?php

namespace App\Observers;

use App\Mail\OrderStatusMail;
use App\Models\Order;
use App\Models\PromotionSetting;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\Mail;

class OrderObserver
{
    public function __construct(private ReferralService $referrals) {}

    /**
     * Đổi trạng thái đơn:
     * - sang trạng thái conversion (mặc định 'returned') → tính referral thành công + thưởng referrer.
     * - sang 'cancelled' sau khi đã convert → clawback (thu hồi voucher chưa dùng).
     * - confirmed / returned / cancelled → gửi mail thông báo cho khách (nếu có email thật).
     */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        $settings = PromotionSetting::current();

        if ($order->status === $settings->conversion_trigger_status) {
            $this->referrals->handleConversion($order, $settings);
        } elseif ($order->status === 'cancelled') {
            $this->referrals->clawback($order, $settings);
        }

        // Mail đổi trạng thái (ShouldQueue → gửi nền), chỉ khi có email thật.
        if (OrderStatusMail::notifies($order->status) && ($email = $order->notifiableEmail())) {
            Mail::to($email)->send(new OrderStatusMail($order, $order->status));
        }
    }
}
