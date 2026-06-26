<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CampingSpot;
use App\Models\CampingSpotMedia;
use App\Models\ServiceLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CampingSpotController extends Controller
{
    /** Ảnh + video cho phép trong điểm cắm trại (như review media). */
    private const MEDIA_MIMES = 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime';

    public function index(): Response
    {
        $spots = CampingSpot::with(['media', 'nearestServiceLocation'])
            ->orderBy('province')->ordered()
            ->paginate(50)
            ->through(fn (CampingSpot $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'province' => $s->province,
                'district' => $s->district,
                'terrain_tag' => $s->terrain_tag,
                'description' => $s->description,
                'map_url' => $s->map_url,
                'best_season_from' => $s->best_season_from,
                'best_season_to' => $s->best_season_to,
                'season_label' => $s->seasonLabel(),
                'nearest_service_location_id' => $s->nearest_service_location_id,
                'nearest_name' => $s->nearestServiceLocation?->name,
                'is_suggested' => $s->is_suggested,
                'sort_order' => $s->sort_order,
                'media' => $s->media->map(fn (CampingSpotMedia $m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'url' => Storage::url($m->path),
                ])->values(),
            ]);

        return Inertia::render('Admin/CampingSpots', [
            'spots' => $spots,
            'service_locations' => ServiceLocation::withCount('campingSpots')->ordered()->get()
                ->map(fn (ServiceLocation $l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'area' => $l->area,
                    'status' => $l->status,
                    'sort_order' => $l->sort_order,
                    'spots_count' => $l->camping_spots_count,
                ]),
            'provinces' => CampingSpot::provinces(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        CampingSpot::create($data);

        return back()->with('success', 'Đã thêm điểm cắm trại.');
    }

    public function update(Request $request, CampingSpot $campingSpot): RedirectResponse
    {
        $campingSpot->update($this->validated($request));

        return back()->with('success', 'Đã cập nhật điểm cắm trại.');
    }

    public function destroy(CampingSpot $campingSpot): RedirectResponse
    {
        foreach ($campingSpot->media as $m) {
            Storage::disk('public')->delete($m->path);
        }
        $campingSpot->delete(); // media cascade ở DB

        return back()->with('success', 'Đã xoá điểm cắm trại.');
    }

    public function storeMedia(Request $request, CampingSpot $campingSpot): RedirectResponse
    {
        $request->validate([
            'media' => ['required', 'array', 'max:12'],
            'media.*' => ['file', self::MEDIA_MIMES, 'max:51200'], // ≤50MB
        ], [
            'media.*.mimetypes' => 'Chỉ nhận ảnh (jpg, png, webp) hoặc video (mp4, webm, mov).',
            'media.*.max' => 'Mỗi tệp tối đa 50MB.',
        ]);

        $maxSort = (int) $campingSpot->media()->max('sort_order');
        foreach ((array) $request->file('media', []) as $file) {
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            $campingSpot->media()->create([
                'type' => $isVideo ? 'video' : 'image',
                'path' => $file->store('camping-spots', 'public'),
                'sort_order' => ++$maxSort,
            ]);
        }

        return back()->with('success', 'Đã tải lên ảnh/video.');
    }

    public function destroyMedia(CampingSpot $campingSpot, CampingSpotMedia $media): RedirectResponse
    {
        // Chặn IDOR: media phải thuộc đúng điểm trên URL (CWE-639).
        abort_unless($media->camping_spot_id === $campingSpot->id, 404);

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Đã xoá ảnh/video.');
    }

    /** Validate + chuẩn hoá dữ liệu cho store/update. */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'province' => ['required', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'terrain_tag' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url', 'max:500'],
            'best_season_from' => ['nullable', 'integer', 'min:1', 'max:12'],
            'best_season_to' => ['nullable', 'integer', 'min:1', 'max:12'],
            'nearest_service_location_id' => ['nullable', 'integer', 'exists:service_locations,id'],
            'is_suggested' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => 'Tên điểm không được bỏ trống.',
            'province.required' => 'Vui lòng nhập tỉnh/thành.',
            'terrain_tag.required' => 'Vui lòng nhập loại địa hình.',
            'map_url.url' => 'Link bản đồ phải là một URL hợp lệ.',
        ]);

        $data['is_suggested'] = $request->boolean('is_suggested');
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
