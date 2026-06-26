<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ServiceLocationController extends Controller
{
    // Vị trí phục vụ được quản lý ngay trong màn Điểm cắm trại (AdminCampingSpotController::index
    // truyền danh sách). Ở đây chỉ giữ các endpoint ghi: thêm/sửa/xoá.

    public function store(Request $request): RedirectResponse
    {
        ServiceLocation::create($this->validated($request));

        return back()->with('success', 'Đã thêm vị trí phục vụ.');
    }

    public function update(Request $request, ServiceLocation $serviceLocation): RedirectResponse
    {
        $serviceLocation->update($this->validated($request));

        return back()->with('success', 'Đã cập nhật vị trí phục vụ.');
    }

    public function destroy(ServiceLocation $serviceLocation): RedirectResponse
    {
        // Điểm cắm trại gắn vị trí này sẽ tự gỡ liên kết (nullOnDelete).
        $serviceLocation->delete();

        return back()->with('success', 'Đã xoá vị trí phục vụ.');
    }

    /** Validate + chuẩn hoá dữ liệu cho store/update. */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:open,coming'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Tên vị trí không được bỏ trống.',
            'status.required' => 'Vui lòng chọn trạng thái.',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
