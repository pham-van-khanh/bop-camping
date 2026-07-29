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
    /** Các tab của trang: khách hàng · quản trị · shipper (bopcamping-2xf6). */
    private const TABS = ['customers', 'admins', 'shippers'];

    public function index(Request $request): Response
    {
        $tab = in_array($request->string('tab')->toString(), self::TABS, true)
            ? $request->string('tab')->toString()
            : 'customers';
        $q = trim($request->string('q')->toString());

        $customers = User::query()
            ->where('is_admin', false)
            // Shipper là nhân sự, không phải khách — tránh lẫn vào danh sách khách.
            ->where('is_shipper', false)
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
                // Ẩn email tạm <phone>@bopcamping.local — coi như chưa đặt.
                'email' => str_ends_with((string) $u->email, '@bopcamping.local') ? null : $u->email,
                'created_at' => $u->created_at->format('d/m/Y'),
            ]);

        $shippers = User::shippers()
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->hasPlaceholderEmail() ? null : $u->email,
                'is_admin' => (bool) $u->is_admin,
                // Số lượt giao/thu còn phải đi từ hôm nay — cảnh báo trước khi xoá/tắt vai.
                'upcoming_legs' => $this->upcomingLegCount($u),
                'created_at' => $u->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Admin/Users', [
            'tab' => $tab,
            'filters' => ['q' => $q],
            'customers' => $customers,
            'admins' => $admins,
            'shippers' => $shippers,
            'customerDetail' => $this->customerDetail($request),
            'stats' => [
                'customers' => User::where('is_admin', false)->where('is_shipper', false)->count(),
                'admins' => $admins->count(),
                'shippers' => $shippers->count(),
            ],
        ]);
    }

    /**
     * Số lượt giao/thu SẮP TỚI (từ hôm nay) còn gán cho shipper này — dùng để cảnh báo
     * khi admin định xoá tài khoản hoặc tắt vai shipper (prd_shipper_delivery_ops FR-1).
     */
    private function upcomingLegCount(User $user): int
    {
        $today = Carbon::today()->toDateString();

        $pickups = Order::where('pickup_shipper_id', $user->id)
            ->whereNotIn('status', ['returned', 'cancelled'])
            ->whereDate('start_date', '>=', $today)
            ->count();

        $returns = Order::where('return_shipper_id', $user->id)
            ->whereNotIn('status', ['returned', 'cancelled'])
            ->whereDate('end_date', '>=', $today)
            ->count();

        return $pickups + $returns;
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
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => 'required|string|min:6',
            // Tài khoản nhân sự: quản trị hoặc shipper (bopcamping-2xf6). Mặc định admin.
            'role' => ['nullable', 'in:admin,shipper'],
        ], [
            'name.required' => 'Vui lòng nhập tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'phone.unique' => 'Số điện thoại đã được dùng.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được dùng.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu tối thiểu 6 ký tự.',
        ]);

        $isShipper = ($data['role'] ?? 'admin') === 'shipper';

        User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,   // trống → model tự điền email tạm
            'password' => $data['password'],   // cast 'hashed' tự hash
            'is_admin' => ! $isShipper,
            'is_shipper' => $isShipper,
        ]);

        return back()->with('success', $isShipper ? 'Đã thêm shipper.' : 'Đã thêm quản trị viên.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        // Form sửa chỉ áp dụng tài khoản NHÂN SỰ: admin hoặc shipper (khách chỉ xem —
        // system_design §11.3; shipper thêm ở bopcamping-2xf6).
        abort_unless($user->is_admin || $user->is_shipper, 404);

        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'phone' => ['required', 'string', 'regex:/^0[0-9]{8,10}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
        ], [
            'phone.regex' => 'Số điện thoại không hợp lệ (VD: 0912345678).',
            'phone.unique' => 'Số điện thoại đã được dùng.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email đã được dùng.',
            'password.min' => 'Mật khẩu tối thiểu 6 ký tự.',
        ]);

        $user->name = $data['name'];
        $user->phone = $data['phone'];
        if (! empty($data['email'])) {
            $user->email = $data['email'];   // chỉ đổi khi có nhập email mới
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];   // chỉ reset khi có nhập
        }
        $user->save();

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_admin' => 'nullable|boolean',
            // Vai shipper (bopcamping-2xf6) — độc lập với is_admin.
            'is_shipper' => 'nullable|boolean',
            // Tắt vai shipper khi còn lượt sắp tới: cần xác nhận (đơn sẽ về "chưa gán").
            'force' => 'nullable|boolean',
        ]);

        if (! array_key_exists('is_admin', $data) && ! array_key_exists('is_shipper', $data)) {
            return back()->withErrors(['message' => 'Không có quyền nào cần cập nhật.']);
        }
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['message' => 'Không thể tự đổi quyền của chính mình.']);
        }

        if (array_key_exists('is_admin', $data)) {
            if (! $data['is_admin'] && $user->is_admin && $this->adminCount() <= 1) {
                return back()->withErrors(['message' => 'Không thể hạ quyền admin cuối cùng.']);
            }
            $user->is_admin = (bool) $data['is_admin'];
        }

        if (array_key_exists('is_shipper', $data)) {
            $turningOff = ! $data['is_shipper'] && $user->is_shipper;
            $upcoming = $turningOff ? $this->upcomingLegCount($user) : 0;

            if ($upcoming > 0 && ! ($data['force'] ?? false)) {
                return back()->withErrors(['message' => "Shipper này còn {$upcoming} lượt giao/thu sắp tới. Gán lại cho người khác trước, hoặc xác nhận để các lượt đó về \"chưa gán\"."]);
            }

            $user->is_shipper = (bool) $data['is_shipper'];
            if ($turningOff) {
                $this->releaseUpcomingLegs($user);
            }
        }

        $user->save();

        Log::info('admin.user.role_changed', [
            'actor_id' => $request->user()->id,
            'target_id' => $user->id,
            'is_admin' => $user->is_admin,
            'is_shipper' => $user->is_shipper,
        ]);

        return back()->with('success', 'Đã cập nhật quyền.');
    }

    /**
     * Bỏ gán các lượt SẮP TỚI của người vừa bị tắt vai shipper — để lịch không còn trỏ
     * vào người không còn đi giao nữa. Lượt đã qua giữ nguyên để còn dấu ai đã đi.
     */
    private function releaseUpcomingLegs(User $user): void
    {
        $today = Carbon::today()->toDateString();

        Order::where('pickup_shipper_id', $user->id)
            ->whereNotIn('status', ['returned', 'cancelled'])
            ->whereDate('start_date', '>=', $today)
            ->update(['pickup_shipper_id' => null]);

        Order::where('return_shipper_id', $user->id)
            ->whereNotIn('status', ['returned', 'cancelled'])
            ->whereDate('end_date', '>=', $today)
            ->update(['return_shipper_id' => null]);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['message' => 'Không thể xoá chính mình.']);
        }
        if ($user->is_admin && $this->adminCount() <= 1) {
            return back()->withErrors(['message' => 'Không thể xoá admin cuối cùng.']);
        }

        // Shipper còn lượt sắp tới: cảnh báo trước, xoá rồi thì các lượt đó về "chưa gán"
        // (FK nullOnDelete) và không ai biết đơn đó ai đi (bopcamping-2xf6).
        if ($user->is_shipper && ! $request->boolean('force')) {
            $upcoming = $this->upcomingLegCount($user);
            if ($upcoming > 0) {
                return back()->withErrors(['message' => "Shipper này còn {$upcoming} lượt giao/thu sắp tới. Gán lại cho người khác trước, hoặc xác nhận xoá để các lượt đó về \"chưa gán\"."]);
            }
        }

        $wasAdmin = $user->is_admin;
        $wasShipper = $user->is_shipper;
        $user->delete();   // orders.user_id tự set null (nullOnDelete) — giữ lịch sử đơn

        Log::info('admin.user.deleted', [
            'actor_id' => $request->user()->id,
            'target_id' => $user->id,
            'was_admin' => $wasAdmin,
            'was_shipper' => $wasShipper,
        ]);

        return back()->with('success', 'Đã xoá người dùng.');
    }

    private function adminCount(): int
    {
        return User::where('is_admin', true)->count();
    }
}
