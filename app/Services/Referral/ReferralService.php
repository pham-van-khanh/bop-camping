<?php

namespace App\Services\Referral;

use App\Models\Order;
use App\Models\PromotionSetting;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use App\Models\Voucher;
use App\Support\ReferralCode as ReferralCodeGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Logic chương trình giới thiệu 1 cấp (single source of truth — RULES DRY).
 * - Referee: giảm % cho ĐƠN ĐẦU khi nhập mã.
 * - Referrer: nhận voucher cố định mỗi lượt giới thiệu THÀNH CÔNG (đơn đầu đạt trạng thái conversion).
 */
class ReferralService
{
    /**
     * Gắn referral + tính giảm cho đơn ĐẦU của referee khi nhập mã.
     *
     * @return array{discount:int, reason:string, referral:?Referral}
     */
    public function applyRefereeFirstOrderDiscount(Order $order, ?string $code, ?PromotionSetting $settings = null): array
    {
        $settings ??= PromotionSetting::current();
        $fail = fn (string $reason) => ['discount' => 0, 'reason' => $reason, 'referral' => null];

        if (! $settings->referral_enabled || ! $code) {
            return $fail('disabled_or_empty');
        }

        $referralCode = ReferralCode::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->first();
        if (! $referralCode) {
            return $fail('invalid_code');
        }

        $buyerId = $order->user_id;
        if (! $buyerId) {
            return $fail('guest'); // cần tài khoản để ghi nhận referee
        }
        if ($referralCode->user_id === $buyerId) {
            return $fail('self_referral');
        }

        // Phải là đơn ĐẦU TIÊN của referee.
        $hasPriorOrder = Order::where('user_id', $buyerId)->where('id', '!=', $order->id)->exists();
        if ($hasPriorOrder) {
            return $fail('not_first_order');
        }

        // 1 referee chỉ được tính 1 lần.
        if (Referral::where('referee_id', $buyerId)->exists()) {
            return $fail('already_referred');
        }

        $referral = Referral::create([
            'referrer_id' => $referralCode->user_id,
            'referee_id' => $buyerId,
            'code_used' => $referralCode->code,
            'status' => 'pending',
            'first_order_id' => $order->id,
        ]);

        $base = (int) $order->total_price;
        $raw = $settings->referee_discount_type === 'percent'
            ? (int) floor($base * (float) $settings->referee_discount_value / 100)
            : (int) round((float) $settings->referee_discount_value);
        $cap = (int) floor($base * (float) $settings->max_discount_percent_per_order / 100);
        $discount = max(0, min($raw, $cap, $base));

        if ($discount > 0) {
            $order->applyDiscountLines([
                ['source' => 'referral', 'amount' => $discount, 'code' => $referralCode->code],
            ]);
        }

        return ['discount' => $discount, 'reason' => 'ok', 'referral' => $referral];
    }

    /**
     * Khi đơn đổi sang trạng thái conversion → đánh dấu referral converted + thưởng referrer.
     */
    public function handleConversion(Order $order, ?PromotionSetting $settings = null): void
    {
        $settings ??= PromotionSetting::current();

        if (! $settings->referral_enabled || $order->status !== $settings->conversion_trigger_status) {
            return;
        }

        $referral = Referral::where('status', 'pending')
            ->where(fn ($q) => $q->where('first_order_id', $order->id)->orWhere('referee_id', $order->user_id))
            ->first();

        if (! $referral) {
            return;
        }

        DB::transaction(function () use ($referral, $order, $settings) {
            $referral->update([
                'status' => 'converted',
                'converted_at' => now(),
                'first_order_id' => $referral->first_order_id ?? $order->id,
            ]);

            if (! $this->withinMonthlyLimit($referral->referrer_id, $settings)) {
                return; // đã convert nhưng vượt trần tháng → không thưởng
            }

            $alreadyRewarded = Voucher::where('user_id', $referral->referrer_id)
                ->where('source', 'referral_reward')->exists();

            if ($settings->referrer_reward_per_referral || ! $alreadyRewarded) {
                $this->issueReferrerVoucher($referral, $settings);
            }
        });
    }

    /** Thu hồi khi đơn đầu của referee bị huỷ/hoàn sau khi đã convert. */
    public function clawback(Order $order, ?PromotionSetting $settings = null): void
    {
        $settings ??= PromotionSetting::current();

        $referral = Referral::where('status', 'converted')
            ->where(fn ($q) => $q->where('first_order_id', $order->id)->orWhere('referee_id', $order->user_id))
            ->first();

        if (! $referral || ! $settings->reward_clawback_enabled) {
            return;
        }

        DB::transaction(function () use ($referral) {
            $referral->update(['status' => 'rejected']);
            // Thu hồi voucher CHƯA dùng sinh từ referral này (đã dùng thì giữ — theo policy mặc định).
            Voucher::where('referral_id', $referral->id)
                ->where('status', 'active')
                ->update(['status' => 'revoked']);
        });
    }

    public function issueReferrerVoucher(Referral $referral, PromotionSetting $settings): Voucher
    {
        return Voucher::create([
            'user_id' => $referral->referrer_id,
            'code' => ReferralCodeGenerator::voucher(),
            'type' => $settings->referrer_reward_type,
            'value' => $settings->referrer_reward_value,
            'source' => 'referral_reward',
            'referral_id' => $referral->id,
            'status' => 'active',
            'applies_to' => $settings->discount_applies_to,
            'max_uses' => 1,
            'used_count' => 0,
            'expires_at' => now()->addDays($settings->voucher_validity_days),
        ]);
    }

    /** Mã giới thiệu cá nhân của user (tạo nếu chưa có). */
    public function codeFor(User $user): string
    {
        return $user->getReferralCode();
    }

    private function withinMonthlyLimit(int $referrerId, PromotionSetting $settings): bool
    {
        if ($settings->max_referrals_per_user_per_month <= 0) {
            return true;
        }

        $count = Referral::where('referrer_id', $referrerId)
            ->where('status', 'converted')
            ->whereBetween('converted_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();

        return $count <= $settings->max_referrals_per_user_per_month;
    }
}
