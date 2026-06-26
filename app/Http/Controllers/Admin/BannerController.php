<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BannerController extends Controller
{
    public function index(): Response
    {
        $banners = Banner::ordered()->orderBy('placement')->get()
            ->map(fn (Banner $b) => [
                'id' => $b->id,
                'placement' => $b->placement,
                'image' => $b->imageUrl(),
                'title' => $b->title,
                'subtitle' => $b->subtitle,
                'link_url' => $b->link_url,
                'sort_order' => $b->sort_order,
                'is_active' => $b->is_active,
            ]);

        return Inertia::render('Admin/Banners', [
            'banners' => $banners,
            'placements' => Banner::PLACEMENTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, creating: true);
        $data['image'] = $request->file('image')->store('banners', 'public');
        Banner::create($data);

        return back()->with('success', 'Đã thêm banner.');
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validated($request, creating: false);

        if ($request->hasFile('image')) {
            $this->deleteImage($banner);
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        $banner->update($data);

        return back()->with('success', 'Đã cập nhật banner.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $this->deleteImage($banner);
        $banner->delete();

        return back()->with('success', 'Đã xoá banner.');
    }

    /** Xoá file ảnh trên storage (bỏ qua ảnh public /images/... của seed). */
    private function deleteImage(Banner $banner): void
    {
        if ($banner->image && ! Str::startsWith($banner->image, ['/', 'http'])) {
            Storage::disk('public')->delete($banner->image);
        }
    }

    /** Validate + chuẩn hoá. Ảnh bắt buộc khi tạo mới, tuỳ chọn khi sửa. */
    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'placement' => ['required', 'in:'.implode(',', array_keys(Banner::PLACEMENTS))],
            'image' => [$creating ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'title' => ['nullable', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'link_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ], [
            'placement.required' => 'Vui lòng chọn vị trí.',
            'image.required' => 'Vui lòng chọn ảnh banner.',
            'image.mimes' => 'Ảnh phải là jpg, png hoặc webp.',
            'image.max' => 'Ảnh tối đa 8MB.',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? 0;
        unset($data['image']); // ảnh xử lý riêng ở store/update

        return $data;
    }
}
