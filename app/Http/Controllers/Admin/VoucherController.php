<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotionSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Support\ReferralCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:active,used,expired,revoked'],
            'source' => ['nullable', 'in:referral_reward,first_order,manual_admin,campaign'],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $vouchers = Voucher::with('user:id,name,phone')
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['source'] ?? null, fn ($q, $s) => $q->where('source', $s))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('code', 'like', "%{$term}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Voucher $v) => [
                'id' => $v->id,
                'code' => $v->code,
                'type' => $v->type,
                'value' => (float) $v->value,
                'source' => $v->source,
                'status' => $v->status,
                'user' => $v->user ? ['name' => $v->user->name, 'phone' => $v->user->phone] : null,
                'expires_at' => $v->expires_at?->format('d/m/Y'),
                'used_at' => $v->used_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Vouchers', [
            'vouchers' => $vouchers,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'exists:users,phone'],
            'type' => ['required', 'in:fixed,percent'],
            'value' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $user = User::where('phone', $data['phone'])->firstOrFail();
        $days = $data['validity_days'] ?? PromotionSetting::current()->voucher_validity_days;

        Voucher::create([
            'user_id' => $user->id,
            'code' => ReferralCode::voucher(),
            'type' => $data['type'],
            'value' => $data['value'],
            'source' => 'manual_admin',
            'status' => 'active',
            'max_uses' => 1,
            'expires_at' => now()->addDays($days),
        ]);

        return back()->with('success', "Đã tạo voucher cho {$user->name}.");
    }

    public function revoke(Voucher $voucher): RedirectResponse
    {
        if ($voucher->status === 'active') {
            $voucher->update(['status' => 'revoked']);
        }

        return back()->with('success', 'Đã thu hồi voucher.');
    }
}
