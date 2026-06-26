<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceLocationController extends Controller
{
    public function index(): Response
    {
        $locations = ServiceLocation::withCount('campingSpots')->ordered()->get()
            ->map(fn (ServiceLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'area' => $l->area,
                'status' => $l->status,
                'sort_order' => $l->sort_order,
                'spots_count' => $l->camping_spots_count,
            ]);

        return Inertia::render('Admin/ServiceLocations', [
            'locations' => $locations,
        ]);
    }

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
