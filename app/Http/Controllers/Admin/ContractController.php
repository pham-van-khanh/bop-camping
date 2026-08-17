<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ContractService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Lập hợp đồng cho một đơn (bopcamping-4jao).
 *
 * Chủ shop xem ảnh CCCD khách gửi qua Zalo rồi NHẬP TAY vào đây. Hệ thống cố ý KHÔNG lưu ảnh
 * CCCD: không lưu thì không phải hứa xoá, và không phát sinh rủi ro dữ liệu cá nhân — Điều 8
 * của hợp đồng cam kết đúng như vậy.
 */
class ContractController extends Controller
{
    public function __construct(private ContractService $contracts) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'id_number' => ['nullable', 'string', 'max:20'],
            'id_issued_on' => ['nullable', 'date'],
            'id_issued_place' => ['nullable', 'string', 'max:120'],
        ], [
            'id_issued_on.date' => 'Ngày cấp không hợp lệ (định dạng ngày/tháng/năm).',
        ]);

        try {
            $this->contracts->createFor($order, [
                'id_number' => $data['id_number'] ?? null,
                'id_issued_on' => $data['id_issued_on'] ?? null,
                'id_issued_place' => $data['id_issued_place'] ?? null,
            ]);
        } catch (InvalidArgumentException $e) {
            // Đơn cha không có ngày/đồ riêng nên không lập hợp đồng được — lập trên đơn con.
            return back()->withErrors(['contract' => $e->getMessage()]);
        }

        return back()->with('success', 'Đã lập hợp đồng. Sao chép link rồi gửi cho khách qua Zalo.');
    }
}
