<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticPage;
use App\Support\EditorHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Admin "Trang nội dung" (Epic 4): sửa các trang tĩnh (giới thiệu...). */
class StaticPageController extends Controller
{
    public function index(): Response
    {
        // Đảm bảo trang giới thiệu + các trang chính sách luôn tồn tại
        // (prod không cần chạy seeder).
        StaticPage::provisionAll();

        return Inertia::render('Admin/StaticPages', [
            'pages' => StaticPage::orderBy('id')->get()->map(fn (StaticPage $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'updated_at' => $p->updated_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    public function edit(StaticPage $staticPage): Response
    {
        return Inertia::render('Admin/StaticPageEdit', [
            'page' => [
                'id' => $staticPage->id,
                'slug' => $staticPage->slug,
                'title' => $staticPage->title,
                'cover_url' => $staticPage->cover_path ? Storage::disk('media')->url($staticPage->cover_path) : null,
                'content' => $staticPage->content,
            ],
        ]);
    }

    public function update(Request $request, StaticPage $staticPage): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|min:2|max:150',
            'cover' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:4096',
            'content' => 'nullable|string|max:200000',
        ], [
            'title.required' => 'Tiêu đề không được bỏ trống.',
            'cover.mimes' => 'Ảnh bìa chỉ nhận jpg, png hoặc webp.',
            'cover.max' => 'Ảnh bìa tối đa 4MB.',
            'content.max' => 'Nội dung quá dài.',
        ]);

        $coverPath = $staticPage->cover_path;
        if ($request->hasFile('cover')) {
            if ($coverPath) {
                Storage::disk('media')->delete($coverPath);
            }
            $coverPath = $request->file('cover')->store('pages', 'media');
        }

        $staticPage->update([
            'title' => $data['title'],
            'cover_path' => $coverPath,
            'content' => EditorHtml::clean($data['content'] ?? null),
        ]);

        return back()->with('success', 'Đã lưu trang nội dung.');
    }
}
