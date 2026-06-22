<?php

namespace App\Support;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Str;

/**
 * Sinh mã giới thiệu duy nhất cho User (single source of truth — RULES DRY).
 * Alphabet loại bỏ ký tự dễ nhầm (0/O, 1/I/L) để khách đọc/gõ lại dễ.
 */
class ReferralCode
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(int $length = 6): string
    {
        do {
            $code = collect(range(1, $length))
                ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
                ->implode('');
        } while (User::where('referral_code', $code)->exists());

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
}
