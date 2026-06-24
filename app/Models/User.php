<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\ReferralCode as ReferralCodeGenerator;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Email là BẮT BUỘC (đăng nhập OTP, KE_HOACH 8.1). Nếu tạo user mà chưa có
     * email (vd tạo nhanh bằng SĐT) → điền email tạm; khách sẽ bổ sung email
     * thật khi xác thực OTP. Cùng quy ước với migration backfill.
     */
    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (blank($user->email)) {
                $local = $user->phone ?: ('user'.Str::random(8));
                $user->email = $local.'@bopcamping.local';
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /** Đơn đã liên kết tài khoản (user_id) — dùng cho thống kê list. */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Mã giới thiệu cá nhân (1 user 1 mã). */
    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    /** Các lượt giới thiệu user này thực hiện (với vai trò referrer). */
    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /** Lượt user này được giới thiệu (với vai trò referee) — tối đa 1. */
    public function refereeRecord(): HasOne
    {
        return $this->hasOne(Referral::class, 'referee_id');
    }

    /** Voucher khách sở hữu. */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * Email các tài khoản admin để gửi thông báo (đơn mới…).
     * Bỏ email tạm <phone>@bopcamping.local — chỉ gửi tới email thật admin đã đặt.
     *
     * @return array<int, string>
     */
    public static function adminNotifyEmails(): array
    {
        return static::query()->where('is_admin', true)
            ->where('email', 'not like', '%@bopcamping.local')
            ->pluck('email')->all();
    }

    /** Lấy mã giới thiệu (tạo nếu chưa có) — single source. */
    public function getReferralCode(): string
    {
        $record = $this->referralCode()->firstOrCreate(
            [],
            ['code' => ReferralCodeGenerator::generate()],
        );

        return $record->code;
    }

    /**
     * Đơn đối soát đầy đủ cho 1 khách: liên kết qua user_id HOẶC trùng SĐT
     * (bắt cả đơn vãng lai đặt trước khi khách đăng nhập — xem system_design D2).
     */
    public function relatedOrders(): Builder
    {
        return Order::where(function (Builder $q) {
            $q->where('user_id', $this->id);
            if ($this->phone) {
                $q->orWhere('customer_phone', $this->phone);
            }
        });
    }
}
