<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quản lý người dùng (admin). 2 nhóm: khách hàng (passwordless, chỉ xem) và
 * tài khoản quản trị (CRUD). Thiết kế: artifacts/system_design_admin_user_management.md.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() === 'admins' ? 'admins' : 'customers';
        $q = trim($request->string('q')->toString());

        $customers = User::query()
            ->where('is_admin', false)
            ->when($q !== '', fn (Builder $query) => $query->where(
                fn (Builder $w) => $w->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
            ))
            ->withCount('orders')
            ->withSum(
                ['orders as total_spent' => fn (Builder $query) => $query->where('status', '!=', 'cancelled')],
                'total_price'
            )
            ->withMax('orders as last_order_at', 'created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
                'orders_count' => $u->orders_count,
                'total_spent' => (int) ($u->total_spent ?? 0),
                'last_order_at' => $u->last_order_at ? Carbon::parse($u->last_order_at)->format('d/m/Y') : null,
                'created_at' => $u->created_at->format('d/m/Y'),
            ]);

        $admins = User::where('is_admin', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'created_at' => $u->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Users', [
            'tab' => $tab,
            'filters' => ['q' => $q],
            'customers' => $customers,
            'admins' => $admins,
            'customerDetail' => $this->customerDetail($request),
            'stats' => [
                'customers' => User::where('is_admin', false)->count(),
                'admins' => $admins->count(),
            ],
        ]);
    }

    /** Chi tiết 1 khách + lịch sử đơn đối soát theo SĐT (lazy qua ?customer=ID). */
    private function customerDetail(Request $request): ?array
    {
        $id = $request->integer('customer');
        if (! $id) {
            return null;
        }

        $customer = User::where('is_admin', false)->find($id);
        if (! $customer) {
            return null;
        }

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'orders' => $customer->relatedOrders()
                ->orderByDesc('start_date')
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'code' => $o->code,
                    'start_date' => $o->start_date->format('Y-m-d'),
                    'end_date' => $o->end_date->format('Y-m-d'),
                    'total_price' => $o->total_price,
                    'status' => $o->status,
                    'linked' => (int) $o->user_id === $customer->id,
                ])
                ->values(),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/', 'unique:users,phone'],
            'password' => 'required|string|min:6',
        ], [
            'name.required' => 'Vui lòng nhập tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'phone.unique' => 'Số điện thoại đã được dùng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu tối thiểu 6 ký tự.',
        ]);

        User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => $data['password'],   // cast 'hashed' tự hash
            'is_admin' => true,
        ]);

        return back()->with('success', 'Đã thêm quản trị viên.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Form sửa chỉ áp dụng tài khoản admin (khách chỉ xem — system_design §11.3).
        abort_unless($user->is_admin, 404);

        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
        ], [
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'phone.unique' => 'Số điện thoại đã được dùng.',
            'password.min' => 'Mật khẩu tối thiểu 6 ký tự.',
        ]);

        $user->name = $data['name'];
        $user->phone = $data['phone'];
        if (! empty($data['password'])) {
            $user->password = $data['password'];   // chỉ reset khi có nhập
        }
        $user->save();

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['is_admin' => 'required|boolean']);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['message' => 'Không thể tự đổi quyền của chính mình.']);
        }
        if (! $data['is_admin'] && $user->is_admin && $this->adminCount() <= 1) {
            return back()->withErrors(['message' => 'Không thể hạ quyền admin cuối cùng.']);
        }

        $user->is_admin = $data['is_admin'];
        $user->save();

        Log::info('admin.user.role_changed', [
            'actor_id' => $request->user()->id,
            'target_id' => $user->id,
            'is_admin' => $user->is_admin,
        ]);

        return back()->with('success', 'Đã cập nhật quyền.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['message' => 'Không thể xoá chính mình.']);
        }
        if ($user->is_admin && $this->adminCount() <= 1) {
            return back()->withErrors(['message' => 'Không thể xoá admin cuối cùng.']);
        }

        $wasAdmin = $user->is_admin;
        $user->delete();   // orders.user_id tự set null (nullOnDelete) — giữ lịch sử đơn

        Log::info('admin.user.deleted', [
            'actor_id' => $request->user()->id,
            'target_id' => $user->id,
            'was_admin' => $wasAdmin,
        ]);

        return back()->with('success', 'Đã xoá người dùng.');
    }

    private function adminCount(): int
    {
        return User::where('is_admin', true)->count();
    }
}
