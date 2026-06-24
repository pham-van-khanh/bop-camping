<?php

namespace Tests\Feature;

use App\Models\EmailOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailOtpSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function email_otps_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('email_otps'));
        $this->assertTrue(Schema::hasColumns('email_otps', [
            'id', 'email', 'code', 'attempts', 'expires_at', 'consumed_at', 'created_at', 'updated_at',
        ]));
    }

    /** @test */
    public function fresh_otp_is_usable(): void
    {
        $otp = $this->otp(expiresAt: Carbon::now()->addMinutes(10));

        $this->assertTrue($otp->isUsable());
        $this->assertFalse($otp->isExpired());
        $this->assertFalse($otp->isConsumed());
        $this->assertSame(0, $otp->fresh()->attempts); // default DB = 0
    }

    /** @test */
    public function expired_otp_is_not_usable(): void
    {
        $otp = $this->otp(expiresAt: Carbon::now()->subMinute());

        $this->assertTrue($otp->isExpired());
        $this->assertFalse($otp->isUsable());
    }

    /** @test */
    public function consumed_otp_is_not_usable(): void
    {
        $otp = $this->otp(expiresAt: Carbon::now()->addMinutes(10));
        $otp->update(['consumed_at' => Carbon::now()]);

        $this->assertTrue($otp->isConsumed());
        $this->assertFalse($otp->isUsable());
    }

    /** @test */
    public function usable_for_scope_returns_only_valid_otps_for_the_email(): void
    {
        $this->otp(email: 'a@x.com', expiresAt: Carbon::now()->addMinutes(10)); // hợp lệ
        $this->otp(email: 'a@x.com', expiresAt: Carbon::now()->subMinute());    // hết hạn
        $consumed = $this->otp(email: 'a@x.com', expiresAt: Carbon::now()->addMinutes(10));
        $consumed->update(['consumed_at' => Carbon::now()]);                    // đã dùng
        $this->otp(email: 'b@x.com', expiresAt: Carbon::now()->addMinutes(10)); // email khác

        $this->assertSame(1, EmailOtp::usableFor('a@x.com')->count());
    }

    private function otp(string $email = 'a@x.com', ?Carbon $expiresAt = null): EmailOtp
    {
        return EmailOtp::create([
            'email' => $email,
            'code' => bcrypt('123456'),
            'expires_at' => $expiresAt ?? Carbon::now()->addMinutes(10),
        ]);
    }
}
