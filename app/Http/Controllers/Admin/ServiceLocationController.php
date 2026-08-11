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
            'address' => ['nullable', 'string', 'max:255'],
            // map_url được render thành <a href> trên trang checkout của khách.
            // Rule 'url' của Laravel đã chặn javascript: và data: (đã đo), nhưng vẫn cho
            // lọt ftp: và scheme lạ khác. Link bản đồ thì chỉ có thể là http/https, nên
            // siết thêm regex: vừa loại link vô nghĩa, vừa là lớp phòng thủ thứ hai cho
            // chỗ render href (CWE-79).
            'map_url' => ['nullable', 'string', 'max:500', 'url', 'regex:/^https?:\/\//i'],
            'status' => ['required', 'in:open,coming'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Tên vị trí không được bỏ trống.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'map_url.url' => 'Link bản đồ không hợp lệ.',
            'map_url.regex' => 'Link bản đồ phải bắt đầu bằng http:// hoặc https://',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
