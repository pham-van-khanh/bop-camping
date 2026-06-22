<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferralController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,converted,rejected,expired'],
        ]);

        $referrals = Referral::with(['referrer:id,name,phone', 'referee:id,name,phone', 'firstOrder:id,code'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Referral $r) => [
                'id' => $r->id,
                'referrer' => $r->referrer ? ['name' => $r->referrer->name, 'phone' => $r->referrer->phone] : null,
                'referee' => $r->referee ? ['name' => $r->referee->name, 'phone' => $r->referee->phone] : null,
                'code_used' => $r->code_used,
                'status' => $r->status,
                'first_order_code' => $r->firstOrder?->code,
                'converted_at' => $r->converted_at?->format('d/m/Y H:i'),
                'created_at' => $r->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Referrals', [
            'referrals' => $referrals,
            'filters' => $filters,
        ]);
    }
}
