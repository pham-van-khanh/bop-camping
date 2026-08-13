<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CapitalContribution;
use App\Models\Expense;
use App\Models\User;
use App\Services\FinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * bopcamping-n4qy — màn Tài chính: vốn ban đầu, thu, chi, lợi nhuận, tiến độ hoàn vốn,
 * chart và bảng theo tháng. Quản lý khoản chi nằm luôn ở đây (trước ở màn Thống kê) để
 * chỉ có MỘT chỗ nhập — hai form nhập cùng một bảng là mở đường cho số liệu lệch nhau.
 *
 * Mọi con số tiền lấy từ FinanceService, controller không tự tính.
 */
class FinanceController extends Controller
{
    private const PERIODS = ['month', 'quarter', 'year', 'all'];

    public function __construct(private FinanceService $finance) {}

    public function index(Request $request): Response
    {
        $period = in_array($request->query('period'), self::PERIODS, true)
            ? $request->query('period')
            : 'all';

        $from = match ($period) {
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            'year' => Carbon::now()->startOfYear(),
            default => null,
        };

        return Inertia::render('Admin/Finance', [
            'period' => $period,
            // Toàn cảnh LUÔN tính trên toàn bộ lịch sử, không theo bộ lọc kỳ: vốn còn
            // lại và tiến độ hoàn vốn mà tính theo tháng thì vô nghĩa.
            'overview' => $this->finance->overview(),
            'period_summary' => [
                'revenue' => $this->finance->revenue($from),
                'expense' => $this->finance->expenseTotal($from),
                'profit' => $this->finance->revenue($from) - $this->finance->expenseTotal($from),
                'returned_count' => $this->finance->returnedCount($from),
            ],
            'monthly' => $this->finance->monthlySeries(),
            // Chia lợi nhuận luôn tính trên TOÀN BỘ lịch sử, không theo bộ lọc kỳ: luật
            // bù lỗ luỹ kế chỉ đúng khi chạy từ tháng đầu tiên (bopcamping-n4qy).
            'sharing' => $this->finance->profitSharing(),
            'by_category' => $this->finance->expenseByCategory($from),
            'expenses' => $this->expenseRows($from),
            'categories' => Expense::categoryOptions(),
            'capital' => $this->capitalRows(),
            // Admin thường VẪN xem được toàn bộ số liệu (hai người góp vốn đều là chủ),
            // chỉ super admin mới sửa. FE ẩn form/nút theo cờ này; chặn thật nằm ở
            // middleware 'super-admin' trên route ghi — cờ này chỉ để đỡ mời gọi bấm.
            'can_manage' => (bool) $request->user()?->is_super_admin,
            'admins' => User::where('is_admin', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),
        ]);
    }

    /**
     * Sổ góp vốn, mới nhất trước — kèm tổng của từng người để đối chiếu với tỉ lệ chia.
     *
     * @return list<array<string, mixed>>
     */
    private function capitalRows(): array
    {
        return CapitalContribution::with('user:id,name')
            ->orderByDesc('contributed_on')->orderByDesc('id')
            ->get()
            ->map(fn (CapitalContribution $c) => [
                'id' => $c->id,
                'user_id' => $c->user_id,
                'user_name' => $c->user?->name ?? 'Đã xoá',
                'amount' => $c->amount,
                'contributed_on' => $c->contributed_on->format('Y-m-d'),
                'contributed_on_label' => $c->contributed_on->format('d/m/Y'),
                'note' => $c->note,
            ])->values()->all();
    }

    public function storeCapital(Request $request): RedirectResponse
    {
        CapitalContribution::create($this->validateCapital($request));

        return back()->with('success', 'Đã ghi nhận vốn góp.');
    }

    public function updateCapital(Request $request, CapitalContribution $capital): RedirectResponse
    {
        $capital->update($this->validateCapital($request));

        return back()->with('success', 'Đã cập nhật vốn góp.');
    }

    public function destroyCapital(CapitalContribution $capital): RedirectResponse
    {
        $capital->delete();

        return back()->with('success', 'Đã xoá khoản góp vốn.');
    }

    /** @return array{user_id: int, amount: int, contributed_on: string, note: ?string} */
    private function validateCapital(Request $request): array
    {
        return $request->validate([
            // Chỉ nhận tài khoản ĐANG là admin: vốn góp gắn với người quản trị shop,
            // trỏ vào một khách hàng bất kỳ là sai mô hình.
            'user_id' => ['required', Rule::exists('users', 'id')->where('is_admin', true)],
            'amount' => ['required', 'integer', 'min:1'],
            'contributed_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'user_id.required' => 'Chọn người góp vốn.',
            'user_id.exists' => 'Người góp vốn phải là một tài khoản quản trị.',
            'amount.required' => 'Nhập số tiền góp.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'contributed_on.required' => 'Chọn ngày góp.',
        ]);
    }

    /**
     * Danh sách khoản chi của kỳ đang xem. Giới hạn 200 dòng để trang không phình;
     * nói rõ số bị cắt ở FE thay vì lặng lẽ hiển thị thiếu.
     *
     * @return array{rows: list<array<string, mixed>>, total_count: int}
     */
    private function expenseRows(?Carbon $from): array
    {
        $base = fn () => Expense::query()
            ->when($from, fn ($q) => $q->where('spent_on', '>=', $from->toDateString()));

        $rows = $base()
            ->orderByDesc('spent_on')->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Expense $e) => [
                'id' => $e->id,
                'spent_on' => $e->spent_on->format('Y-m-d'),
                'spent_on_label' => $e->spent_on->format('d/m/Y'),
                'amount' => $e->amount,
                'category' => $e->category,
                'category_label' => Expense::CATEGORY_LABELS[$e->category] ?? $e->category,
                'note' => $e->note,
            ])->values()->all();

        return ['rows' => $rows, 'total_count' => $base()->count()];
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        Expense::create($this->validateExpense($request));

        return back()->with('success', 'Đã thêm khoản chi.');
    }

    public function updateExpense(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validateExpense($request));

        return back()->with('success', 'Đã cập nhật khoản chi.');
    }

    public function destroyExpense(Expense $expense): RedirectResponse
    {
        $expense->delete();

        return back()->with('success', 'Đã xoá khoản chi.');
    }

    /** @return array{spent_on: string, amount: int, category: string, note: ?string} */
    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'spent_on' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'category' => ['required', 'in:'.implode(',', Expense::CATEGORIES)],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'spent_on.required' => 'Chọn ngày chi.',
            'amount.required' => 'Nhập số tiền.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'category.in' => 'Loại chi phí không hợp lệ.',
        ]);
    }
}
