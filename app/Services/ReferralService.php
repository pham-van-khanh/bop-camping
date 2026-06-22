<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use App\Support\ReferralCode;
use Illuminate\Support\Facades\DB;

/**
 * Logic chương trình giới thiệu (single source of truth — RULES DRY).
 * - Cấp thưởng khi referee TRẢ đơn đầu tiên (returned).
 * - Redeem voucher vào tiền thuê đơn mới.
 */
class ReferralService
{
    /**
     * Cấp voucher thưởng cho CẢ referee và referrer khi referee trả đơn đầu tiên.
     * Idempotent: chỉ cấp một lần (guard + unique index referred_user_id,source).
     */
    public function rewardForReturnedOrder(Order $order): void
    {
        $referee = $order->user;

        // Chỉ thưởng cho đơn gắn tài khoản, và referee phải được ai đó giới thiệu.
        if (! $referee || ! $referee->referred_by) {
            return;
        }

        // Chỉ kích hoạt ở đơn returned ĐẦU TIÊN của referee.
        $returnedCount = Order::where('user_id', $referee->id)
            ->where('status', 'returned')
            ->count();

        if ($returnedCount !== 1) {
            return;
        }

        // Guard idempotency (phòng race / chạy lại observer).
        $alreadyRewarded = Voucher::where('referred_user_id', $referee->id)
            ->whereIn('source', ['referral_referee', 'referral_referrer'])
            ->exists();

        if ($alreadyRewarded) {
            return;
        }

        $amount = (int) config('referral.reward_amount');

        DB::transaction(function () use ($referee, $order, $amount) {
            // Voucher cho referee (khách mới).
            Voucher::create([
                'user_id' => $referee->id,
                'code' => ReferralCode::voucher(),
                'amount' => $amount,
                'source' => 'referral_referee',
                'referred_user_id' => $referee->id,
                'trigger_order_id' => $order->id,
            ]);

            // Voucher cho người giới thiệu (nếu còn tồn tại).
            if ($referrer = $referee->referrer) {
                Voucher::create([
                    'user_id' => $referrer->id,
                    'code' => ReferralCode::voucher(),
                    'amount' => $amount,
                    'source' => 'referral_referrer',
                    'referred_user_id' => $referee->id,
                    'trigger_order_id' => $order->id,
                ]);
            }
        });
    }

    /**
     * Tìm voucher dùng được của 1 user theo mã (case-insensitive). null nếu không hợp lệ.
     */
    public function findUsableVoucher(?User $user, ?string $code): ?Voucher
    {
        if (! $user || ! $code) {
            return null;
        }

        return Voucher::where('user_id', $user->id)
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->usable()
            ->first();
    }

    /**
     * Áp voucher vào 1 đơn đã tạo: tính discount, đánh dấu đã dùng, lưu discount_total.
     * Trả về số tiền đã giảm.
     */
    public function redeem(Voucher $voucher, Order $order): int
    {
        $discount = min($voucher->amount, (int) $order->total_price);

        DB::transaction(function () use ($voucher, $order, $discount) {
            $order->update(['discount_total' => $discount]);
            $voucher->update([
                'redeemed_at' => now(),
                'order_id' => $order->id,
            ]);
        });

        return $discount;
    }
}
