<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
