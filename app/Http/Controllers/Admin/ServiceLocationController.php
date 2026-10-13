<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        $serviceLocation->update($this->validated($request, $serviceLocation));

        return back()->with('success', 'Đã cập nhật vị trí phục vụ.');
    }

    public function destroy(ServiceLocation $serviceLocation): RedirectResponse
    {
        // Điểm cắm trại gắn vị trí này sẽ tự gỡ liên kết (nullOnDelete).
        $serviceLocation->delete();

        return back()->with('success', 'Đã xoá vị trí phục vụ.');
    }

    /** Validate + chuẩn hoá dữ liệu cho store/update. */
    private function validated(Request $request, ?ServiceLocation $current = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:100'],
            // Slug = URL trang khu vực /thue-do-cam-trai/{slug} VÀ khoá lọc ?vi-tri= (bopcamping-y05c).
            // Để trống -> tự sinh từ tên. Chỉ chữ thường không dấu, số, gạch nối.
            'slug' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('service_locations', 'slug')->ignore($current?->id)],
            // Đoạn giới thiệu trên trang khu vực — văn bản thường, trống thì trang tự sinh.
            'intro' => ['nullable', 'string', 'max:3000'],
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
            'slug.regex' => 'Slug chỉ gồm chữ thường không dấu, số và dấu gạch nối (vd: ha-noi).',
            'slug.unique' => 'Slug này đã được dùng cho vị trí khác.',
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
