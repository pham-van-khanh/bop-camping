<?php

namespace App\Services\Auth;

use App\Mail\OtpMail;
use App\Models\EmailOtp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Gửi & xác thực OTP đăng nhập qua email (KE_HOACH 8.1).
 * - OTP 6 số, lưu ĐÃ HASH, hết hạn 10', dùng 1 lần.
 * - Khoá brute-force: tối đa MAX_ATTEMPTS lần nhập sai / mã.
 */
class OtpService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** Sinh OTP mới, vô hiệu mã cũ chưa dùng, gửi email. Trả về mã thô (chỉ để test/log nội bộ). */
    public function send(string $email): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Vô hiệu mọi OTP chưa dùng trước đó của email này.
        EmailOtp::query()->where('email', $email)->whereNull('consumed_at')
            ->update(['consumed_at' => Carbon::now()]);

        EmailOtp::create([
            'email' => $email,
            'code' => Hash::make($code),
            'expires_at' => Carbon::now()->addMinutes(self::TTL_MINUTES),
        ]);

        Mail::to($email)->send(new OtpMail($code));

        return $code;
    }

    /** Xác thực OTP: đúng & còn hiệu lực → đánh dấu đã dùng, trả true. */
    public function verify(string $email, string $code): bool
    {
        $otp = EmailOtp::query()->usableFor($email)->latest('id')->first();

        if (! $otp || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $otp->code)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['consumed_at' => Carbon::now()]);

        return true;
    }
}
