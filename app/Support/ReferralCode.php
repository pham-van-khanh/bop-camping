<?php

namespace App\Support;

use App\Models\ReferralCode as ReferralCodeModel;
use App\Models\Voucher;
use Illuminate\Support\Str;

/**
 * Sinh mã giới thiệu / mã voucher duy nhất (single source of truth — RULES DRY).
 * Alphabet loại bỏ ký tự dễ nhầm (0/O, 1/I/L) để khách đọc/gõ lại dễ.
 */
class ReferralCode
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Mã giới thiệu cá nhân (vd TOM7K3X) — duy nhất trong referral_codes. */
    public static function generate(int $length = 7): string
    {
        do {
            $code = self::random($length);
        } while (ReferralCodeModel::where('code', $code)->exists());

        return $code;
    }

    /** Mã voucher hiển thị duy nhất (VC-XXXXXX). */
    public static function voucher(): string
    {
        do {
            $code = 'VC-'.strtoupper(Str::random(6));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    private static function random(int $length): string
    {
        $out = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $out;
    }
}
